<?php

declare(strict_types=1);

namespace Architex\Infrastructure\Database;

use LogicException;
use PDOException;
use Throwable;

/**
 * Pure resource ledger for the guarded disposable verification database
 * provisioner (tools/v1-clone/provision-verification-db.php).
 *
 * The question this class answers
 *   Which resources may this invocation remove again? Exactly one kind: the
 *   resources whose own CREATE statement was acknowledged as successful by the
 *   server. MySQL DDL is not transactional and cannot be rolled back, so
 *   cleanup is compensating, and compensation that is not sure it owns a
 *   resource can delete a resource another concurrent creator owns. The ledger
 *   therefore keeps two separate states and never confuses them:
 *
 *     owned      a CREATE returned success. This invocation created it and may
 *                remove it again.
 *     uncertain  a CREATE was attempted and its outcome is unknown — a lost
 *                acknowledgement, a dropped connection, any failure that is not
 *                an acknowledged duplicate. It may or may not exist. It is
 *                NEVER a cleanup target, and no attempt is made to confirm it:
 *                an uncertain resource is reported for manual reconciliation.
 *
 * A resource is never owned before its CREATE is executed, and a failed or
 * unacknowledged CREATE never makes it owned. An explicit duplicate failure
 * (MySQL 1007 for an existing schema, 1396 for an existing account) means the
 * resource exists but belongs to someone else: it is abandoned, never adopted,
 * and never dropped.
 *
 * This class does no I/O. It records, classifies and reports; the provisioner
 * runs the statements.
 */
final class VerificationProvisioningLedger
{
    public const KIND_SCHEMA = 'schema';
    public const KIND_ACCOUNT = 'account';

    /** The only resource kinds this tool ever creates or removes. */
    public const KINDS = [self::KIND_SCHEMA, self::KIND_ACCOUNT];

    /** The CREATE failed with an acknowledged duplicate: the resource is not ours. */
    public const OUTCOME_DUPLICATE = 'duplicate';

    /** Anything else: the outcome is unknown and must be reconciled by a human. */
    public const OUTCOME_UNKNOWN = 'unknown';

    /** ER_DB_CREATE_EXISTS — the schema already exists, so it belongs to someone else. */
    public const ERROR_SCHEMA_EXISTS = 1007;

    /** ER_CANNOT_USER — CREATE USER failed, so the account belongs to someone else. */
    public const ERROR_ACCOUNT_EXISTS = 1396;

    /**
     * Every attempted resource, in attempt order.
     *
     * @var list<array{kind:string,name:string,owned:bool}>
     */
    private array $entries = [];

    /**
     * Record that a CREATE for this resource is about to be executed, i.e. that
     * its outcome is not yet known. Ownership is NOT granted here; only
     * confirm() grants it, and only after the statement was acknowledged.
     */
    public function attempt(string $kind, string $name): void
    {
        $this->assertKind($kind);
        if ($this->entryIndex($kind, $name) !== null) {
            // Already tracked. The first record stands: a confirmed resource is
            // never silently downgraded back to uncertain, and an uncertain
            // resource is never re-classified by a later attempt.
            return;
        }

        $this->entries[] = ['kind' => $kind, 'name' => $name, 'owned' => false];
    }

    /**
     * Record that the CREATE for this resource was acknowledged as successful.
     * This is the ONLY way a resource becomes owned, and therefore the only way
     * it can ever enter the cleanup order.
     *
     * @throws LogicException when the resource was never attempted.
     */
    public function confirm(string $kind, string $name): void
    {
        $index = $this->entryIndex($kind, $name);
        if ($index === null) {
            throw new LogicException('Only an attempted resource can be confirmed as created.');
        }

        $this->entries[$index]['owned'] = true;
    }

    /**
     * Abandon an attempt that lost a race: the resource exists but another
     * creator owns it. It leaves the ledger entirely, so it can never be
     * dropped by this invocation's compensation.
     *
     * @throws LogicException when the resource is owned: a resource this
     *   invocation created is never re-labelled as someone else's.
     */
    public function disown(string $kind, string $name): void
    {
        $index = $this->entryIndex($kind, $name);
        if ($index === null) {
            return;
        }
        if ($this->entries[$index]['owned']) {
            throw new LogicException('A resource this invocation created can never be disowned.');
        }

        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    /**
     * Forget an owned resource after its removal was confirmed. Until this is
     * called the resource stays owned, so a failed cleanup is retained in the
     * ledger and in the report rather than quietly assumed gone.
     *
     * @throws LogicException when the resource is not owned.
     */
    public function forget(string $kind, string $name): void
    {
        $index = $this->entryIndex($kind, $name);
        if ($index === null || !$this->entries[$index]['owned']) {
            throw new LogicException('Only a resource created by this invocation can be forgotten.');
        }

        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    /**
     * The resources this invocation definitely created, in creation order.
     *
     * @return list<array{kind:string,name:string}>
     */
    public function owned(): array
    {
        return array_values(array_map(
            static fn (array $entry): array => ['kind' => $entry['kind'], 'name' => $entry['name']],
            array_filter($this->entries, static fn (array $entry): bool => $entry['owned']),
        ));
    }

    /**
     * The owned resources in the order compensation must remove them: newest
     * first, so the account goes before the schemas it was granted on.
     *
     * @return list<array{kind:string,name:string}>
     */
    public function cleanupOrder(): array
    {
        return array_reverse($this->owned());
    }

    /**
     * The attempted resources whose outcome is unknown. These are reported for
     * manual reconciliation and are never cleanup targets.
     *
     * @return list<array{kind:string,name:string}>
     */
    public function uncertain(): array
    {
        return array_values(array_map(
            static fn (array $entry): array => ['kind' => $entry['kind'], 'name' => $entry['name']],
            array_filter($this->entries, static fn (array $entry): bool => !$entry['owned']),
        ));
    }

    public function ownsAnything(): bool
    {
        return $this->owned() !== [];
    }

    public function hasUncertainty(): bool
    {
        return $this->uncertain() !== [];
    }

    /** Whether anything at all was attempted, created or left unknown. */
    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Whether the outcome may be reported as compensated. True only when
     * nothing this invocation created is still present AND nothing is
     * uncertain: an uncertain resource makes the outcome uncompensated even
     * when every known resource was removed.
     *
     * @param list<array{kind:string,name:string}> $remaining the owned
     *   resources compensation could not remove.
     */
    public function compensated(array $remaining): bool
    {
        return $remaining === [] && !$this->hasUncertainty();
    }

    /**
     * Classify a failed CREATE. Only the duplicate vendor errors prove that the
     * existing resource is someone else's; everything else — including a lost
     * connection — leaves the outcome unknown and must not be treated as
     * "nothing was created".
     */
    public static function classifyFailure(Throwable $failure): string
    {
        $code = self::vendorCode($failure);
        if ($code === self::ERROR_SCHEMA_EXISTS || $code === self::ERROR_ACCOUNT_EXISTS) {
            return self::OUTCOME_DUPLICATE;
        }

        return self::OUTCOME_UNKNOWN;
    }

    private static function vendorCode(Throwable $failure): int
    {
        // PDO reports the MySQL vendor error as the second element of the
        // driver-specific errorInfo; its getCode() carries the SQLSTATE string
        // instead, which is not distinctive for these two errors.
        if ($failure instanceof PDOException) {
            $errorInfo = $failure->errorInfo;
            if (is_array($errorInfo) && array_key_exists(1, $errorInfo) && is_scalar($errorInfo[1])) {
                $code = (string) $errorInfo[1];
                if (preg_match('/^[0-9]+$/D', $code) === 1) {
                    return (int) $code;
                }
            }
        }

        $code = $failure->getCode();

        return is_int($code) ? $code : 0;
    }

    private function assertKind(string $kind): void
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new LogicException('The ledger only tracks schemas and accounts.');
        }
    }

    private function entryIndex(string $kind, string $name): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry['kind'] === $kind && $entry['name'] === $name) {
                return $index;
            }
        }

        return null;
    }
}
