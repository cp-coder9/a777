<?php
declare(strict_types=1);
namespace Architex\Tests\Release;

use Architex\Infrastructure\Deployment\ReleaseBuilder;
use Architex\Infrastructure\Deployment\ReleaseVerifier;
use PHPUnit\Framework\TestCase;

final class ReleaseSourceSafetyTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        $this->root=sys_get_temp_dir().'/architex-release-safety-'.bin2hex(random_bytes(6));
        foreach(['public','src','views','database','cron','config','bin'] as $directory)mkdir($this->root.'/app/'.$directory,0700,true);
        file_put_contents($this->root.'/composer.json',json_encode(['require'=>[],'autoload'=>['psr-4'=>['Architex\\'=>'app/src/']]],JSON_THROW_ON_ERROR));
        file_put_contents($this->root.'/composer.lock','{}');
        file_put_contents($this->root.'/app/bootstrap.php','<?php');
        file_put_contents($this->root.'/app/public/index.php','<?php echo "fixture";');
        file_put_contents($this->root.'/app/config/runtime.example.php','<?php return [];');
        file_put_contents($this->root.'/.gitignore',"app/config/runtime.php\noutput/\n");
        $this->git(['init','--quiet']);$this->git(['add','.']);$this->git(['-c','user.name=Release Test','-c','user.email=release@example.invalid','commit','--quiet','-m','fixture']);
    }
    protected function tearDown(): void
    {
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $item){if($item->isFile()){@chmod($item->getPathname(),0600);unlink($item->getPathname());}else rmdir($item->getPathname());}rmdir($this->root);
    }
    private function git(array $args): void
    {
        $p=proc_open(['git',...$args],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,$this->root);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);self::assertSame(0,proc_close($p));
    }
    private function builder(): ReleaseBuilder
    {
        return new ReleaseBuilder($this->root,$this->root.'/output',static function(string $release):void{mkdir($release.'/vendor',0700,true);file_put_contents($release.'/vendor/autoload.php','<?php return true;');});
    }
    public function testTargetRuntimeConfigNeverEntersCleanArtifact(): void
    {
        file_put_contents($this->root.'/app/config/runtime.php',"<?php return ['DB_PASSWORD'=>'synthetic-test-value'];");
        $release=$this->builder()->build('clean');
        self::assertFileDoesNotExist($release.'/config/runtime.php');self::assertFileExists($release.'/config/runtime.example.php');
        $meta=json_decode(file_get_contents($release.'/release.json'),true);self::assertFalse($meta['sourceDirty']);self::assertSame($meta['sourceBaseCommit'],$meta['sourceCommit']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/',$meta['sourceSnapshotSha256']);
    }
    public function testProductionRejectsDirtyTree(): void
    {
        file_put_contents($this->root.'/app/public/index.php','<?php echo "changed";');
        $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('clean');$this->builder()->build('dirty');
    }
    public function testExplicitStagingSnapshotCannotClaimExactHead(): void
    {
        file_put_contents($this->root.'/app/public/index.php','<?php echo "changed";');
        $first=$this->builder()->build('first','staging',true);$a=json_decode(file_get_contents($first.'/release.json'),true);
        self::assertTrue($a['sourceDirty']);self::assertNull($a['sourceCommit']);self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/',$a['sourceBaseCommit']);
        file_put_contents($this->root.'/app/public/index.php','<?php echo "changed again";');
        $second=$this->builder()->build('second','staging',true);$b=json_decode(file_get_contents($second.'/release.json'),true);
        self::assertNotSame($a['sourceSnapshotSha256'],$b['sourceSnapshotSha256']);
    }
    public function testDirtyOverrideCannotBeUsedForProduction(): void
    {
        $this->expectException(\InvalidArgumentException::class);$this->builder()->build('override','production',true);
    }
    public function testVerifierRejectsTargetConfigEvenWithMatchingManifest(): void
    {
        $release=$this->builder()->build('verify');$path=$release.'/config/runtime.php';file_put_contents($path,"<?php return ['DB_PASSWORD'=>'synthetic-test-value'];");
        $manifest=json_decode(file_get_contents($release.'/release-manifest.json'),true);$manifest['files']['config/runtime.php']=['sha256'=>hash_file('sha256',$path),'bytes'=>filesize($path)];file_put_contents($release.'/release-manifest.json',json_encode($manifest));
        $result=(new ReleaseVerifier())->verify($release);self::assertFalse($result->valid);self::assertContains('forbidden_path:config/runtime.php',$result->errors);
    }
}
