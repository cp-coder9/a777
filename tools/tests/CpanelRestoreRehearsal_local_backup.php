<?php
declare(strict_types=1);
// Real local BackupService/ZIP/files, synthetic PDO rows; no DB/provider/permission proof.
$base=dirname(__DIR__,2);
foreach (['Platform/Files/LocalFileStorage','Infrastructure/Backup/BackupReport','Infrastructure/Backup/BackupService'] as $class) require $base.'/app/src/'.$class.'.php';
use Architex\Infrastructure\Backup\BackupService;
final class FixtureRows extends PDOStatement {
    private int $offset=0;
    public function __construct(private array $rows) {}
    public function execute(?array $params=null): bool { return true; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed { return $this->rows[$this->offset++]??false; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column=0): mixed { return $this->rows[0]??false; }
}
final class FixtureSource extends PDO {
    private bool $active=false;
    public function __construct(private array $file) {}
    public function exec(string $statement): int|false { return 0; }
    public function beginTransaction(): bool { return $this->active=true; }
    public function commit(): bool { $this->active=false; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function rollBack(): bool { $this->active=false; return true; }
    public function prepare(string $query,array $options=[]): PDOStatement|false { return new FixtureRows(['id']); }
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false {
        return new FixtureRows(match(true) {
            $query==='SELECT DATABASE()'=>['synthetic_source'],
            str_contains($query,'information_schema.tables')=>['records'],
            str_starts_with($query,'SELECT *')=>[['id'=>1,'value'=>'synthetic']],
            str_contains($query,"status='active'")=>[$this->file],
            str_contains($query,'schema_migrations')=>['0001_fixture'],
            default=>throw new RuntimeException('Unexpected fixture query.')
        });
    }
}
$root=(string)getenv('TMPDIR').'/local-backup-'.bin2hex(random_bytes(8));
if (!is_dir(dirname($root))) throw new RuntimeException('Explicit scratch required.');
mkdir($root,0700); mkdir($root.'/source',0700);
$org=str_repeat('a',32); $name=str_repeat('b',48); $dir=$root.'/source/aa/'.$org; mkdir($dir,0700,true);
$path=$dir.'/'.$name; $bytes="synthetic private file\0\xff"; file_put_contents($path,$bytes); chmod($path,0600);
$password=bin2hex(random_bytes(32)); $key=base64_encode(random_bytes(32)); $n=0;
function verify(bool $v): void { global $n; if (!$v) throw new RuntimeException('Local backup assertion failed.'); $n++; }
try {
    $source=new FixtureSource(['organisation_id'=>$org,'storage_name'=>$name,'sha256'=>hash('sha256',$bytes),'byte_size'=>strlen($bytes)]);
    $report=(new BackupService($source,$root.'/source',$password,$key))->create($root.'/source.axbackup');
    $zip=new ZipArchive(); verify($zip->open($report->path)===true); $zip->setPassword($password);
    try {
        $manifest=json_decode($zip->getFromName('manifest.json'),true,64,JSON_THROW_ON_ERROR);
        $entry='files/'.$org.'/'.$name;
        verify($manifest['entries'][$entry]===['sha256'=>hash('sha256',$bytes),'bytes'=>strlen($bytes)]);
        verify($zip->getFromName($entry)===$bytes);
        verify($manifest['files'][0]['sha256']===hash_file('sha256',$path));
        verify($report->fileCount===1 && $report->rowCount===1);
        verify(file_get_contents($path)===$bytes);
    } finally { $zip->close(); }
} finally {
    if (is_file($root.'/source.axbackup')) unlink($root.'/source.axbackup');
    unlink($path); rmdir($dir); rmdir(dirname($dir)); rmdir($root.'/source'); rmdir($root);
}
printf("Local BackupService synthetic PDO / real encrypted ZIP assertions: %d passed; no native DB/provider/permission proof.\n",$n);
