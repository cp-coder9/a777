<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Wingman;

use Architex\Infrastructure\AI\WingmanModel;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Wingman\WingmanException;
use Architex\Platform\Wingman\WingmanService;
use Architex\Tests\Support\MariaDbTestCase;

final class WingmanServiceTest extends MariaDbTestCase
{
    public function testPersistsCitedKnowledgeAnswerAndKeepsConversationPrivate(): void
    {
        [$organisation, $owner, $project] = $this->tenant('wingman-service');
        $knowledge = Identifier::generate();
        $this->pdo->prepare("INSERT INTO wingman_knowledge_chunks(id,organisation_id,project_id,permission_key,source_type,source_id,source_reference,route_target,title,content,content_sha256,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),'projects.read','practice_guide','guide-1','Practice guide 4.2','municipal','Municipal submission pack','Confirm the drawing register, professional appointments and submission evidence before review.',:sha,UNHEX(:user))")->execute([
            'id' => $knowledge, 'organisation' => $organisation, 'project' => $project,
            'sha' => hash('sha256', 'municipal submission pack'), 'user' => $owner,
        ]);
        $model = new QueueWingmanModel([
            $this->modelResult('', [['id' => 'call-1', 'name' => 'search_authorised_knowledge', 'arguments' => ['query' => 'submission evidence']]]),
            $this->modelResult('Review the cited submission evidence before opening Comply.'),
        ]);
        $service = new WingmanService($this->pdo, $model, dirname(__DIR__, 3) . '/public/assets/route-placement.json');

        $bootstrap = $service->bootstrap($organisation, $owner, 'owner', 'municipal', $project);
        self::assertSame('municipal', $bootstrap['context']['route']);
        self::assertSame('nvidia/nemotron-3.5-lightning-30b-a3b', $bootstrap['model']);

        $answer = $service->message($organisation, $owner, 'owner', [
            'prompt' => 'What evidence should I review?', 'route' => 'municipal', 'projectId' => $project,
        ]);
        self::assertSame('Review the cited submission evidence before opening Comply.', $answer['message']['content']);
        self::assertSame('Municipal submission pack', $answer['citations'][0]['title']);
        self::assertCount(2, $model->requests, 'Only one bounded tool follow-up is permitted.');

        $conversation = $service->conversation($organisation, $owner, $answer['conversationId']);
        self::assertCount(2, $conversation['messages']);
        self::assertSame('user', $conversation['messages'][0]['role']);
        self::assertSame('assistant', $conversation['messages'][1]['role']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM wingman_citations WHERE organisation_id=UNHEX('$organisation')")->fetchColumn());
        self::assertGreaterThanOrEqual(2, (int) $this->pdo->query("SELECT COUNT(*) FROM wingman_audit_records WHERE organisation_id=UNHEX('$organisation')")->fetchColumn());

        $otherUser = $this->member($organisation, 'wingman-other@example.test', 'member');
        try {
            $service->conversation($organisation, $otherUser, $answer['conversationId']);
            self::fail('Conversations are private to the authenticated user.');
        } catch (WingmanException $exception) {
            self::assertSame('wingman_conversation_not_found', $exception->reason);
        }
    }

    public function testCreatesExplicitNavigationSuggestionAndRecordsFeedback(): void
    {
        [$organisation, $owner, $project] = $this->tenant('wingman-navigation');
        $model = new QueueWingmanModel([
            $this->modelResult('', [['id' => 'call-2', 'name' => 'propose_navigation', 'arguments' => ['route' => 'municipal']]]),
            $this->modelResult('I suggest opening Municipal Approval Readiness. You decide whether to navigate.'),
        ]);
        $service = new WingmanService($this->pdo, $model, dirname(__DIR__, 3) . '/public/assets/route-placement.json');
        $answer = $service->message($organisation, $owner, 'owner', [
            'prompt' => 'Take me to submission readiness.', 'route' => 'projects', 'projectId' => $project,
        ]);

        self::assertSame('navigate', $answer['suggestions'][0]['type']);
        self::assertSame('municipal', $answer['suggestions'][0]['routeTarget']);
        self::assertSame('proposed', $answer['suggestions'][0]['status']);

        $feedback = $service->feedback($organisation, $owner, $answer['suggestions'][0]['id'], ['rating' => 'helpful', 'note' => 'Correct next step.']);
        self::assertSame('helpful', $feedback['rating']);
    }

    public function testProjectAndRecordToolsExposeOnlyBoundedLiveSummaries(): void
    {
        [$organisation, $owner, $project] = $this->tenant('wingman-live-summary');
        $record = Identifier::generate();
        $this->pdo->prepare("INSERT INTO project_records(id,organisation_id,project_id,phase,module_key,record_type,title,status,payload,approvals,linked_record_ids,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:project),'municipal_submission','compliance','submission_pack','Council submission pack','pending_review',:payload,JSON_ARRAY(),JSON_ARRAY(),UNHEX(:user))")->execute([
            'id' => $record, 'organisation' => $organisation, 'project' => $project,
            'payload' => json_encode(['privateRawBody' => 'must-not-reach-model'], JSON_THROW_ON_ERROR), 'user' => $owner,
        ]);
        $model = new QueueWingmanModel([
            $this->modelResult('', [['id' => 'call-record', 'name' => 'get_authorised_record_summary', 'arguments' => []]]),
            $this->modelResult('The selected pack is pending review.'),
            $this->modelResult('', [['id' => 'call-project', 'name' => 'get_authorised_project_summary', 'arguments' => []]]),
            $this->modelResult('The project is in municipal submission with one governed record.'),
        ]);
        $service = new WingmanService($this->pdo, $model, dirname(__DIR__, 3) . '/public/assets/route-placement.json');

        $service->message($organisation, $owner, 'owner', [
            'prompt' => 'Summarise this record.', 'route' => 'municipal', 'projectId' => $project, 'selectedRecordId' => $record,
        ]);
        $recordToolContext = $model->requests[1]['messages'][array_key_last($model->requests[1]['messages'])]['content'];
        self::assertStringContainsString('Council submission pack', $recordToolContext);
        self::assertStringContainsString('pending_review', $recordToolContext);
        self::assertStringNotContainsString('must-not-reach-model', $recordToolContext);

        $service->message($organisation, $owner, 'owner', [
            'prompt' => 'Summarise the project.', 'route' => 'projects', 'projectId' => $project,
        ]);
        $projectToolContext = $model->requests[3]['messages'][array_key_last($model->requests[3]['messages'])]['content'];
        self::assertStringContainsString('Wingman Project', $projectToolContext);
        self::assertStringContainsString('municipal_submission', $projectToolContext);
        self::assertStringContainsString('recordCount', $projectToolContext);
    }

    public function testFailsClosedForProjectRequiredRouteWithoutAuthorisedProject(): void
    {
        [$organisation, $owner] = $this->tenant('wingman-denied');
        $service = new WingmanService($this->pdo, new QueueWingmanModel([]), dirname(__DIR__, 3) . '/public/assets/route-placement.json');

        $this->expectExceptionObject(new WingmanException('context_denied'));
        $service->bootstrap($organisation, $owner, 'owner', 'municipal', null);
    }

    /** @param list<array{id:string,name:string,arguments:array<string,mixed>}> $toolCalls @return array<string,mixed> */
    private function modelResult(string $content, array $toolCalls = []): array
    {
        return [
            'content' => $content, 'toolCalls' => $toolCalls, 'requestId' => 'request-test',
            'model' => 'nvidia/nemotron-3.5-lightning-30b-a3b', 'finishReason' => $toolCalls === [] ? 'stop' : 'tool_calls',
            'usage' => ['input' => 10, 'output' => 5],
        ];
    }

    /** @return array{string,string,string} */
    private function tenant(string $slug): array
    {
        $organisation = Identifier::generate(); $user = Identifier::generate(); $project = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $slug . '@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisation, 'name' => $slug, 'slug' => $slug . '-' . substr($organisation, 0, 8)]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id' => Identifier::generate(), 'organisation' => $organisation, 'user' => $user]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:project),UNHEX(:organisation),'WING','Wingman Project','Client','City','Erf 1','Office','','architect','municipal_submission',UNHEX(:user))")->execute(['project' => $project, 'organisation' => $organisation, 'user' => $user]);
        return [$organisation, $user, $project];
    }

    private function member(string $organisation, string $email, string $role): string
    {
        $user = Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $user, 'email' => $email]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id' => Identifier::generate(), 'organisation' => $organisation, 'user' => $user, 'role' => $role]);
        return $user;
    }
}

final class QueueWingmanModel implements WingmanModel
{
    /** @var list<array<string,mixed>> */
    public array $requests = [];

    /** @param list<array<string,mixed>> $responses */
    public function __construct(private array $responses) {}

    public function complete(array $messages, array $tools = []): array
    {
        $this->requests[] = ['messages' => $messages, 'tools' => $tools];
        $response = array_shift($this->responses);
        if (!is_array($response)) throw new \RuntimeException('Unexpected model call.');
        return $response;
    }
}
