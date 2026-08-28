<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class MunicipalReadinessWorkspaceTest extends TestCase
{
    public function testCanonicalWorkspaceExposesTheCompleteGovernedReadinessWorkflow():void
    {
        $shell=file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');self::assertIsString($shell);
        foreach(['id="municipalReadinessWorkspace"','Municipal Submission Readiness','Overall readiness','Eight readiness categories','Required professionals','Not currently required','Missing documents','Required human sign-offs','Submission evidence pack','Next-best actions','/municipal-requirement-versions','/submission-readiness','matrixVersionId','clientAuthorityConfirmed','professionalSignoffs','No statutory approval','municipalSubmissionCreated','TOOLS.municipal.status = \'live\'']as$expected)self::assertStringContainsString($expected,$shell);
        foreach(['escapeHtml(result.matrix.version)','escapeHtml(route.reason)','escapeHtml(item.title)','encodeURIComponent(projectId)','encodeURIComponent(id)']as$escaped)self::assertStringContainsString($escaped,$shell);
    }
}
