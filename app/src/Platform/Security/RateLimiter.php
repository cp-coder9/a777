<?php

declare(strict_types=1);

namespace Architex\Platform\Security;

use InvalidArgumentException;
use PDO;

final readonly class RateLimiter
{
    public function __construct(private PDO $pdo) {}

    public function consume(string $scope, string $key, int $limit, int $windowSeconds): RateLimitDecision
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]{0,79}$/D', $scope) !== 1) {
            throw new InvalidArgumentException('Rate-limit scope is invalid.');
        }
        if ($key === '' || strlen($key) > 1_000) {
            throw new InvalidArgumentException('Rate-limit key is invalid.');
        }
        if ($limit < 1 || $limit > 10_000 || $windowSeconds < 1 || $windowSeconds > 86_400) {
            throw new InvalidArgumentException('Rate-limit policy is invalid.');
        }

        $hash = hash('sha256', $key);
        $cutoff = sprintf('UTC_TIMESTAMP(6) - INTERVAL %d SECOND', $windowSeconds);
        $sql = 'INSERT INTO rate_limit_buckets (scope,key_hash,bucket_started_at,request_count,updated_at) '
            . 'VALUES (:scope,UNHEX(:key_hash),UTC_TIMESTAMP(6),1,UTC_TIMESTAMP(6)) '
            . 'ON DUPLICATE KEY UPDATE '
            . "request_count=IF(bucket_started_at<=$cutoff,1,request_count+1),"
            . "bucket_started_at=IF(bucket_started_at<=$cutoff,UTC_TIMESTAMP(6),bucket_started_at),"
            . 'updated_at=UTC_TIMESTAMP(6)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['scope' => $scope, 'key_hash' => $hash]);

        $read = $this->pdo->prepare('SELECT request_count,GREATEST(1,CEILING(:window_seconds - TIMESTAMPDIFF(MICROSECOND,bucket_started_at,UTC_TIMESTAMP(6))/1000000)) retry_after FROM rate_limit_buckets WHERE scope=:scope AND key_hash=UNHEX(:key_hash)');
        $read->bindValue('window_seconds', $windowSeconds, PDO::PARAM_INT);
        $read->bindValue('scope', $scope, PDO::PARAM_STR);
        $read->bindValue('key_hash', $hash, PDO::PARAM_STR);
        $read->execute();
        $row = $read->fetch();
        if (!is_array($row)) {
            throw new \RuntimeException('Rate-limit state could not be read.');
        }
        $count = (int) $row['request_count'];
        return new RateLimitDecision($count <= $limit, max(0, $limit - $count), $count <= $limit ? 0 : (int) $row['retry_after']);
    }
}
