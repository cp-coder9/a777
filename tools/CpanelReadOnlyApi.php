<?php
declare(strict_types=1);

/** Fixed-account, read-only transport. Never used by restore issuance. */
final class CpanelReadOnlyApi
{
    public const TOKEN = '/home/archite4/.a777-deploy/cpanel-token';
    public const ORIGIN = 'https://cp73.domains.co.za:2083';
    private static function need(bool $ok, string $gate): void
    {
        if (!$ok) throw new RuntimeException($gate);
    }
    public static function validateTokenStat(array $stat, array $opened, int $owner): void
    {
        self::need(($stat['mode'] & 0170000) === 0100000 && ($stat['mode'] & 0777) === 0600
            && $stat['uid'] === $owner && $stat['nlink'] === 1
            && $stat['dev'] === $opened['dev'] && $stat['ino'] === $opened['ino']
            && $stat['mode'] === $opened['mode'] && $stat['uid'] === $opened['uid'], 'api_token_not_private');
    }
    private static function token(): string
    {
        self::need(PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid'), 'api_linux_identity_required');
        $identity = posix_getpwuid(posix_geteuid());
        self::need(is_array($identity) && $identity['name'] === 'archite4' && $identity['dir'] === '/home/archite4', 'api_operator_identity_mismatch');
        foreach (['/home', '/home/archite4', '/home/archite4/.a777-deploy'] as $directory) {
            self::need(!is_link($directory) && realpath($directory) === $directory, 'api_token_path_invalid');
        }
        $root = dirname(self::TOKEN);
        self::need(fileowner($root) === posix_geteuid() && (fileperms($root) & 0777) === 0700, 'api_token_root_not_private');
        clearstatcache(true, self::TOKEN);
        self::need(!is_link(self::TOKEN) && is_file(self::TOKEN) && realpath(self::TOKEN) === self::TOKEN, 'api_token_missing_or_path_invalid');
        $stat = lstat(self::TOKEN);
        $handle = @fopen(self::TOKEN, 'rb');
        self::need(is_array($stat) && is_resource($handle), 'api_token_unreadable');
        try {
            self::validateTokenStat($stat, fstat($handle), posix_geteuid());
            $token = stream_get_contents($handle, 4097);
            self::need(is_string($token) && strlen($token) <= 4096, 'api_token_invalid');
            $token = rtrim($token, "\r\n");
            self::need(preg_match('/\A[A-Za-z0-9_-]{16,4096}\z/', $token) === 1, 'api_token_invalid');
            return $token;
        } finally { fclose($handle); }
    }
    public static function decode(string $bytes, int $status): array
    {
        self::need($status === 200, 'api_http_failed');
        self::need(strlen($bytes) <= 1048576, 'api_response_too_large');
        try { $response = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR); }
        catch (Throwable) { throw new RuntimeException('api_response_invalid'); }
        self::need(is_array($response) && ($response['status'] ?? null) === 1 && empty($response['errors']) && is_array($response['data'] ?? null), 'api_uapi_failed');
        return $response['data'];
    }
    public static function requestSpec(string $module, string $function, string $token): array
    {
        self::need(in_array([$module, $function], [['Variables','get_user_information'], ['Mysql','list_databases'], ['Mysql','list_users']], true), 'api_readonly_function_required');
        return ['url'=>self::ORIGIN . '/execute/' . $module . '/' . $function . ($module === 'Variables' ? '?name=home' : ''),
            'headers'=>['Authorization: cpanel archite4:' . $token, 'Accept: application/json']];
    }
    private static function request(string $module, string $function, string $token): array
    {
        self::need(function_exists('curl_init'), 'api_curl_unavailable');
        $spec = self::requestSpec($module, $function, $token);
        $curl = curl_init($spec['url']);
        self::need($curl !== false, 'api_curl_unavailable');
        $bytes = ''; $tooLarge = false;
        try {
            curl_setopt_array($curl, [CURLOPT_HTTPHEADER=>$spec['headers'], CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_MAXREDIRS=>0, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30,
                CURLOPT_PROXY=>'', CURLOPT_WRITEFUNCTION=>static function($handle, string $chunk) use (&$bytes, &$tooLarge): int {
                    if (strlen($bytes) + strlen($chunk) > 1048576) { $tooLarge = true; return 0; }
                    $bytes .= $chunk; return strlen($chunk);
                }]);
            $ok = curl_exec($curl);
            self::need(!$tooLarge, 'api_response_too_large');
            self::need($ok !== false, 'api_transport_failed');
            return self::decode($bytes, (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        } finally { curl_close($curl); }
    }
    public static function inventory(string $module, string $function): array
    {
        self::need($module === 'Mysql' && in_array($function, ['list_databases','list_users'], true), 'api_readonly_function_required');
        $token = self::token();
        $identity = self::request('Variables', 'get_user_information', $token);
        self::need(($identity['home'] ?? null) === '/home/archite4', 'api_account_home_mismatch');
        return self::request($module, $function, $token);
    }
}
