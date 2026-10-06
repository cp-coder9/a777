<?php
declare(strict_types=1);
namespace Architex\Tests\Release;
use Architex\Infrastructure\Backup\AccountOwnedRestoreTarget;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
final class AccountOwnedRestoreTargetTest extends TestCase
{
    private const NAME='archite4_ar_0123456789abcdef_test';
    public function testUniqueExactAccountInventory(): void
    {
        $method=(new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('assertInventory');
        $method->invoke(null,['result'=>['data'=>[['database'=>self::NAME]]]],self::NAME,true);
        $method->invoke(null,['result'=>['data'=>[]]],self::NAME,false);
        self::assertTrue(true);
    }
    public function testDuplicateInventoryRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('assertInventory')->invoke(null,['result'=>['data'=>[['database'=>self::NAME],['database'=>self::NAME]]]],self::NAME,true);
    }
    public function testWrongDatabaseInventoryRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('assertInventory')->invoke(null,['result'=>['data'=>[['database'=>'archite4_dev_rel1']]]],self::NAME,true);
    }
    public function testUnknownInventoryIsNotAbsence(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('assertInventory')->invoke(null,['result'=>['data'=>null]],self::NAME,false);
    }
    public function testCapabilitiesCannotBeSerialized(): void
    {
        $this->expectException(RuntimeException::class);
        serialize((new ReflectionClass(AccountOwnedRestoreTarget::class))->newInstanceWithoutConstructor());
    }
    public function testCapabilitiesCannotBeImported(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->newInstanceWithoutConstructor()->__unserialize(['account'=>'archite4','target'=>self::NAME]);
    }
    public function testUapiSuccessEnvelope(): void
    {
        $response=['result'=>['status'=>1,'errors'=>null,'data'=>null]];
        self::assertSame($response,(new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('validatedResponse')->invoke(null,json_encode($response),0));
    }
    public function testUapiFailedExitCannotIssue(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('validatedResponse')->invoke(null,'{"result":{"status":1}}',1);
    }
    public function testUapiErrorCannotIssue(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('validatedResponse')->invoke(null,'{"result":{"status":1,"errors":["denied"]}}',0);
    }
    public function testUapiBooleanStatusCannotIssue(): void
    {
        $this->expectException(RuntimeException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('validatedResponse')->invoke(null,'{"result":{"status":true}}',0);
    }
    public function testUapiMalformedOutputCannotIssue(): void
    {
        $this->expectException(\JsonException::class);
        (new ReflectionClass(AccountOwnedRestoreTarget::class))->getMethod('validatedResponse')->invoke(null,'not authenticated JSON',0);
    }
    public function testCapabilitiesCannotBeConstructedOrCloned(): void
    {
        $class=new ReflectionClass(AccountOwnedRestoreTarget::class);
        self::assertTrue($class->getConstructor()->isPrivate());
        self::assertTrue($class->getMethod('__clone')->isPrivate());
        self::assertTrue($class->isFinal());
    }
}
