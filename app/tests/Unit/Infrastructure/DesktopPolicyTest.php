<?php
declare(strict_types=1);

namespace Architex\Tests\Unit\Infrastructure;

use Architex\Infrastructure\Integration\IntegrationException;
use Architex\Platform\RemoteDesktop\DesktopPolicy;
use PHPUnit\Framework\TestCase;

final class DesktopPolicyTest extends TestCase
{
    private function host(): array
    {
        return ['hostId'=>'fixture-host','platform'=>'windows','available'=>true,'signedAgent'=>true,'hostConsentVerified'=>true,'processBinding'=>true,'inputFiltering'=>true,'workspaceConfinement'=>true,'selectedAppOnly'=>true,'autoExpire'=>true,'policySha256'=>str_repeat('a',64),'apps'=>['cad']];
    }

    public function testSelectedAppHostMustEnforceEveryBoundary(): void
    {
        self::assertSame($this->host(),DesktopPolicy::host(['hosts'=>[$this->host()]],'fixture-host','cad'));
        foreach (['signedAgent','hostConsentVerified','processBinding','inputFiltering','workspaceConfinement','selectedAppOnly','autoExpire','available'] as $flag) {
            $host=$this->host(); $host[$flag]=false;
            try { DesktopPolicy::host(['hosts'=>[$host]],'fixture-host','cad'); self::fail($flag); }
            catch (IntegrationException $e) { self::assertSame('desktop_host_policy_unavailable',$e->reason); }
        }
    }

    public function testAppOutsideHostAllowlistDenied(): void
    {
        $this->expectException(IntegrationException::class);
        DesktopPolicy::host(['hosts'=>[$this->host()]],'fixture-host','shell');
    }

    public function testDesktopLifecycleCannotReopenTerminalSession(): void
    {
        self::assertTrue(DesktopPolicy::allows('ready','active'));
        self::assertTrue(DesktopPolicy::allows('active','closed'));
        foreach ([['closed','active'],['expired','ready'],['failed','active'],['active','ready'],['closing','active']] as [$from,$to]) self::assertFalse(DesktopPolicy::allows($from,$to));
    }

    public function testViewerTicketCannotExposeArbitraryOriginOrLongLivedToken(): void
    {
        $result=['viewerUrl'=>'https://broker.example/viewer','viewerToken'=>str_repeat('t',43),'expiresAt'=>time()+60];
        self::assertSame($result,DesktopPolicy::ticket($result,'https://broker.example/api',time()+900));
        foreach ([['viewerUrl'=>'http://broker.example/viewer'],['viewerUrl'=>'https://evil.example/viewer'],['expiresAt'=>time()+121],['viewerToken'=>'short'],['viewerUrl'=>'https://broker.example/viewer?token=leak']] as $change) {
            try { DesktopPolicy::ticket(array_replace($result,$change),'https://broker.example/api',time()+900); self::fail('invalid ticket accepted'); }
            catch (IntegrationException $e) { self::assertSame('desktop_viewer_ticket_invalid',$e->reason); }
        }
    }
}
