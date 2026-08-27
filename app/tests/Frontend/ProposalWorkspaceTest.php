<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ProposalWorkspaceTest extends TestCase
{
    public function testCanonicalShellExposesOwnerProposalAndPublicAcceptanceWorkflows(): void
    {
        $shell = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/shell.js');
        $styles = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/architex-v1.css');

        self::assertStringContainsString('id="proposalBuilderForm"', $shell);
        self::assertStringContainsString("api.post('/proposals'", $shell);
        self::assertStringContainsString('/issue', $shell);
        self::assertStringContainsString('professionalConfirmed', $shell);
        self::assertStringContainsString('legalReviewConfirmed', $shell);
        self::assertStringContainsString('proposal_token', $shell);
        self::assertStringContainsString('responsibilityConfirmed', $shell);
        self::assertStringContainsString('/proposal-acceptance/', $shell);
        self::assertStringContainsString('Draft terms for legal review', $shell);
        self::assertStringContainsString('/revisions', $shell);
        self::assertStringNotContainsString(',document=item.document', $shell);
        self::assertStringContainsString('if (proposalToken) { enforceProductionControls(null);', $shell);
        self::assertStringContainsString('body.public-proposal .app{--nav:0px}', $styles);
        self::assertStringContainsString('.public-proposal-card .notice{overflow-wrap:anywhere}', $styles);
    }
}
