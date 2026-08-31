<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class JobTicketMarketplaceWorkspaceTest extends TestCase
{
    public function testUsesTheFlightpathPageGrammarAndOrderedMarketplaceWorkflow(): void
    {
        $javascript = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/job-ticket-marketplace.js'));
        $css = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/job-ticket-marketplace.css'));

        foreach ([
            'fp-page', 'fp-page-header', 'fp-page-header__copy', 'fp-page-header__actions',
            'fp-workflow', 'marketplace workflow', "setattribute('data-jtm-region',region)",
            'orient:', 'find:', 'create:', 'review:', 'administer:',
            'scrolltojobticketregion', "classlist.add('fp-field')", 'btn primary',
        ] as $token) self::assertStringContainsString($token, $javascript);

        self::assertLessThan(strpos($javascript, "scrolltojobticketregion('find')"), strpos($javascript, "scrolltojobticketregion('orient')"));
        self::assertLessThan(strpos($javascript, "scrolltojobticketregion('review')"), strpos($javascript, "scrolltojobticketregion('find')"));
        foreach (['.jtm-workflow', '.jtm-region', '.jtm-form .fp-field', '@media (max-width: 760px)'] as $token) {
            self::assertStringContainsString($token, $css);
        }
    }

    public function testV1ShellContainsResponsiveGovernedJobTicketWorkspace(): void
    {
        $javascript=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/job-ticket-marketplace.js');
        $css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/job-ticket-marketplace.css');
        $app=(string)file_get_contents(dirname(__DIR__,2).'/public/app.php');
        $front=(string)file_get_contents(dirname(__DIR__,2).'/public/index.php');

        foreach([
            'jobTicketMarketplaceWorkspace','Job ticket exchange','Post governed ticket','Public opportunities','Direct invitations','Recommended for you','Active work',
            'Advisory classification','Quotation register','Ticket lineage','Freelancer profile','Locked supervision route','Timesheets &amp; claims','Deliverables &amp; exact-revision sign-off',
            'automaticAward:false','recorded evidence, not bank confirmation','independent freelancer appointment','/job-tickets','/freelancers/me','/freelancer-engagements/',
            'createJobTicket','submitJobTicketQuote','acceptJobTicketQuote','expandJobTicket','publishFreelancerProfile','submitFreelancerTimesheet','recordFreelancerPaymentEvidence','signOffFreelancerDeliverable','completeFreelancerEngagement',
            "t.posterUserId===window.__ARCHITEX_SHELL_STATE__?.session?.userId",
            'window.reviewFreelancerEngagement=reviewFreelancerEngagement',
            "setAttribute('aria-label','Execution categories')","categories.innerHTML='<span class=\"jtm-tab active\">Timesheets &amp; claims</span>",
        ] as $token)self::assertStringContainsString($token,$javascript);

        foreach(['.jtm-workspace','.jtm-lineage','.jtm-lanes','.jtm-classification','.jtm-workspace [hidden]','@media (max-width: 760px)','overflow-x:clip'] as $token)self::assertStringContainsString($token,$css);
        self::assertStringContainsString('.jtm-hero-controls .btn{background:#eafaf8;color:#0b5b57}',$css);
        self::assertStringContainsString('.jtm-empty{color:#50645f',$css);
        self::assertStringContainsString('/assets/css/job-ticket-marketplace.css',$app);
        self::assertStringContainsString('/assets/js/job-ticket-marketplace.js',$app);
        self::assertStringContainsString('freelancer-job-ticket-marketplace',$front);
    }
}
