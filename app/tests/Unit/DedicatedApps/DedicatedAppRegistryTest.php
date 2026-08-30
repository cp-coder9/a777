<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\DedicatedApps;

use Architex\Platform\DedicatedApps\DedicatedAppRegistry;
use PHPUnit\Framework\TestCase;

final class DedicatedAppRegistryTest extends TestCase
{
    public function testAllTwelveDedicatedCapabilitiesAreOrderedAndGoverned():void
    {
        $registry=new DedicatedAppRegistry();
        $apps=$registry->all();
        self::assertCount(12,$apps);
        self::assertSame('agent_worker_runtime',$apps[0]['id']);
        self::assertSame('notification_gateway',$apps[1]['id']);
        self::assertSame('mobile_companion',$apps[2]['id']);
        foreach($apps as$app){
            self::assertNotEmpty($app['spineOutputs']);
            self::assertNotEmpty($app['mvpCapabilities']);
            self::assertFalse($app['aiMayExecute']);
            self::assertContains($app['readiness'],['api_backed','integration_required']);
        }
        self::assertSame('integration_required',$registry->get('secure_remote_desktop')['readiness']);
        self::assertContains('municipal_submission',$registry->get('revit_cad_connector')['sensitiveGates']);
        self::assertSame('api_backed',$registry->get('agent_worker_runtime')['readiness']);
    }
}
