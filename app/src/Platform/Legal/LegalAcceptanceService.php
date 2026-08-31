<?php
declare(strict_types=1);

namespace Architex\Platform\Legal;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\IdentityException;
use JsonException;
use PDO;

final readonly class LegalAcceptanceService
{
    public const SIGNUP_NOTICE_TYPES = ['terms_of_service', 'privacy_policy', 'platform_disclaimer', 'signup_privacy_notice'];

    public function __construct(private PDO $pdo) {}

    /** @return list<array{noticeType:string,version:string,title:string,contentSha256:string,effectiveAt:string,href:string}> */
    public function currentSignupNotices(): array
    {
        $types = implode(',', array_fill(0, count(self::SIGNUP_NOTICE_TYPES), '?'));
        $statement = $this->pdo->prepare("SELECT notice_type,version,title,content_sha256,DATE_FORMAT(effective_at,'%Y-%m-%dT%H:%i:%s.%fZ') effective_at FROM legal_notices WHERE notice_type IN ($types) AND published_at<=UTC_TIMESTAMP(6) AND effective_at<=UTC_TIMESTAMP(6) AND retired_at IS NULL ORDER BY FIELD(notice_type,$types)");
        $statement->execute([...self::SIGNUP_NOTICE_TYPES, ...self::SIGNUP_NOTICE_TYPES]);
        $hrefs = [
            'terms_of_service'=>'/terms',
            'privacy_policy'=>'/privacy',
            'platform_disclaimer'=>'/disclaimer',
            'signup_privacy_notice'=>'/privacy#signup',
        ];
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $type = (string)$row['notice_type'];
            $result[] = [
                'noticeType'=>$type,
                'version'=>(string)$row['version'],
                'title'=>(string)$row['title'],
                'contentSha256'=>(string)$row['content_sha256'],
                'effectiveAt'=>(string)$row['effective_at'],
                'href'=>$hrefs[$type],
            ];
        }
        return $result;
    }

    /** @param array<string,string> $versions @param array<string,mixed> $deviceMetadata */
    public function recordRequiredSignupAcceptances(string $userId, string $organisationId, array $versions, ?string $ipAddress, array $deviceMetadata): void
    {
        if (array_keys($versions) !== self::SIGNUP_NOTICE_TYPES) {
            $sorted = $versions;
            ksort($sorted);
            $required = self::SIGNUP_NOTICE_TYPES;
            sort($required);
            if (array_keys($sorted) !== $required) {
                throw new IdentityException('legal_acceptance_required', 'All current signup notices must be accepted.');
            }
        }
        $lookup = $this->pdo->prepare("SELECT HEX(id) id FROM legal_notices WHERE notice_type=:type AND version=:version AND published_at<=UTC_TIMESTAMP(6) AND effective_at<=UTC_TIMESTAMP(6) AND retired_at IS NULL");
        $insert = $this->pdo->prepare('INSERT INTO legal_acceptances (id,legal_notice_id,user_id,organisation_id,ip_address,device_metadata) VALUES (UNHEX(:id),UNHEX(:notice_id),UNHEX(:user_id),UNHEX(:organisation_id),:ip_address,:device_metadata)');
        try {
            $metadata = $deviceMetadata === [] ? null : json_encode($deviceMetadata, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new IdentityException('invalid_device_metadata', 'Device metadata is invalid.');
        }
        $packedIp = $ipAddress === null ? null : inet_pton($ipAddress);
        foreach (self::SIGNUP_NOTICE_TYPES as $type) {
            $lookup->execute(['type' => $type, 'version' => $versions[$type]]);
            $noticeId = $lookup->fetchColumn();
            if (!is_string($noticeId)) {
                throw new IdentityException('legal_notice_not_current', 'A selected legal notice is not current.');
            }
            $insert->execute([
                'id' => Identifier::generate(), 'notice_id' => strtolower($noticeId), 'user_id' => $userId,
                'organisation_id' => $organisationId, 'ip_address' => $packedIp, 'device_metadata' => $metadata,
            ]);
        }
    }
}
