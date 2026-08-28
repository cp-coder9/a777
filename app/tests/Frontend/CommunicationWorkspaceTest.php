<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class CommunicationWorkspaceTest extends TestCase
{
    public function testCanonicalShellProvidesMobileChatAndDesktopCentreOnOneEngine():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        self::assertStringContainsString('id="projectMessageCentre"',$shell);
        self::assertStringContainsString('id="projectChatApplet"',$shell);
        self::assertStringContainsString('id="communicationProjectSwitcher"',$shell);
        self::assertStringContainsString('Suggested prompts',$shell);
        self::assertStringContainsString("api.post('/communication-threads'",$shell);
        self::assertStringContainsString('/drafts`',$shell);
        self::assertStringContainsString('/send`',$shell);
        self::assertStringContainsString('/convert`',$shell);
        self::assertStringContainsString('humanConfirmed:true',$shell);
        self::assertStringContainsString('/communication-assistant?phase=',$shell);
        self::assertStringContainsString('/communication-actions',$shell);
        self::assertStringContainsString("/files`,{method:'POST'",$shell);
        self::assertStringContainsString('window.openContextualMessage',$shell);
        self::assertStringContainsString("api.get(`/projects/\${encodeURIComponent(projectId)}/team`)",$shell);
        self::assertStringContainsString('participantUserIds:communicationTeamUserIds',$shell);
        self::assertStringContainsString('this.dataset.messageBody',$shell);
        self::assertStringNotContainsString('selectCommunicationMessage(\'${escapeHtml(message.id)}\',\'${escapeHtml(message.body)}\')',$shell);
        self::assertStringContainsString('.communication-mobile-tabs',$styles);
        self::assertStringContainsString('@media(max-width:760px)',$styles);
    }
}
