<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class CommunityWorkspaceTest extends TestCase
{
    public function testEmbeddedAndStandaloneShellsShareProductionCommunityApi(): void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');
        $styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css').(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/community.css');
        $index=(string)file_get_contents(dirname(__DIR__,2).'/public/index.php');
        $api=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/api-client.js');
        foreach (['communityWorkspace','hydrateCommunityWorkspace','/community/channels','/community/preference','/community/feed','/community/threads','/community/moderation','communityChannelAdminForm','channel-preference','standalone_mobile','embedded_platform','community_conversion_confirmation_required','serviceWorker.register'] as $needle) self::assertStringContainsString($needle,$shell);
        foreach (['community-layout','community-compose','community-standalone','community-search','@media(max-width:720px)'] as $needle) self::assertStringContainsString($needle,$styles);
        self::assertStringContainsString('messages/community',$index);
        self::assertStringContainsString('community-app',$index);
        self::assertStringContainsString('community-(?:moderation|channels)',$index);
        self::assertStringContainsString('put(path, body)',$api);
        self::assertFileExists(dirname(__DIR__,2).'/public/community.webmanifest');
        self::assertFileExists(dirname(__DIR__,2).'/public/community-sw.js');
        self::assertStringNotContainsString('WhatsApp',$shell);
    }
}
