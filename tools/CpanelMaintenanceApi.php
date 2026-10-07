<?php
declare(strict_types=1);

/** Fixed-account maintenance UAPI. No CLI credentials; no restore-service integration. */
final class CpanelMaintenanceApi
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
    public static function decode(string $bytes, int $status): mixed
    {
        self::need($status === 200, 'api_http_failed');
        self::need(strlen($bytes) <= 1048576, 'api_response_too_large');
        try {
            $typed = json_decode($bytes, false, 64, JSON_THROW_ON_ERROR);
            self::need($typed instanceof stdClass, 'api_uapi_failed');
            if (property_exists($typed, 'result')) {
                self::need(!property_exists($typed, 'status') && !property_exists($typed, 'errors') && !property_exists($typed, 'data') && $typed->result instanceof stdClass, 'api_uapi_failed');
                $typed = $typed->result;
            }
            self::need(property_exists($typed, 'errors') && ($typed->errors === null || $typed->errors === []), 'api_uapi_failed');
            $response = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        }
        catch (Throwable) { throw new RuntimeException('api_response_invalid'); }
        self::need(is_array($response), 'api_uapi_failed');
        if (array_key_exists('result', $response)) {
            self::need(!array_key_exists('status', $response) && !array_key_exists('errors', $response) && !array_key_exists('data', $response) && is_array($response['result']), 'api_uapi_failed');
            $response = $response['result'];
        }
        self::need(($response['status'] ?? null) === 1 && array_key_exists('errors', $response)
            && ($response['errors'] === null || $response['errors'] === []) && array_key_exists('data', $response), 'api_uapi_failed');
        return $response['data'];
    }
    public static function requestSpec(string $module, string $function, string $token): array
    {
        self::need(in_array([$module, $function], [['DomainInfo','domains_data'], ['Variables','get_user_information'], ['Mysql','list_databases'], ['Mysql','list_users'], ['Mysql','get_privileges_on_database'], ['Mysql','revoke_access_to_database'], ['Mysql','set_privileges_on_database'], ['Mysql','create_database'], ['Mysql','create_user']], true), 'api_function_refused');
        return ['url'=>self::ORIGIN . '/execute/' . $module . '/' . $function,
            'headers'=>['Authorization: cpanel archite4:' . $token, 'Accept: application/json']];
    }
    private static function request(string $module, string $function, string $token, array $parameters = []): mixed
    {
        self::need(function_exists('curl_init'), 'api_curl_unavailable');
        $spec = self::requestSpec($module, $function, $token);
        $curl = curl_init($spec['url']);
        self::need($curl !== false, 'api_curl_unavailable');
        $bytes = ''; $tooLarge = false;
        try {
            curl_setopt_array($curl, [CURLOPT_HTTPHEADER=>$spec['headers'], CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($parameters, '', '&', PHP_QUERY_RFC3986), CURLOPT_FOLLOWLOCATION=>false,
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
    private static function authenticated(string $operation, array $args): mixed
    {
        $token = self::token();
        $identity = self::request('Variables', 'get_user_information', $token, ['name'=>'home']);
        self::need(is_array($identity) && ($identity['home'] ?? null) === '/home/archite4', 'api_account_home_mismatch');
        return self::request('Mysql', $operation, $token, $args);
    }
    public static function domains(): array
    {
        $token=self::token();
        $identity=self::request('Variables','get_user_information',$token,['name'=>'home']);
        self::need(is_array($identity) && ($identity['home']??null)==='/home/archite4','api_account_home_mismatch');
        $data=self::request('DomainInfo','domains_data',$token,['format'=>'hash']);
        self::need(is_array($data),'api_inventory_invalid');
        return $data;
    }
    public static function inventory(string $operation): array
    {
        self::need(in_array($operation, ['list_databases','list_users'], true), 'api_inventory_refused');
        $data = self::authenticated($operation, []);
        self::need(is_array($data), 'api_inventory_invalid');
        return $data;
    }
    public static function target(string $operation, array $args): array
    {
        self::need(in_array($operation, ['get_privileges_on_database','revoke_access_to_database','set_privileges_on_database'], true), 'api_target_operation_refused');
        self::need(($args['database'] ?? null) === 'archite4_dev_rel1', 'api_target_refused');
        $user = $args['user'] ?? null;
        self::need(is_string($user) && preg_match('/^archite4_[A-Za-z0-9_]+$/D', $user) === 1, 'api_user_refused');
        $users = self::inventory('list_users');
        $matches = array_filter($users, static fn($row) => is_array($row) && ($row['user'] ?? null) === $user);
        self::need(count($matches) === 1, 'api_user_not_in_authenticated_inventory');
        $expected = $operation === 'set_privileges_on_database' ? ['database','privileges','user'] : ['database','user'];
        $keys = array_keys($args); sort($keys); self::need($keys === $expected, 'api_parameters_refused');
        if ($operation === 'set_privileges_on_database') self::need(is_string($args['privileges']) && preg_match('/^[A-Z][A-Z ,]*$/D', $args['privileges']) === 1, 'api_privileges_refused');
        return ['status'=>1, 'errors'=>null, 'data'=>self::authenticated($operation, $args)];
    }
    public static function operator(string $operation, array $args): mixed
    {
        self::need(in_array($operation, ['create_user','get_privileges_on_database','set_privileges_on_database','revoke_access_to_database'], true), 'api_operator_operation_refused');
        $name=$args[$operation==='create_user'?'name':'user']??null;
        self::need(is_string($name) && preg_match('/\Aarchite4_nd_[a-f0-9]{16}\z/',$name)===1,'api_operator_namespace_refused');
        $keys=array_keys($args); sort($keys);
        $expected=match($operation) {'create_user'=>['name','password'],'set_privileges_on_database'=>['database','privileges','user'],default=>['database','user']};
        self::need($keys===$expected,'api_operator_parameters_refused');
        if ($operation==='create_user') self::need(is_string($args['password']) && preg_match('/\A[a-f0-9]{64}\z/',$args['password'])===1,'api_operator_password_refused');
        else self::need($args['database']==='archite4_dev_rel1','api_operator_database_refused');
        if ($operation==='set_privileges_on_database') self::need($args['privileges']==='ALL PRIVILEGES','api_operator_privileges_refused');
        return self::authenticated($operation,$args);
    }
    public static function createOwnedRestoreDatabase(string $name): void
    {
        self::need(preg_match('/^archite4_restore_[a-f0-9]{16}$/D', $name) === 1, 'api_restore_namespace_refused');
        foreach (self::inventory('list_databases') as $row) self::need(!is_array($row) || ($row['database'] ?? null) !== $name, 'api_restore_already_exists');
        self::authenticated('create_database', ['name'=>$name]);
        $matches = array_filter(self::inventory('list_databases'), static fn($row) => is_array($row) && ($row['database'] ?? null) === $name);
        self::need(count($matches) === 1, 'api_restore_create_readback_failed');
    }
}
