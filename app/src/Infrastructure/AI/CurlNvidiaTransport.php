<?php

declare(strict_types=1);

namespace Architex\Infrastructure\AI;

final class CurlNvidiaTransport implements NvidiaTransport
{
    public function send(
        string $endpoint,
        array $headers,
        string $body,
        int $connectTimeoutSeconds,
        int $timeoutSeconds,
        int $maxResponseBytes,
    ): array {
        if (!extension_loaded('curl')) throw new NvidiaNemotronException('configuration_required');
        $handle = curl_init($endpoint);
        if ($handle === false) throw new NvidiaNemotronException('provider_unavailable');

        $responseBody = '';
        $responseHeaders = [];
        $tooLarge = false;
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'Architex-Wingman/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody, &$tooLarge, $maxResponseBytes): int {
                if (strlen($responseBody) + strlen($chunk) > $maxResponseBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ]);

        try {
            $success = curl_exec($handle);
            $errorNumber = curl_errno($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }

        if ($tooLarge) throw new NvidiaNemotronException('provider_response_invalid');
        if ($success === false) {
            throw new NvidiaNemotronException($errorNumber === CURLE_OPERATION_TIMEDOUT ? 'provider_timeout' : 'provider_unavailable');
        }
        return ['status' => $status, 'body' => $responseBody, 'headers' => $responseHeaders];
    }
}
