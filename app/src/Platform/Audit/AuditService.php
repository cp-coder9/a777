<?php
declare(strict_types=1);
namespace Architex\Platform\Audit;

use Architex\Platform\Identity\Identifier;
use JsonException;
use PDO;

final readonly class AuditService
{
    private const SECRET_KEYS = ['password','password_hash','token','token_hash','secret','authorization','cookie','csrf'];
    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed> $metadata */
    public function append(?string $organisationId, ?string $actorUserId, string $action, string $subjectType, string $subjectId, array $metadata = []): void
    {
        $statement = $this->pdo->prepare('INSERT INTO audit_events(id,organisation_id,actor_user_id,action,subject_type,subject_id,metadata) VALUES(UNHEX(:id),UNHEX(:organisation_id),UNHEX(:actor_user_id),:action,:subject_type,UNHEX(:subject_id),:metadata)');
        $statement->execute([
            'id' => Identifier::generate(), 'organisation_id' => $organisationId, 'actor_user_id' => $actorUserId,
            'action' => substr($action, 0, 100), 'subject_type' => substr($subjectType, 0, 80), 'subject_id' => $subjectId,
            'metadata' => json_encode($this->redact($metadata), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::SECRET_KEYS, true)) return '[REDACTED]';
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $childKey => $child) $clean[$childKey] = $this->redact($child, is_string($childKey) ? $childKey : null);
            return $clean;
        }
        return is_string($value) && strlen($value) > 4000 ? substr($value, 0, 4000) : $value;
    }
}
