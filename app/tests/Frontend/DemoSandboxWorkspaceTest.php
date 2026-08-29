<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DemoSandboxWorkspaceTest extends TestCase
{
    public function testDemoHubUsesTheCanonicalV1ShellAndServerOwnedContracts(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $javascript = (string) file_get_contents($public . '/assets/js/demo-sandbox.js');
        $css = (string) file_get_contents($public . '/assets/css/demo-sandbox.css');
        $html = (string) file_get_contents($public . '/app.php');
        $index = (string) file_get_contents($public . '/index.php');

        foreach (['demoSandboxWorkspace','hydrateDemoSandbox','DEMO SANDBOX','/demo/sandbox/ensure','/demo/sandbox','RESET DEMO SANDBOX','illustrative_demo_not_for_reliance','not_accredited_demo_record'] as $needle) {
            self::assertStringContainsString($needle, $javascript . $html);
        }
        foreach (['Client / Developer','Project Manager','Architect (PrArch)','Architectural Technologist','Candidate Architect','Structural Engineer (PrEng)','Civil Engineer (PrEng)','Electrical Engineer (PrEng)','Mechanical Engineer (PrEng)','Fire Engineer','Quantity Surveyor (PrQS)','Contractor (Main)','Subcontractor','Supplier','Energy Consultant','Town Planner','Environmental Consultant','Accessibility Consultant','BEP / Professional','Freelancer / Draughtsperson','CPD Officer / Administrator','Administrator'] as $label) {
            self::assertStringContainsString($label, $javascript);
        }
        self::assertStringContainsString("getenv('APP_MODE')==='demo'", $index);
        self::assertSame(1, substr_count($html, 'class="os-rail"'));
        foreach (['id="navigator"','id="main"','class="inspector"'] as $owner) self::assertSame(1, substr_count($html, $owner));
        foreach (['.demo-sandbox-status-rail','.demo-sandbox-role-control','.demo-sandbox-project-grid',':focus-visible','@media (max-width: 760px)','prefers-reduced-motion'] as $needle) self::assertStringContainsString($needle, $css);
        self::assertStringNotContainsString('localStorage', $javascript);
        self::assertStringNotContainsString('MOCK_PROJECTS', $javascript);
    }
}
