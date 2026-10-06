<?php
declare(strict_types=1);

namespace Architex\Infrastructure\Integration;

final readonly class BridgeClient
{
    public function __construct(private BridgeConfig $config, private BridgeTransport $transport = new CurlBridgeTransport()) {}

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function call(string $method, string $path, array $payload, ?string $idempotencyKey = null): array
    {
        if (!in_array($method, ['GET','POST'], true) || preg_match('#\A/[a-z0-9/-]+\z#D', $path) !== 1) {
            throw new IntegrationException('integration_request_invalid',422);
        }
        $headers = ['Authorization'=>'Bearer '.$this->config->token, 'Accept'=>'application/json', 'Content-Type'=>'application/json'];
        if ($idempotencyKey !== null) {
            if (preg_match('/\A[a-z0-9-]{1,120}\z/D', $idempotencyKey) !== 1) throw new IntegrationException('integration_request_invalid',422);
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $body = json_encode(['contract'=>'architex.integration.v1']+$payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $url = $this->config->url.$path;
        if ($method === 'GET') $url .= '?'.http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $response = $this->transport->request($method,$url,$headers,$method === 'GET' ? '' : $body);
        if ($response['status'] < 200 || $response['status'] > 299) throw new IntegrationException('integration_transport_unknown',502);
        try { $data=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new IntegrationException('integration_response_invalid',502); }
        if (!is_array($data) || ($data['contract'] ?? '') !== 'architex.integration.v1') throw new IntegrationException('integration_response_invalid',502);
        foreach (['organisationId','projectId','actorId','providerId','releaseRequestId','certificateRevisionId','sessionId','hostId','appId','bookingReference','brokerReference','policySha256','amountCents','currency'] as $key) {
            if (array_key_exists($key,$payload) && ($data[$key] ?? null) !== $payload[$key]) throw new IntegrationException('integration_response_scope_mismatch',502);
        }
        return $data;
    }
}
