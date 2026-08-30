<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DedicatedAppsControlCentreTest extends TestCase
{
    public function testV1ControlCentreShowsTruthfulDedicatedBoundaries():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        foreach(['Dedicated Apps','Native & service integrations','/dedicated-apps','integration required','AI may execute: no','Offline conflicts','Selected-app-only remote desktop']as$token)self::assertStringContainsString($token,$shell);
        self::assertStringContainsString('const oneTimeCredential=result.credentialToken;await hydrateDedicatedApps();const credential=document.getElementById', $shell);
        self::assertStringContainsString('escapeDedicated(oneTimeCredential)', $shell);
        $manifest=(string)file_get_contents(dirname(__DIR__,2).'/public/manifest.webmanifest');
        self::assertStringContainsString('Architex Mobile Companion',$manifest);
        $worker=(string)file_get_contents(dirname(__DIR__,2).'/public/service-worker.js');
        self::assertStringContainsString("request.url.includes('/api/')",$worker);
        self::assertStringContainsString("return fetch(request)",$worker);
    }
}
