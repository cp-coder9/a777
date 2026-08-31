<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Wingman;

use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;
use PDO;

final class WingmanSchemaTest extends MariaDbTestCase
{
    public function testSchemaProvidesTenantIndexesFullTextKnowledgeAndDefaultRetention(): void
    {
        $tables = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'wingman_%'")->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);
        self::assertSame([
            'wingman_audit_records', 'wingman_citations', 'wingman_conversations', 'wingman_knowledge_chunks',
            'wingman_messages', 'wingman_suggestion_feedback', 'wingman_suggestions',
        ], $tables);

        $fullText = $this->pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='wingman_knowledge_chunks' AND index_name='ft_wingman_knowledge' AND index_type='FULLTEXT'")->fetchColumn();
        self::assertSame(2, (int) $fullText, 'The full-text index must cover title and content.');

        $organisation = Identifier::generate();
        $user = Identifier::generate();
        $conversation = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => 'wingman-schema@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Wingman schema','wingman-schema','active')")->execute(['id' => $organisation]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation' => $organisation, 'user' => $user]);
        $this->pdo->prepare("INSERT INTO wingman_conversations(id,organisation_id,user_id,title,provider,model) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'Schema check','nvidia','nvidia/nemotron-3.5-lightning-30b-a3b')")->execute(['id' => $conversation, 'organisation' => $organisation, 'user' => $user]);

        $days = $this->pdo->query("SELECT TIMESTAMPDIFF(DAY,created_at,retention_expires_at) FROM wingman_conversations WHERE id=UNHEX('$conversation')")->fetchColumn();
        self::assertGreaterThanOrEqual(89, (int) $days);
        self::assertLessThanOrEqual(90, (int) $days);
    }
}
