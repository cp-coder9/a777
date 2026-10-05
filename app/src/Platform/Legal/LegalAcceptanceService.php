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
        $hrefs = [
            'terms_of_service'=>'/terms',
            'privacy_policy'=>'/privacy',
            'platform_disclaimer'=>'/disclaimer',
            'signup_privacy_notice'=>'/privacy#signup',
        ];
        $result = [];
        foreach ($this->currentSignupRows() as $row) {
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

    /** @return list<array<string,mixed>> */
    private function currentSignupRows(): array
    {
        $types = implode(',', array_fill(0, count(self::SIGNUP_NOTICE_TYPES), '?'));
        $statement = $this->pdo->prepare("SELECT HEX(n.id) id,n.notice_type,n.version,n.title,n.content_sha256,DATE_FORMAT(n.effective_at,'%Y-%m-%dT%H:%i:%s.%fZ') effective_at
            FROM legal_notices n
            WHERE n.notice_type IN ($types) AND n.published_at<=UTC_TIMESTAMP(6) AND n.effective_at<=UTC_TIMESTAMP(6) AND n.retired_at IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM legal_notices newer
                WHERE newer.notice_type=n.notice_type AND newer.published_at<=UTC_TIMESTAMP(6) AND newer.effective_at<=UTC_TIMESTAMP(6) AND newer.retired_at IS NULL
                AND (newer.effective_at,newer.published_at,newer.created_at,newer.id) > (n.effective_at,n.published_at,n.created_at,n.id)
            ) ORDER BY FIELD(n.notice_type,$types)");
        $statement->execute([...self::SIGNUP_NOTICE_TYPES, ...self::SIGNUP_NOTICE_TYPES]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
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
        $current = array_column($this->currentSignupRows(), null, 'notice_type');
        // Validate the complete selection before recording any acceptance.
        foreach (self::SIGNUP_NOTICE_TYPES as $type) {
            if (!isset($current[$type]) || $current[$type]['version'] !== $versions[$type]) {
                throw new IdentityException('legal_notice_not_current', 'A selected legal notice is not current.');
            }
        }
        $insert = $this->pdo->prepare('INSERT INTO legal_acceptances (id,legal_notice_id,user_id,organisation_id,ip_address,device_metadata) VALUES (UNHEX(:id),UNHEX(:notice_id),UNHEX(:user_id),UNHEX(:organisation_id),:ip_address,:device_metadata)');
        try {
            $metadata = $deviceMetadata === [] ? null : json_encode($deviceMetadata, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new IdentityException('invalid_device_metadata', 'Device metadata is invalid.');
        }
        $packedIp = $ipAddress === null ? null : inet_pton($ipAddress);
        foreach (self::SIGNUP_NOTICE_TYPES as $type) {
            $noticeId = (string)$current[$type]['id'];
            $insert->execute([
                'id' => Identifier::generate(), 'notice_id' => strtolower($noticeId), 'user_id' => $userId,
                'organisation_id' => $organisationId, 'ip_address' => $packedIp, 'device_metadata' => $metadata,
            ]);
        }
    }
}
