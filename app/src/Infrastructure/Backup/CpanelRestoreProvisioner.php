<?php
declare(strict_types=1);
namespace Architex\Infrastructure\Backup;
use RuntimeException;

/** Fixed account HTTPS issuer. No injectable transport in the production factory. */
final class CpanelRestoreProvisioner
{
    private const ROOT = '/home/archite4/.a777-deploy';
    private const ORIGIN = 'https://cp73.domains.co.za:2083';
    private static ?\WeakMap $authenticated = null;
    private function __construct(private string $token, private ?\Closure $testTransport = null) {}
    private function __clone() {}
    public function __serialize(): array { throw new RuntimeException('Provider cannot be serialized.'); }
    public function __unserialize(array $data): void { throw new RuntimeException('Provider cannot be imported.'); }
    public function __debugInfo(): array { return ['account'=>'archite4']; }

    public static function fromAccountToken(): self
    {
        if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid')) throw new RuntimeException('Linux account context required.');
        $uid = posix_geteuid(); $owner = posix_getpwuid($uid);
        if (($owner['name'] ?? '') !== 'archite4' || ($owner['dir'] ?? '') !== '/home/archite4') throw new RuntimeException('Exact account owner required.');
        foreach (['/home','/home/archite4',self::ROOT] as $dir) {
            if (is_link($dir) || realpath($dir) !== $dir) throw new RuntimeException('Canonical private token root required.');
        }
        if (fileowner(self::ROOT) !== $uid || (fileperms(self::ROOT) & 0777) !== 0700) throw new RuntimeException('Private account token root required.');
        $path = self::ROOT . '/cpanel-token'; $stat = lstat($path); $handle = @fopen($path, 'rb');
        if (!is_array($stat) || !is_resource($handle)) throw new RuntimeException('Private token unavailable.');
        try {
            $opened = fstat($handle);
            if (($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600 || $stat['uid'] !== $uid || $stat['nlink'] !== 1 || $stat['dev'] !== $opened['dev'] || $stat['ino'] !== $opened['ino'] || $stat['mode'] !== $opened['mode'] || $stat['uid'] !== $opened['uid']) throw new RuntimeException('Private token identity mismatch.');
            $token = rtrim((string)stream_get_contents($handle, 4097), "\r\n");
            if (!preg_match('/\A[A-Za-z0-9_-]{16,4096}\z/', $token)) throw new RuntimeException('Invalid account token.');
        } finally { fclose($handle); }
        $self = new self($token);
        $self->home();
        self::$authenticated ??= new \WeakMap(); self::$authenticated[$self] = true;
        return $self;
    }
    public function assertAuthenticated(): void
    {
        if (self::$authenticated === null || !isset(self::$authenticated[$this])) throw new RuntimeException('Unissued authenticated provider.');
        $this->home();
    }
    private function home(): void
    {
        if (($this->request('Variables', 'get_user_information', ['name'=>'home'])['data']['home'] ?? null) !== '/home/archite4') throw new RuntimeException('Exact authenticated account home mismatch.');
    }
    private function request(string $module, string $function, array $arguments): array
    {
        if ($this->testTransport !== null) return self::decode(($this->testTransport)($module, $function, $arguments), 200);
        if (!function_exists('curl_init')) throw new RuntimeException('HTTPS curl unavailable.');
        $curl = curl_init(self::ORIGIN . '/execute/' . $module . '/' . $function);
        curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($arguments, '', '&', PHP_QUERY_RFC3986)]);
        $bytes = '';
        try {
            curl_setopt_array($curl, [CURLOPT_HTTPHEADER=>['Authorization: cpanel archite4:' . $this->token,'Accept: application/json'], CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_PROXY=>'', CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30, CURLOPT_WRITEFUNCTION=>static function($handle, string $chunk) use (&$bytes): int { if (strlen($bytes)+strlen($chunk)>1048576) return 0; $bytes.=$chunk; return strlen($chunk); }]);
            if (curl_exec($curl) === false) throw new RuntimeException('Authenticated HTTPS transport failed; retain receipt.');
            return self::decode($bytes, (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        } finally { curl_close($curl); }
    }
    private static function decode(string $bytes, int $status): array
    {
        if ($status !== 200 || strlen($bytes)>1048576) throw new RuntimeException('HTTPS response refused.');
        try { $response=json_decode($bytes,true,64,JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new RuntimeException('Invalid HTTPS UAPI response.'); }
        // Normalize only an unambiguous direct or CLI-style result envelope.
        if (!is_array($response)) throw new RuntimeException('Authenticated UAPI failure; retain receipt.');
        if (array_key_exists('result', $response)) {
            if (array_key_exists('status', $response) || !is_array($response['result'])) throw new RuntimeException('Ambiguous UAPI envelope.');
            $response = $response['result'];
        }
        if (($response['status']??null)!==1 || !array_key_exists('errors', $response) || !in_array($response['errors'], [null, []], true) || !array_key_exists('data',$response)) throw new RuntimeException('Authenticated UAPI failure; retain receipt.');
        return $response;
    }
    private function mysql(string $function, array $arguments=[]): array
    {
        $this->assertAuthenticated(); // exact Variables home before every inventory or mutation
        return $this->request('Mysql',$function,$arguments);
    }
    private static function inventory(array $response, string $field, string $value, int $count): void
    {
        $rows=$response['data']??null;
        if (!is_array($rows) || count(array_filter($rows,static fn($row)=>is_array($row)&&($row[$field]??null)===$value))!==$count) throw new RuntimeException('Exact provider inventory mismatch.');
    }
    private static function allPrivileges(mixed $data): bool
    {
        if (!is_array($data) || !array_is_list($data) || array_filter($data,static fn($v)=>!is_string($v))) return false;
        if ($data === ['ALL PRIVILEGES']) return true;
        // Official Mysql/set_privileges_on_database privilege enumeration, excluding shorthand ALL PRIVILEGES.
        $full=['ALTER','ALTER ROUTINE','CREATE','CREATE ROUTINE','CREATE TEMPORARY TABLES','CREATE VIEW','DELETE','DROP','EVENT','EXECUTE','INDEX','INSERT','LOCK TABLES','REFERENCES','SELECT','SHOW VIEW','TRIGGER','UPDATE'];
        sort($full); sort($data); return $data === $full;
    }
    /** Inventory-only rehearsal. No database DDL. */
    public function preflight(string $source, string $user): void
    {
        if ($source !== 'archite4_dev_rel1' || !preg_match('/\Aarchite4(?:_[A-Za-z0-9_]+)?\z/',$user)) throw new RuntimeException('Source account binding refused.');
        self::inventory($this->mysql('list_databases'),'database',$source,1);
        self::inventory($this->mysql('list_users'),'user',$user,1);
    }
    /** Creates only the generated owned namespace. Never drops or uses application PDO for DDL. */
    public function provision(string $name, string $source, string $user, array $binding): array
    {
        if (!preg_match('/\Aarchite4_ar_[a-f0-9]{16}_test\z/',$name) || $name===$source) throw new RuntimeException('Owned namespace required.');
        $this->preflight($source,$user);
        self::inventory($this->mysql('list_databases'),'database',$name,0);
        $receipt=['account'=>'archite4','target'=>$name,'source'=>$source,'user'=>$user,'binding'=>$binding,'phase'=>'intent'];
        $path=self::ROOT . '/restore-' . $name . '.json';
        $this->journal($path,$receipt,true);
        // On every failure the private intent/readback receipt remains. No automatic cleanup.
        $receipt['create']=$this->mysql('create_database',['name'=>$name]); $receipt['phase']='created'; $this->journal($path,$receipt);
        $receipt['phase']='grant-intent'; $this->journal($path,$receipt);
        $receipt['grant']=$this->mysql('set_privileges_on_database',['user'=>$user,'database'=>$name,'privileges'=>'ALL PRIVILEGES']); $receipt['phase']='granted'; $this->journal($path,$receipt);
        $receipt['inventory']=$this->mysql('list_databases'); self::inventory($receipt['inventory'],'database',$name,1);
        $receipt['users']=$this->mysql('list_users'); self::inventory($receipt['users'],'user',$user,1);
        $receipt['privileges']=$this->mysql('get_privileges_on_database',['user'=>$user,'database'=>$name]);
        if (!self::allPrivileges($receipt['privileges']['data'])) throw new RuntimeException('Full privilege readback refused; retain owned receipt.');
        $receipt['phase']='verified'; $this->journal($path,$receipt); return $receipt;
    }
    private function journal(string $path, array $receipt, bool $create=false): void
    {
        if ($this->testTransport !== null) return; // reflection-only synthetic protocol fixtures, never factory-issued
        if (is_link(self::ROOT) || realpath(self::ROOT) !== self::ROOT || fileowner(self::ROOT)!==posix_geteuid() || (fileperms(self::ROOT)&0777)!==0700) throw new RuntimeException('Private receipt root refused.');
        $old=umask(0077);
        try {
            if (!$create && (is_link($path) || !is_file($path) || fileowner($path)!==posix_geteuid() || (fileperms($path)&0777)!==0600)) throw new RuntimeException('Private receipt refused.');
            $handle=@fopen($path,$create?'xb':'ab');
            if (!is_resource($handle)) throw new RuntimeException('Private receipt unavailable.');
            try { $bytes=json_encode($receipt,JSON_THROW_ON_ERROR) . "\n"; if (fwrite($handle,$bytes)!==strlen($bytes) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('Private receipt persistence failed.'); } finally { fclose($handle); }
        } finally { umask($old); }
    }
}
