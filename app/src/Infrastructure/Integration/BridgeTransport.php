<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Integration;

interface BridgeTransport
{
    /** @param array<string,string> $headers @return array{status:int,body:string} */
    public function request(string $method, string $url, array $headers, string $body): array;
}
