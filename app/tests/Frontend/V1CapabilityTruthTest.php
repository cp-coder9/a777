<?php

declare(strict_types=1);

namespace Architex\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class V1CapabilityTruthTest extends TestCase
{
    public function testRegistryUsesProductionReadinessVocabularyWithoutScaffoldClaims(): void
    {
        $registry = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/tool-registry.json'), true, flags: JSON_THROW_ON_ERROR);
        $tools = $registry['tools'] ?? [];
        $statuses = array_unique(array_column($tools, 'status'));

        self::assertNotContains('scaffold', $statuses);
        self::assertNotContains('prototype_live', $statuses);
        foreach ($tools as $tool) {
            self::assertContains($tool['status'], ['live', 'limited', 'integration_required', 'unavailable']);
            self::assertArrayHasKey('evidence', $tool);
            self::assertNotSame('', trim((string) $tool['evidence']));
        }
    }

    public function testControlledRegistersAndExternalDesktopAreTruthfullyClassified(): void
    {
        $registry = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/tool-registry.json'), true, flags: JSON_THROW_ON_ERROR);
        $byId = array_column($registry['tools'] ?? [], null, 'id');

        foreach (['dispute_resolution', 'insurance_register', 'nhbrc_enrolment', 'contractor_compliance'] as $id) {
            self::assertSame('live', $byId[$id]['status']);
        }
        self::assertSame('integration_required', $byId['remote_desktop']['status']);
    }
}
