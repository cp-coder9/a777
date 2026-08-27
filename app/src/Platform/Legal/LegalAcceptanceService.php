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
