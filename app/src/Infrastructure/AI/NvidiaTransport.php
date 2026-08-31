<?php

declare(strict_types=1);

namespace Architex\Infrastructure\AI;

interface NvidiaTransport
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,headers:array<string,string>}
     */
    public function send(
        string $endpoint,
        array $headers,
        string $body,
        int $connectTimeoutSeconds,
        int $timeoutSeconds,
        int $maxResponseBytes,
    ): array;
}
