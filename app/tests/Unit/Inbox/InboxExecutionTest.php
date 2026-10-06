<?php
declare(strict_types=1);
namespace Architex\Tests\Unit\Inbox;

use Architex\Platform\Inbox\InboxMailQueue;
use Architex\Platform\Inbox\InboxService;
use Architex\Platform\Jobs\JobQueue;
use Architex\Platform\Jobs\PayloadCipher;
use PDO;
use PHPUnit\Framework\TestCase;

/** Single-process dialect adapter; does not claim to test MariaDB locking. */
final class NotificationSqlite extends PDO
{
    private function sql(string $sql): string
    {
        $sql = str_replace(' FOR UPDATE SKIP LOCKED','',$sql);
        return preg_replace_callback('/UTC_TIMESTAMP\(6\)([+-])INTERVAL (\d+) (SECOND|MINUTE)/',static fn($m)=>"datetime('now','{$m[1]}{$m[2]} ".strtolower($m[3])."')",$sql);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    { return $fetchMode===null?parent::query($this->sql($query)):parent::query($this->sql($query),$fetchMode,...$fetchModeArgs); }
    public function exec(string $statement): int|false { return parent::exec($this->sql($statement)); }
    public function prepare(string $query, array $options = []): \PDOStatement|false { return parent::prepare($this->sql($query),$options); }
}

final class InboxExecutionTest extends TestCase
{
    private PDO $pdo;
    private InboxMailQueue $mail;
    private function id(int $n): string { return str_pad(dechex($n), 32, '0', STR_PAD_LEFT); }
    protected function setUp(): void
    {
        $this->pdo = new NotificationSqlite('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        // Only dialect functions differ; production recipient SQL executes unchanged.
        $this->pdo->sqliteCreateFunction('UNHEX', static fn($x)=>$x);
        $this->pdo->sqliteCreateFunction('HEX', static fn($x)=>$x);
        $this->pdo->sqliteCreateFunction('DATE_FORMAT', static fn($x,$f)=>$x);
        $this->pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn($x)=>gmdate('Y-m-d H:i:s'));
        $this->pdo->sqliteCreateFunction('SHA2', static fn($x,$n)=>hash('sha256',$x));
        $this->pdo->sqliteCreateFunction('CONCAT', static fn(...$x)=>implode('',$x));
        $this->pdo->exec("CREATE TABLE users(id TEXT,email TEXT,status TEXT,email_verified_at TEXT);
            CREATE TABLE organisations(id TEXT,status TEXT);
            CREATE TABLE organisation_memberships(organisation_id TEXT,user_id TEXT,role TEXT,status TEXT);
            CREATE TABLE projects(id TEXT,organisation_id TEXT,created_by TEXT);
            CREATE TABLE project_team_members(project_id TEXT,organisation_id TEXT,user_id TEXT,status TEXT);
            CREATE TABLE inbox_items(id TEXT,organisation_id TEXT,project_id TEXT,assigned_user_id TEXT,assigned_role TEXT,event_type TEXT,title TEXT,detail TEXT,priority TEXT,status TEXT,payload TEXT,created_at TEXT);
            CREATE TABLE scheduled_jobs(id TEXT PRIMARY KEY,job_type TEXT,payload TEXT,status TEXT,attempts INTEGER,max_attempts INTEGER,available_at TEXT,locked_at TEXT,locked_by TEXT,last_error_code TEXT,completed_at TEXT,failed_at TEXT);
            CREATE TABLE scheduled_job_deduplication_keys(deduplication_key TEXT PRIMARY KEY,job_id TEXT);");
        $this->pdo->exec("INSERT INTO organisations VALUES('{$this->id(1)}','active'); INSERT INTO projects VALUES('{$this->id(2)}','{$this->id(1)}','{$this->id(3)}')");
        foreach ([3=>'owner',4=>'member',5=>'member',6=>'viewer'] as $n=>$role) {
            $this->pdo->exec("INSERT INTO users VALUES('{$this->id($n)}','user{$n}@example.test','active','2026-01-01'); INSERT INTO organisation_memberships VALUES('{$this->id(1)}','{$this->id($n)}','$role','active')");
        }
        $this->pdo->exec("INSERT INTO project_team_members VALUES('{$this->id(2)}','{$this->id(1)}','{$this->id(4)}','active')");
        $this->item(10,4,'member'); $this->item(11,null,'member'); $this->item(12,null,'owner');
        $this->mail = new InboxMailQueue($this->pdo,new JobQueue($this->pdo,PayloadCipher::fromBase64Key(base64_encode(str_repeat('k',32)))));
    }
    private function item(int $id, ?int $user, ?string $role): void
    {
        $this->pdo->prepare("INSERT INTO inbox_items VALUES(?,?,?,?,?,'communication_rfi','Review','Confidential','high','open','{}','2026-10-05')")
            ->execute([$this->id($id),$this->id(1),$this->id(2),$user===null?null:$this->id($user),$role]);
    }
    public function testInboxDoesNotLeakDirectOrRoleItemsAndIgnoresSpoofedRole(): void
    {
        $inbox = new InboxService($this->pdo);
        self::assertCount(2,$inbox->list($this->id(1),$this->id(4),'member'));
        self::assertSame([],$inbox->list($this->id(1),$this->id(5),'owner'));
        self::assertCount(1,$inbox->list($this->id(1),$this->id(3),'member'));
        self::assertSame([],$inbox->list($this->id(99),$this->id(4),'member'));
    }
    public function testReconciliationQueuesOnceAndRechecksRevocation(): void
    {
        self::assertSame(3,$this->mail->enqueue());
        self::assertSame(0,$this->mail->enqueue());
        self::assertSame('user4@example.test',$this->mail->recipient($this->id(10),$this->id(4)));
        $this->pdo->exec("UPDATE organisation_memberships SET status='revoked' WHERE user_id='{$this->id(4)}'");
        self::assertNull($this->mail->recipient($this->id(10),$this->id(4)));
        self::assertSame(3,(int)$this->pdo->query('SELECT COUNT(*) FROM scheduled_jobs')->fetchColumn());
    }
    public function testUnknownAudienceAndRemovedProjectMembersAreNotBroadcast(): void
    {
        $this->item(13,null,null);
        $this->pdo->exec("UPDATE project_team_members SET status='removed'");
        self::assertNull($this->mail->recipient($this->id(11),$this->id(4)));
        self::assertNull($this->mail->recipient($this->id(13),$this->id(3)));
    }
    public function testMeetingPublicationResolvesStoredIdentitiesOnlyAndDeduplicates(): void
    {
        $this->pdo->exec('DELETE FROM inbox_items');
        $notifier = new \Architex\Platform\Inbox\MeetingNotifications($this->pdo);
        $notifier->published($this->id(1),$this->id(2),$this->id(20),$this->id(21),[
            ['identity'=>'user4@example.test'],['identity'=>$this->id(4)],
            ['identity'=>'Someone Unknown'],['identity'=>'external@example.test'],
        ],[['state'=>'accepted','owner'=>'user3@example.test']]);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM inbox_items')->fetchColumn());
        self::assertCount(1,(new InboxService($this->pdo))->list($this->id(1),$this->id(4),'member'));
    }
    public function testDispatcherSendsEligibleNotificationAndReturnsTransportReceipt(): void
    {
        $cipher = PayloadCipher::fromBase64Key(base64_encode(str_repeat('k',32)));
        $mailer = new \Architex\Infrastructure\Mail\CapturedMailer();
        $dispatcher = new \Architex\Platform\Jobs\JobDispatcher($this->pdo,$cipher,$mailer,'https://example.test');
        $method = new \ReflectionMethod($dispatcher,'dispatch');
        $receipt = $method->invoke($dispatcher,['job_type'=>'send_inbox_email','payload'=>json_encode(['encrypted'=>$cipher->encrypt(['inboxId'=>$this->id(10),'userId'=>$this->id(4)])])]);
        self::assertInstanceOf(\Architex\Infrastructure\Mail\DeliveryReceipt::class,$receipt);
        self::assertCount(1,$mailer->messages());
        self::assertSame('user4@example.test',$mailer->messages()[0]->to);
        self::assertStringNotContainsString('Confidential',$mailer->messages()[0]->textBody);
    }
    public function testVariationRoutesToClientQsAndProjectLeadWithoutOtherProjectMembers(): void
    {
        $this->pdo->exec("ALTER TABLE projects ADD COLUMN lead_professional_role TEXT; UPDATE projects SET lead_professional_role='architect'; ALTER TABLE project_team_members ADD COLUMN discipline TEXT; UPDATE project_team_members SET discipline='architect'");
        $rows = (new \Architex\Platform\Inbox\WorkflowRecipients($this->pdo))->forEvent($this->id(1),$this->id(2),'finance_variation_requires_approval');
        self::assertSame([$this->id(3),$this->id(4)],array_column($rows,'id'));
        self::assertSame([],(new \Architex\Platform\Inbox\WorkflowRecipients($this->pdo))->forEvent($this->id(1),$this->id(2),'unknown_event'));
        self::assertSame([$this->id(3),$this->id(4)],array_column((new \Architex\Platform\Inbox\WorkflowRecipients($this->pdo))->forEvent($this->id(1),$this->id(2),'closeout.handover_pack_issued'),'id'));
        $this->pdo->exec('DELETE FROM inbox_items');
        (new InboxService($this->pdo))->create(new \Architex\Platform\Project\ProjectContext($this->id(1),$this->id(3),$this->id(2),'owner'),'finance_variation_requires_approval','Variation review','Review exact revision','high');
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM inbox_items')->fetchColumn());
    }
    public function testActualDispatcherRunPersistsReceiptsAndDoesNotSendTwice(): void
    {
        $cipher = PayloadCipher::fromBase64Key(base64_encode(str_repeat('k',32)));
        $mailer = new \Architex\Infrastructure\Mail\CapturedMailer();
        $dispatcher = new \Architex\Platform\Jobs\JobDispatcher($this->pdo,$cipher,$mailer,'https://example.test');
        self::assertSame(3,$dispatcher->run(10)->succeeded);
        self::assertSame(0,$dispatcher->run(10)->claimed);
        self::assertCount(3,$mailer->messages());
        foreach ($this->pdo->query('SELECT status,payload FROM scheduled_jobs') as $row) {
            self::assertSame('completed',$row['status']);
            self::assertNotEmpty(json_decode($row['payload'],true)['deliveryReceipt']['messageId']);
        }
    }
    public function testTransportFailureRetriesWithoutReceiptAndStaleWorkerDoesNotResend(): void
    {
        $cipher = PayloadCipher::fromBase64Key(base64_encode(str_repeat('k',32)));
        $mailer = new class implements \Architex\Infrastructure\Mail\Mailer {
            public function isConfigured(): bool { return true; }
            public function send(\Architex\Infrastructure\Mail\Message $message): \Architex\Infrastructure\Mail\DeliveryReceipt { throw new \RuntimeException('private transport error'); }
        };
        $dispatcher = new \Architex\Platform\Jobs\JobDispatcher($this->pdo,$cipher,$mailer,'https://example.test');
        self::assertSame(3,$dispatcher->run(10)->retried);
        foreach ($this->pdo->query('SELECT payload,last_error_code FROM scheduled_jobs') as $row) {
            self::assertArrayNotHasKey('deliveryReceipt',json_decode($row['payload'],true));
            self::assertSame('mail_delivery_failed',$row['last_error_code']);
        }
        $this->pdo->exec("UPDATE scheduled_jobs SET status='running',locked_by='crashed',locked_at='2000-01-01'");
        self::assertSame(0,$dispatcher->run(10)->claimed);
        self::assertSame(3,(int)$this->pdo->query("SELECT COUNT(*) FROM scheduled_jobs WHERE status='dead' AND last_error_code='delivery_outcome_unknown'")->fetchColumn());
    }
    public function testProcurementRequiresVerifiedProfessionalAndCurrentProjectTeam(): void
    {
        $this->pdo->sqliteCreateFunction('UTC_DATE',static fn()=>gmdate('Y-m-d'));
        $this->pdo->exec("ALTER TABLE project_team_members ADD COLUMN discipline TEXT; UPDATE project_team_members SET discipline='architect';
            CREATE TABLE verification_records(user_id TEXT,kind TEXT,grade TEXT,reviewed_by TEXT,expires_on TEXT)");
        $routing = new \Architex\Platform\Inbox\WorkflowRecipients($this->pdo);
        self::assertSame([$this->id(3)],array_column($routing->forEvent($this->id(1),$this->id(2),'procurement_award_approval_required'),'id'));
        $this->pdo->exec("INSERT INTO verification_records VALUES('{$this->id(4)}','professional_registration','manually_reviewed','{$this->id(3)}',NULL)");
        self::assertSame([$this->id(3),$this->id(4)],array_column($routing->forEvent($this->id(1),$this->id(2),'procurement_award_approval_required'),'id'));
        $this->pdo->exec("UPDATE verification_records SET expires_on='2000-01-01'");
        self::assertSame([$this->id(3)],array_column($routing->forEvent($this->id(1),$this->id(2),'procurement_award_approval_required'),'id'));
    }
    public function testRevokedQueuedRecipientIsDeadWithoutSending(): void
    {
        $this->mail->enqueue();
        $this->pdo->exec("UPDATE organisation_memberships SET status='revoked'");
        $mailer = new \Architex\Infrastructure\Mail\CapturedMailer();
        $dispatcher = new \Architex\Platform\Jobs\JobDispatcher($this->pdo,PayloadCipher::fromBase64Key(base64_encode(str_repeat('k',32))),$mailer,'https://example.test');
        self::assertSame(3,$dispatcher->run(10)->dead);
        self::assertCount(0,$mailer->messages());
    }
}
