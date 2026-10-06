<?php
declare(strict_types=1);

namespace Architex\Platform\RemoteDesktop;

use Architex\Infrastructure\Integration\IntegrationException;

final class DesktopPolicy
{
    public static function host(array $capabilities,string $hostId,string $appId): array
    {
        $hosts=$capabilities['hosts'] ?? null;
        if (!is_array($hosts) || count($hosts)>100) throw new IntegrationException('desktop_host_policy_unavailable');
        foreach ($hosts as $host) {
            if (!is_array($host) || ($host['hostId'] ?? null)!==$hostId) continue;
            foreach (['available','signedAgent','hostConsentVerified','processBinding','inputFiltering','workspaceConfinement','selectedAppOnly','autoExpire'] as $flag) {
                if (($host[$flag] ?? false)!==true) throw new IntegrationException('desktop_host_policy_unavailable');
            }
            if (($host['platform'] ?? null)!=='windows' || !is_array($host['apps'] ?? null) || !in_array($appId,$host['apps'],true)
                || !is_string($host['policySha256'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/D',$host['policySha256'])!==1) throw new IntegrationException('desktop_host_policy_unavailable');
            return $host;
        }
        throw new IntegrationException('desktop_host_policy_unavailable');
    }

    public static function allows(string $current,string $next): bool
    {
        if (!in_array($next,['ready','active','closed','failed','expired'],true)) return false;
        if ($current===$next) return true;
        return in_array($next,match ($current) {
            'requested'=>['ready','failed','expired','closed'],
            'ready'=>['active','closed','failed','expired'],
            'active'=>['closed','failed','expired'],
            'closing'=>['closed','failed','expired'],default=>[],
        },true);
    }

    /** Returns only ephemeral viewer credentials; no URL query token or broker secrets. */
    public static function ticket(array $result,string $brokerUrl,int $sessionExpiry): array
    {
        $url=$result['viewerUrl'] ?? null; $token=$result['viewerToken'] ?? null; $expiry=$result['expiresAt'] ?? null;
        $parts=is_string($url) ? parse_url($url) : false; $broker=parse_url($brokerUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '')!=='https' || ($parts['host'] ?? '')!==($broker['host'] ?? '')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port']!==443) || preg_match('/[\x00-\x20\x7f\\\\]/',(string)$url)===1
            || !is_string($token) || preg_match('/\A[A-Za-z0-9_-]{43,2048}\z/D',$token)!==1
            || !is_int($expiry) || $expiry<=time() || $expiry>time()+120 || $expiry>$sessionExpiry) throw new IntegrationException('desktop_viewer_ticket_invalid',502);
        return ['viewerUrl'=>$url,'viewerToken'=>$token,'expiresAt'=>$expiry];
    }
}
