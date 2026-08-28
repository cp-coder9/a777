<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DocumentWorkspaceTest extends TestCase
{
    public function testCanonicalDocumentsToolUsesControlledRevisionWorkspace():void
    {
        $shell=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js');$styles=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/architex-v1.css');
        foreach(['id="documentControlWorkspace"','Document Register','Drawing Register','Revision History','Pack Readiness','Evidence boundary','No OCR, PDF parsing, AI classification or digital signature provider is configured','/documents','/submit-review','/approve','/issue','/document-readiness','humanConfirmed:true','TOOLS.documents_drawings.status = \'live\'']as$expected)self::assertStringContainsString($expected,$shell);
        self::assertStringContainsString('.document-workspace',$styles);self::assertStringContainsString('@media(max-width:760px)',$styles);
    }
}
