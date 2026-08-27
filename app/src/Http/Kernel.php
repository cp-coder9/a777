<?php

declare(strict_types=1);

namespace Architex\Http;

use Closure;
use Throwable;

final readonly class Kernel
{
    /** @var Closure():string */
    private Closure $idGenerator;

    /** @param Closure():string|null $idGenerator */
    public function __construct(private Router $router, ?Closure $idGenerator = null)
    {
        $this->idGenerator = $idGenerator ?? self::generateUlid(...);
    }

    public function handle(Request $request): Response
    {
        $requestId = $this->requestId();

        try {
            $response = $this->router->dispatch($request)->withMeta(['requestId' => $requestId]);
        } catch (Throwable) {
            $response = Response::error(500, 'internal_error', 'The request could not be completed.', $requestId);
        }

        return $request->method() === 'HEAD' ? $response->withoutBody() : $response;
    }

    private function requestId(): string
    {
        try {
            $candidate = ($this->idGenerator)();
            if (self::isCurrentUlid($candidate)) {
                return $candidate;
            }
        } catch (Throwable) {
        }

        return self::fallbackUlid();
    }

    private static function generateUlid(): string
    {
        $milliseconds = (int) floor(microtime(true) * 1_000);
        return self::encodeUlid($milliseconds, random_bytes(10));
    }

    private static function fallbackUlid(): string
    {
        try {
            return self::generateUlid();
        } catch (Throwable) {
            return self::encodeUlid((int) floor(microtime(true) * 1_000), substr(hash('sha256', uniqid((string) mt_rand(), true), true), 0, 10));
        }
    }

    private static function isCurrentUlid(string $candidate): bool
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $candidate) !== 1) {
            return false;
        }

        $timestamp = 0;
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        foreach (str_split(substr($candidate, 0, 10)) as $character) {
            $timestamp = ($timestamp << 5) | strpos($alphabet, $character);
        }

        $now = (int) floor(microtime(true) * 1_000);
        return $timestamp >= $now - 60_000 && $timestamp <= $now + 60_000;
    }

    private static function encodeUlid(int $milliseconds, string $random): string
    {
        $bytes = pack('Nn', intdiv($milliseconds, 65_536), $milliseconds & 0xffff) . $random;
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $buffer = ord($bytes[0]) & 31;
        $bits = 5;
        $ulid = $alphabet[ord($bytes[0]) >> 5];

        foreach (str_split(substr($bytes, 1)) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $ulid .= $alphabet[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $ulid;
    }
}
