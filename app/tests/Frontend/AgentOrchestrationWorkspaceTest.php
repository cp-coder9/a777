<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class AgentOrchestrationWorkspaceTest extends TestCase
{
    public function testWingmanUsesTheProductionAgentOrchestrationContract(): void
    {
        $shell = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/shell.js');
        self::assertIsString($shell);
        foreach ([
            'Agent Orchestration', 'Identity & bounded context', 'Governance policies',
            'Workflow event routes', 'Advisory recommendations', 'Contextual message drafts',
            'Human approval gates', 'Memory boundary', 'Agent monitoring',
            '/agent-orchestration-workspace', '/agent/bootstrap', '/agent/policies',
            '/agent/routes', '/agent/events/route', '/agent/message-drafts', '/agent/approval-gates/',
            'externalDispatchPerformed', 'permissionBypassAllowed', 'A different authorised user must decide.',
        ] as $required) {
            self::assertStringContainsString($required, $shell);
        }
        self::assertStringContainsString("state.tool==='wingman'", $shell);
    }
}
