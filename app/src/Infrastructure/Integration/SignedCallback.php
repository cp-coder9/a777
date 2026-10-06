<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Integration;

final class SignedCallback
{
    /** @return array<string,mixed> */
    public static function verify(string $body, string $timestamp, string $signature, string $secret): array
    {
        if (strlen($secret)<32) throw new IntegrationException('integration_not_configured');
        if (strlen($body)>262144 || preg_match('/\A[0-9]{10}\z/D',$timestamp)!==1 || abs(time()-(int)$timestamp)>300
            || preg_match('/\A[a-f0-9]{64}\z/iD',$signature)!==1
            || !hash_equals(hash_hmac('sha256',$timestamp.'.'.$body,$secret),strtolower($signature))) {
            throw new IntegrationException('integration_callback_signature_invalid',401);
        }
        try { $payload=json_decode($body,true,32,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new IntegrationException('integration_callback_payload_invalid',422); }
        if (!is_array($payload) || array_is_list($payload)) throw new IntegrationException('integration_callback_payload_invalid',422);
        return $payload;
    }
}
