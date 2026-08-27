<?php

declare(strict_types=1);

namespace Architex\Platform\Trust;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use JsonException;
use PDO;
use Throwable;

final readonly class VerificationService
{
    private const KINDS = ['professional_registration','company_document','insurance_compliance','contractor_supplier_compliance'];
    private const SUBMISSION_GRADES = ['self_declared','document_uploaded'];
    private const REVIEW_DECISIONS = ['manually_reviewed','externally_verified','rejected'];
    private AuditService $audit;

    public function __construct(private PDO $pdo)
    {
        $this->audit = new AuditService($pdo);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function saveProfile(string $organisationId, string $userId, array $input): array
    {
        $this->assertActiveMember($organisationId, $userId);
        $displayName = $this->requiredText($input, 'displayName', 180);
        $profession = strtolower($this->requiredText($input, 'profession', 80));
        $bio = $this->optionalText($input, 'bio', 4000);
        $areas = $input['serviceAreas'] ?? [];
        if (!is_array($areas) || count($areas) > 30) throw new TrustException('invalid_service_areas');
        $serviceAreas = [];
        foreach ($areas as $area) {
            if (!is_string($area) || trim($area) === '' || mb_strlen(trim($area)) > 120) throw new TrustException('invalid_service_areas');
            $serviceAreas[] = trim($area);
        }
        $visible = ($input['directoryVisible'] ?? false) === true;
        return $this->transaction(function () use ($organisationId, $userId, $displayName, $profession, $bio, $serviceAreas, $visible): array {
            $existing = $this->pdo->prepare('SELECT LOWER(HEX(id)) FROM professional_profiles WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) FOR UPDATE');
            $existing->execute(['organisation_id' => $organisationId, 'user_id' => $userId]);
            $id = $existing->fetchColumn();
            $id = is_string($id) ? $id : Identifier::generate();
            $statement = $this->pdo->prepare('INSERT INTO professional_profiles(id,organisation_id,user_id,display_name,profession,bio,service_areas,directory_visible) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:display_name,:profession,:bio,:service_areas,:directory_visible) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),profession=VALUES(profession),bio=VALUES(bio),service_areas=VALUES(service_areas),directory_visible=VALUES(directory_visible)');
            $statement->execute(['id'=>$id,'organisation_id'=>$organisationId,'user_id'=>$userId,'display_name'=>$displayName,'profession'=>$profession,'bio'=>$bio,'service_areas'=>json_encode($serviceAreas, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),'directory_visible'=>$visible ? 1 : 0]);
            $resolved = $this->pdo->prepare('SELECT LOWER(HEX(id)) FROM professional_profiles WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id)');
            $resolved->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
            $actualId = $resolved->fetchColumn();
            if (!is_string($actualId)) throw new TrustException('profile_persistence_failed');
            $this->audit->append($organisationId, $userId, 'professional_profile.saved', 'professional_profile', $actualId, ['directoryVisible'=>$visible,'profession'=>$profession]);
            return ['id'=>$actualId,'displayName'=>$displayName,'profession'=>$profession,'bio'=>$bio,'serviceAreas'=>$serviceAreas,'directoryVisible'=>$visible];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function submit(string $organisationId, string $userId, array $input): array
    {
        $this->assertActiveMember($organisationId, $userId);
        $kind = $input['kind'] ?? null;
        $grade = $input['grade'] ?? null;
        if (!is_string($kind) || !in_array($kind, self::KINDS, true)) throw new TrustException('invalid_verification_kind');
        if (!is_string($grade) || !in_array($grade, self::SUBMISSION_GRADES, true)) throw new TrustException('invalid_verification_grade');
        $purpose = $this->optionalText($input, 'purpose', 500);
        $retentionUntil = $this->date($input['retentionUntil'] ?? null, false);
        if (($input['consentGiven'] ?? false) !== true || $purpose === '' || $retentionUntil === null) throw new TrustException('popia_metadata_required');
        if ($retentionUntil < new DateTimeImmutable('today')) throw new TrustException('invalid_retention_date');
        $expiresOn = $this->date($input['expiresOn'] ?? null, true);
        $evidenceFileId = $this->optionalIdentifier($input['evidenceFileId'] ?? null);
        if ($grade === 'document_uploaded' && $evidenceFileId === null) throw new TrustException('document_evidence_required');
        if ($evidenceFileId !== null && !$this->evidenceExists($organisationId, $userId, $evidenceFileId)) throw new TrustException('evidence_file_not_found');

        return $this->transaction(function () use ($organisationId, $userId, $kind, $grade, $input, $evidenceFileId, $purpose, $retentionUntil, $expiresOn): array {
            $id = Identifier::generate();
            $statement = $this->pdo->prepare('INSERT INTO verification_records(id,organisation_id,user_id,kind,grade,registration_body,registration_number,evidence_file_id,popia_consent_at,processing_purpose,retention_until,expires_on) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:user_id),:kind,:grade,:registration_body,:registration_number,UNHEX(:evidence_file_id),UTC_TIMESTAMP(6),:purpose,:retention_until,:expires_on)');
            $statement->execute([
                'id'=>$id,'organisation_id'=>$organisationId,'user_id'=>$userId,'kind'=>$kind,'grade'=>$grade,
                'registration_body'=>$this->nullableText($input, 'registrationBody', 120),'registration_number'=>$this->nullableText($input, 'registrationNumber', 120),
                'evidence_file_id'=>$evidenceFileId,'purpose'=>$purpose,'retention_until'=>$retentionUntil->format('Y-m-d'),'expires_on'=>$expiresOn?->format('Y-m-d'),
            ]);
            $this->audit->append($organisationId, $userId, 'verification.submitted', 'verification_record', $id, ['kind'=>$kind,'grade'=>$grade,'expiresOn'=>$expiresOn?->format('Y-m-d')]);
            return ['id'=>$id,'kind'=>$kind,'grade'=>$grade,'effectiveGrade'=>$this->effectiveGrade($grade, $expiresOn?->format('Y-m-d'))];
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function review(string $organisationId, string $reviewerId, string $verificationId, array $input): array
    {
        $this->assertReviewer($organisationId, $reviewerId);
        $decision = $input['decision'] ?? null;
        if (!is_string($decision) || !in_array($decision, self::REVIEW_DECISIONS, true)) throw new TrustException('invalid_review_decision');
        $provider = $this->nullableText($input, 'provider', 160);
        $providerReference = $this->nullableText($input, 'providerReference', 180);
        if ($decision === 'externally_verified' && ($provider === null || $providerReference === null)) throw new TrustException('external_provider_evidence_required');
        $note = $this->optionalText($input, 'note', 4000);

        $this->pdo->beginTransaction();
        try {
            $find = $this->pdo->prepare('SELECT kind,LOWER(HEX(user_id)) subject_user_id FROM verification_records WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) FOR UPDATE');
            $find->execute(['id'=>$verificationId,'organisation_id'=>$organisationId]);
            $record = $find->fetch();
            if (!is_array($record)) throw new TrustException('verification_not_found');
            if (hash_equals((string)$record['subject_user_id'], strtolower($reviewerId))) throw new TrustException('self_review_forbidden');
            $kind = (string)$record['kind'];
            $update = $this->pdo->prepare('UPDATE verification_records SET grade=:decision,provider=:provider,provider_reference=:provider_reference,reviewed_by=UNHEX(:reviewer),reviewed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id)');
            $update->execute(['decision'=>$decision,'provider'=>$provider,'provider_reference'=>$providerReference,'reviewer'=>$reviewerId,'id'=>$verificationId,'organisation_id'=>$organisationId]);
            $insert = $this->pdo->prepare('INSERT INTO verification_reviews(id,verification_id,organisation_id,reviewer_user_id,decision,review_note,provider,provider_reference) VALUES(UNHEX(:id),UNHEX(:verification_id),UNHEX(:organisation_id),UNHEX(:reviewer),:decision,:note,:provider,:provider_reference)');
            $insert->execute(['id'=>Identifier::generate(),'verification_id'=>$verificationId,'organisation_id'=>$organisationId,'reviewer'=>$reviewerId,'decision'=>$decision,'note'=>$note,'provider'=>$provider,'provider_reference'=>$providerReference]);
            $this->audit->append($organisationId, $reviewerId, 'verification.reviewed', 'verification_record', $verificationId, ['kind'=>$kind,'decision'=>$decision,'provider'=>$provider,'providerReference'=>$providerReference]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
        return ['id'=>$verificationId,'kind'=>$kind,'grade'=>$decision,'effectiveGrade'=>$decision];
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(string $organisationId, string $userId): array
    {
        $this->assertActiveMember($organisationId, $userId);
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,kind,grade,registration_body,registration_number,LOWER(HEX(evidence_file_id)) evidence_file_id,provider,provider_reference,DATE_FORMAT(retention_until,\'%Y-%m-%d\') retention_until,DATE_FORMAT(expires_on,\'%Y-%m-%d\') expires_on,created_at,reviewed_at FROM verification_records WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) ORDER BY created_at DESC');
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
        $rows = [];
        foreach ($statement as $row) {
            $row['effectiveGrade'] = $this->effectiveGrade((string)$row['grade'], is_string($row['expires_on']) ? $row['expires_on'] : null);
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function profileForUser(string $organisationId, string $userId): ?array
    {
        $this->assertActiveMember($organisationId, $userId);
        $statement = $this->pdo->prepare('SELECT LOWER(HEX(id)) id,display_name,profession,bio,service_areas,directory_visible FROM professional_profiles WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id)');
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
        $row = $statement->fetch();
        if (!is_array($row)) return null;
        return ['id'=>$row['id'],'displayName'=>$row['display_name'],'profession'=>$row['profession'],'bio'=>$row['bio'],'serviceAreas'=>json_decode((string)$row['service_areas'], true, 16, JSON_THROW_ON_ERROR),'directoryVisible'=>(bool)$row['directory_visible']];
    }

    /** @return list<array<string,mixed>> */
    public function searchDirectory(?string $profession = null, ?string $serviceArea = null): array
    {
        $statement = $this->pdo->query("SELECT LOWER(HEX(p.id)) profile_id,LOWER(HEX(p.organisation_id)) organisation_id,LOWER(HEX(p.user_id)) user_id,p.display_name,p.profession,p.bio,p.service_areas FROM professional_profiles p JOIN users u ON u.id=p.user_id AND u.status='active' JOIN organisations o ON o.id=p.organisation_id AND o.status='active' JOIN organisation_memberships m ON m.organisation_id=p.organisation_id AND m.user_id=p.user_id AND m.status='active' WHERE p.directory_visible=1 ORDER BY p.display_name,p.id");
        $results = [];
        foreach ($statement as $profile) {
            if ($profession !== null && $profession !== '' && strcasecmp((string)$profile['profession'], trim($profession)) !== 0) continue;
            $areas = json_decode((string)$profile['service_areas'], true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($areas)) $areas = [];
            if ($serviceArea !== null && $serviceArea !== '' && !$this->containsCaseInsensitive($areas, trim($serviceArea))) continue;
            $badge = $this->bestBadge((string)$profile['organisation_id'], (string)$profile['user_id']);
            $results[] = [
                'profileId'=>$profile['profile_id'],'organisationId'=>$profile['organisation_id'],'displayName'=>$profile['display_name'],
                'profession'=>$profile['profession'],'bio'=>$profile['bio'],'serviceAreas'=>$areas,
                'verificationGrade'=>$badge['grade'],'verificationKind'=>$badge['kind'],'registrationBody'=>$badge['registrationBody'],'registrationNumber'=>$badge['registrationNumber'],
            ];
        }
        return $results;
    }

    private function assertActiveMember(string $organisationId, string $userId): void
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active'");
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
        if ((int)$statement->fetchColumn() !== 1) throw new TrustException('membership_not_found');
    }

    private function assertReviewer(string $organisationId, string $userId): void
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM organisation_memberships WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND status='active' AND role IN ('owner','admin')");
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
        if ((int)$statement->fetchColumn() !== 1) throw new TrustException('review_permission_denied');
    }

    private function evidenceExists(string $organisationId, string $userId, string $fileId): bool
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM stored_files WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation_id) AND uploaded_by=UNHEX(:user_id) AND status='active'");
        $statement->execute(['id'=>$fileId,'organisation_id'=>$organisationId,'user_id'=>$userId]);
        return (int)$statement->fetchColumn() === 1;
    }

    /** @return array{grade:?string,kind:?string,registrationBody:?string,registrationNumber:?string} */
    private function bestBadge(string $organisationId, string $userId): array
    {
        $statement = $this->pdo->prepare("SELECT kind,grade,registration_body,registration_number,DATE_FORMAT(expires_on,'%Y-%m-%d') expires_on FROM verification_records WHERE organisation_id=UNHEX(:organisation_id) AND user_id=UNHEX(:user_id) AND grade<>'rejected' ORDER BY FIELD(grade,'externally_verified','manually_reviewed','document_uploaded','self_declared') ASC,created_at DESC");
        $statement->execute(['organisation_id'=>$organisationId,'user_id'=>$userId]);
        foreach ($statement as $row) {
            $grade = $this->effectiveGrade((string)$row['grade'], is_string($row['expires_on']) ? $row['expires_on'] : null);
            if ($grade === 'expired') continue;
            return ['grade'=>$grade,'kind'=>(string)$row['kind'],'registrationBody'=>is_string($row['registration_body'])?$row['registration_body']:null,'registrationNumber'=>is_string($row['registration_number'])?$row['registration_number']:null];
        }
        return ['grade'=>null,'kind'=>null,'registrationBody'=>null,'registrationNumber'=>null];
    }

    private function effectiveGrade(string $grade, ?string $expiresOn): string
    {
        return $expiresOn !== null && $expiresOn < (new DateTimeImmutable('today'))->format('Y-m-d') ? 'expired' : $grade;
    }

    /** @param list<mixed> $values */
    private function containsCaseInsensitive(array $values, string $needle): bool
    {
        foreach ($values as $value) if (is_string($value) && strcasecmp($value, $needle) === 0) return true;
        return false;
    }

    /** @param array<string,mixed> $input */
    private function requiredText(array $input, string $key, int $max): string
    {
        $value = $this->optionalText($input, $key, $max);
        if ($value === '') throw new TrustException('invalid_' . strtolower($key));
        return $value;
    }

    /** @param array<string,mixed> $input */
    private function optionalText(array $input, string $key, int $max): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) > $max) throw new TrustException('invalid_' . strtolower($key));
        return trim($value);
    }

    /** @param array<string,mixed> $input */
    private function nullableText(array $input, string $key, int $max): ?string
    {
        $value = $this->optionalText($input, $key, $max);
        return $value === '' ? null : $value;
    }

    private function optionalIdentifier(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value) || preg_match('/\A[a-f0-9]{32}\z/i', $value) !== 1) throw new TrustException('invalid_evidence_file_id');
        return strtolower($value);
    }

    private function date(mixed $value, bool $nullable): ?DateTimeImmutable
    {
        if (($value === null || $value === '') && $nullable) return null;
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
