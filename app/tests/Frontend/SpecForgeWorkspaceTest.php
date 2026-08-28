<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class SpecForgeWorkspaceTest extends TestCase
{
    public function testCanonicalWorkspaceUsesPersistedProviderHonestSpecForgeFlow():void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');self::assertIsString($shell);
        foreach(['id="specForgeWorkspace"','SpecForge','Pictorial specification','Sections','Product Register','Approvals','Budget & Risk','Document Preview','Issue & Distribute','/specforge-workspaces','/specforge-items/','budgetAllowanceCents','estimatedCostCents','leadTimeDays','requiresClientDecision','professionalRole','externalDeliveryClaimed','AI drawing suggestions unavailable','Supplier ordering unavailable','PDF export unavailable','TOOLS.specforge.status = \'live\'']as$expected)self::assertStringContainsString($expected,$shell);
        foreach(['escapeHtml(item.title)','escapeHtml(section.title)','encodeURIComponent(workspace.id)','encodeURIComponent(id)']as$escaped)self::assertStringContainsString($escaped,$shell);
    }
}
