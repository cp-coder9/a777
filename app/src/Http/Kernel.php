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
        $requestId = ($this->idGenerator)();

        try {
            return $this->router->dispatch($request)->withMeta(['requestId' => $requestId]);
        } catch (Throwable) {
            return Response::error(500, 'internal_error', 'The request could not be completed.', $requestId);
        }
    }

    private static function generateUlid(): string
    {
        $milliseconds = (int) floor(microtime(true) * 1_000);
        $bytes = pack('Nn', intdiv($milliseconds, 65_536), $milliseconds & 0xffff) . random_bytes(10);
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
