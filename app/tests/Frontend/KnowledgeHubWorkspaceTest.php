<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class KnowledgeHubWorkspaceTest extends TestCase
{
    public function testKnowledgeIsAFirstClassEvidenceBackedWorkspace(): void
    {
        $app=(string)file_get_contents(dirname(__DIR__,2).'/public/app.php');
        $shell=strtolower((string)file_get_contents(dirname(__DIR__,2).'/public/assets/js/shell.js'));
        self::assertStringContainsString('id:"knowledge",label:"Knowledge"',$app);
        foreach(['knowledgehubworkspace','source-linked built-environment knowledge','/knowledge/sources','sourcereference','reviewstatus','citation required','ai may approve: no','reviewknowledgesource']as$token)self::assertStringContainsString($token,$shell);
    }
}
