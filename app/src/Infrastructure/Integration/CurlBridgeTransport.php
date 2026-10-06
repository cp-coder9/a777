<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Integration;

final class CurlBridgeTransport implements BridgeTransport
{
    public function request(string $method, string $url, array $headers, string $body): array
    {
        $host = (string)parse_url($url, PHP_URL_HOST);
        $addresses = gethostbynamel($host);
        if ($addresses === false || $addresses === []) throw new IntegrationException('integration_transport_unknown', 502);
        // Validate every answer, then pin the connection to avoid DNS rebinding.
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new IntegrationException('integration_endpoint_denied');
            }
        }
        $handle = curl_init($url);
        if ($handle === false) throw new IntegrationException('integration_transport_unknown', 502);
        $response = '';
        $lines = [];
        foreach ($headers as $key => $value) {
            if (preg_match('/\A[A-Za-z0-9-]+\z/D', $key) !== 1 || preg_match('/[\r\n\x00]/', $value) === 1) {
                throw new IntegrationException('integration_request_invalid', 422);
            }
            $lines[] = $key.': '.$value;
        }
        try {
            $options = [
                CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$lines,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0, CURLOPT_PROXY=>'',
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>20, CURLOPT_NOSIGNAL=>true,
                CURLOPT_RESOLVE=>[$host.':443:'.$addresses[0]],
                CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use (&$response): int {
                    if (strlen($response) + strlen($chunk) > 262144) return 0;
                    $response .= $chunk;
                    return strlen($chunk);
                },
            ];
            if ($method !== 'GET') $options[CURLOPT_POSTFIELDS] = $body;
            if (!curl_setopt_array($handle, $options) || curl_exec($handle) === false) {
                throw new IntegrationException('integration_transport_unknown', 502);
            }
            return ['status'=>(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE),'body'=>$response];
        } finally { curl_close($handle); }
    }
}
