<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class LegalCentreTest extends TestCase
{
    public function testAllPack31PublicLegalRoutesAreMounted(): void
    {
        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
        foreach (['/terms','/privacy','/disclaimer','/ai-disclaimer','/payment-escrow-terms','/cpd-terms','/resource-sharing-terms','/dispute-resolution-rules'] as $route) {
            self::assertStringContainsString("'{$route}'", $index);
        }
        self::assertStringContainsString("require __DIR__ . '/legal.php'", $index);
    }

    public function testLegalCentrePreservesDraftAndHumanAuthorityBoundaries(): void
    {
        $page = strtolower((string) file_get_contents(dirname(__DIR__, 2) . '/public/legal.php'));
        foreach (['draft pending south african attorney review','humans must verify, approve, sign, submit, certify, purchase and release funds','ai can make mistakes','third-party payment or escrow provider','popia','version 0.1-development'] as $copy) {
            self::assertStringContainsString($copy, $page);
        }
    }
}
