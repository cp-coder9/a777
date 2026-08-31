<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Wingman;

use PHPUnit\Framework\TestCase;

final class WingmanMigrationTest extends TestCase
{
    public function testMigrationOwnsPermissionAwareConversationsKnowledgeAndAudit(): void
    {
        $sql = @file_get_contents(dirname(__DIR__, 3) . '/database/migrations/0056_guided_wingman.sql');
        self::assertIsString($sql, 'The guided Wingman migration must exist.');

        foreach ([
            'wingman_conversations', 'wingman_messages', 'wingman_citations', 'wingman_suggestions',
            'wingman_suggestion_feedback', 'wingman_knowledge_chunks', 'wingman_audit_records',
        ] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        foreach ([
            'organisation_id', 'user_id', 'project_id', 'retention_expires_at', 'provider', 'model',
            'permission_key', 'source_reference', 'route_target', 'context_summary', 'metadata_json',
            'FULLTEXT KEY ft_wingman_knowledge', 'idx_wingman_conversation_scope',
            'uq_wingman_feedback_user', 'ON DELETE CASCADE',
        ] as $guard) {
            self::assertStringContainsString($guard, $sql);
        }
        self::assertStringContainsString('INTERVAL 90 DAY', $sql);
        self::assertStringNotContainsString('api_key', strtolower($sql));
        self::assertStringNotContainsString('DROP TABLE', $sql);
    }
}
