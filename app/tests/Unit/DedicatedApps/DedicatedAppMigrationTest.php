<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\DedicatedApps;

use PHPUnit\Framework\TestCase;

final class DedicatedAppMigrationTest extends TestCase
{
    public function testUnifiedSpinePersistsCredentialsEventsConflictsAndDispatchEvidence():void
    {
        $sql=(string)file_get_contents(dirname(__DIR__,3).'/database/migrations/0049_dedicated_apps_unified.sql');
        foreach(['dedicated_app_installations','credential_hash','dedicated_app_workflow_events','ai_may_execute','dedicated_app_sync_state','dedicated_app_sync_batches','dedicated_app_notification_dispatches','integration_required',"status ENUM('applied','conflict','failed')"]as$token)self::assertStringContainsString($token,$sql);
        self::assertStringNotContainsString('credential_token',$sql);
        $links=(string)file_get_contents(dirname(__DIR__,3).'/database/migrations/0050_dedicated_app_spine_links.sql');
        foreach(['dedicated_app_event_spine_links','project_record_id','inbox_item_id','dedicated_app_workflow_events','project_records','inbox_items']as$token)self::assertStringContainsString($token,$links);
    }
}
