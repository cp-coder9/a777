<?php

declare(strict_types=1);

namespace Architex\Platform\Community;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use PDOException;
use Throwable;

final readonly class CommunityMessagingService
{
    private const CATEGORIES = [
        'general_question','technical_query','regulation_or_sans_query','municipal_process_query',
        'supplier_recommendation','contractor_recommendation','professional_referral','software_or_resource_request',
        'cpd_question','job_ticket_opportunity','collaboration_request','product_or_material_query','site_issue_discussion','admin_notice',
    ];
    private const CONVERSIONS = [
        'project_message','rfi','rfi_draft','supplier_rfq_draft','job_ticket','support_ticket',
        'marketplace_request','cpd_support_request','knowledge_note','risk_item',
    ];

    public function __construct(private PDO $pdo, private CommunityAudiencePolicy $audiencePolicy = new CommunityAudiencePolicy()) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function savePreference(string $organisationId, string $userId, array $input): array
    {
        $this->member($organisationId, $userId);
        $audience = $this->audiencePolicy->normalise(['scope' => 'selected_roles', 'targetRoles' => [$input['role'] ?? null]]);
        $region = $this->nullableText($input['region'] ?? null, 120);
        $verification = (string) ($input['verificationStatus'] ?? 'self_declared');
        $this->audiencePolicy->normalise(['scope' => 'selected_roles', 'targetRoles' => $audience['targetRoles'], 'verificationStatus' => $verification]);
        $blocked = $this->ids($input['blockedUserIds'] ?? [], 'community_blocked_users_invalid');
        $muted = $this->roleList($input['mutedRoles'] ?? []);
        $digest = $this->choice($input['digestMode'] ?? 'daily', ['immediate','daily','weekly','none'], 'community_digest_mode_invalid');
        $optedIn = ($input['optedIn'] ?? false) === true;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO community_preferences(user_id,home_organisation_id,professional_role,region,verification_status,opted_in,blocked_user_ids,muted_roles,digest_mode,popia_consent_at) VALUES(UNHEX(:user),UNHEX(:organisation),:role,:region,:verification,:opted_in,:blocked,:muted,:digest,IF(:consent_opted_in=1,UTC_TIMESTAMP(6),NULL)) ON DUPLICATE KEY UPDATE home_organisation_id=VALUES(home_organisation_id),professional_role=VALUES(professional_role),region=VALUES(region),verification_status=VALUES(verification_status),opted_in=VALUES(opted_in),blocked_user_ids=VALUES(blocked_user_ids),muted_roles=VALUES(muted_roles),digest_mode=VALUES(digest_mode),popia_consent_at=IF(VALUES(opted_in)=1,COALESCE(popia_consent_at,UTC_TIMESTAMP(6)),NULL)')->execute([
                'user'=>$userId,'organisation'=>$organisationId,'role'=>$audience['targetRoles'][0],'region'=>$region,'verification'=>$verification,
                'opted_in'=>$optedIn ? 1 : 0,'consent_opted_in'=>$optedIn ? 1 : 0,'blocked'=>$this->json($blocked),'muted'=>$this->json($muted),'digest'=>$digest,
            ]);
            $this->audit()->append($organisationId, $userId, 'community.preference_saved', 'user', $userId, ['role'=>$audience['targetRoles'][0],'region'=>$region,'verificationStatus'=>$verification,'optedIn'=>$optedIn,'digestMode'=>$digest]);
            $this->pdo->commit();
            return $this->preference($organisationId, $userId);
        } catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    /** @return list<array<string,mixed>> */
    public function channels(string $organisationId, string $userId): array
    {
        $this->member($organisationId, $userId);$moderator=$this->isModerator($userId);
        $statement = $this->pdo->prepare("SELECT c.id,c.label,c.description,g.eligible_roles eligibleRoles,COALESCE(g.visibility,'public') visibility,COALESCE(g.default_moderation_policy,'standard') defaultModerationPolicy,COALESCE(cp.watching,0) watching,COALESCE(cp.muted,0) muted FROM community_channels c LEFT JOIN community_channel_governance g ON g.channel_id=c.id LEFT JOIN community_channel_preferences cp ON cp.channel_id=c.id AND cp.user_id=UNHEX(:user) LEFT JOIN community_preferences p ON p.user_id=UNHEX(:preference_user) WHERE c.status='active' AND (:is_admin=1 OR COALESCE(g.visibility,'public')='public' OR (g.visibility='role_restricted' AND JSON_CONTAINS(g.eligible_roles,JSON_QUOTE(p.professional_role)))) ORDER BY c.sort_order,c.id");
        $statement->execute(['user'=>$userId,'preference_user'=>$userId,'is_admin'=>$moderator?1:0]);
        return array_map(function(array$row):array{$row['eligibleRoles']=$row['eligibleRoles']===null?[]:$this->decode($row['eligibleRoles']);$row['watching']=(bool)$row['watching'];$row['muted']=(bool)$row['muted'];return$row;},$statement->fetchAll());
    }

    /** @return array<string,mixed> */
    public function saveChannelPreference(string $organisationId,string $userId,string $channelId,bool $watching,bool $muted):array
    {
        $this->member($organisationId,$userId);$channelId=$this->text($channelId,80,'community_channel_required');$this->assertChannelAccess($organisationId,$userId,$channelId);
        if($watching&&$muted)throw new CommunityException('community_channel_preference_conflict');
        $this->pdo->prepare('INSERT INTO community_channel_preferences(user_id,channel_id,watching,muted) VALUES(UNHEX(:user),:channel,:watching,:muted) ON DUPLICATE KEY UPDATE watching=VALUES(watching),muted=VALUES(muted)')->execute(['user'=>$userId,'channel'=>$channelId,'watching'=>$watching?1:0,'muted'=>$muted?1:0]);
        $this->audit()->append($organisationId,$userId,'community.channel_preference_saved','user',$userId,['channelId'=>$channelId,'watching'=>$watching,'muted'=>$muted]);
        return ['channelId'=>$channelId,'watching'=>$watching,'muted'=>$muted];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function saveChannel(string$organisationId,string$userId,string$membershipRole,array$input):array
    {
        if($this->member($organisationId,$userId)!==$membershipRole||!$this->isModerator($userId))throw new CommunityException('community_moderation_permission_denied');
        $id=$this->text($input['id']??null,80,'community_channel_required');if(preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',$id)!==1)throw new CommunityException('community_channel_id_invalid');
        $label=$this->text($input['label']??null,120,'community_channel_label_required');$description=$this->text($input['description']??null,500,'community_channel_description_required');$visibility=$this->choice($input['visibility']??'public',['public','role_restricted','invite_only','admin_only'],'community_channel_visibility_invalid');$policy=$this->choice($input['defaultModerationPolicy']??'standard',['standard','preapprove_all','admin_only'],'community_channel_policy_invalid');$roles=$this->roleList($input['eligibleRoles']??[]);if($visibility==='role_restricted'&&$roles===[])throw new CommunityException('community_channel_roles_required');$sort=max(1,min(65000,(int)($input['sortOrder']??500)));
        $this->pdo->beginTransaction();try{$this->pdo->prepare("INSERT INTO community_channels(id,label,description,sort_order,status) VALUES(:id,:label,:description,:sort,'active') ON DUPLICATE KEY UPDATE label=VALUES(label),description=VALUES(description),sort_order=VALUES(sort_order),status='active'")->execute(['id'=>$id,'label'=>$label,'description'=>$description,'sort'=>$sort]);$this->pdo->prepare('INSERT INTO community_channel_governance(channel_id,eligible_roles,visibility,default_moderation_policy) VALUES(:id,:roles,:visibility,:policy) ON DUPLICATE KEY UPDATE eligible_roles=VALUES(eligible_roles),visibility=VALUES(visibility),default_moderation_policy=VALUES(default_moderation_policy)')->execute(['id'=>$id,'roles'=>$roles==[]?null:$this->json($roles),'visibility'=>$visibility,'policy'=>$policy]);$this->audit()->append($organisationId,$userId,'community.channel_saved','user',$userId,['channelId'=>$id,'visibility'=>$visibility,'eligibleRoles'=>$roles,'defaultModerationPolicy'=>$policy]);$this->pdo->commit();return$this->channelRow($id);}catch(Throwable$e){$this->rollback();throw$e;}
    }

    /** @return array<string,mixed> */
    public function currentPreference(string $organisationId, string $userId): array
    {
        $this->member($organisationId, $userId);
        return $this->preference($organisationId, $userId);
    }

    /** @return array{canModerate:bool,canManageChannels:bool} */
    public function capabilities(string$organisationId,string$userId):array{$this->member($organisationId,$userId);$allowed=$this->isModerator($userId);return['canModerate'=>$allowed,'canManageChannels'=>$allowed];}

    /** @return list<array<string,mixed>> */
    public function notifications(string$organisationId,string$userId):array
    {
        $this->member($organisationId,$userId);$s=$this->pdo->prepare("SELECT LOWER(HEX(n.id)) id,LOWER(HEX(n.thread_id)) threadId,n.notification_type notificationType,n.priority,n.status,DATE_FORMAT(n.available_at,'%Y-%m-%dT%H:%i:%s.%fZ') availableAt,t.title FROM community_notification_outbox n JOIN community_threads t ON t.id=n.thread_id WHERE n.user_id=UNHEX(:user) AND n.status<>'cancelled' ORDER BY n.created_at DESC LIMIT 50");$s->execute(['user'=>$userId]);return$s->fetchAll();
    }

    /** @return array{id:string,status:string} */
    public function readNotification(string$organisationId,string$userId,string$notificationId):array
    {
        $this->member($organisationId,$userId);$notificationId=$this->id($notificationId,'community_thread_not_found');$s=$this->pdo->prepare("UPDATE community_notification_outbox SET status='read',delivered_at=COALESCE(delivered_at,UTC_TIMESTAMP(6)) WHERE id=UNHEX(:id) AND user_id=UNHEX(:user) AND status<>'cancelled'");$s->execute(['id'=>$notificationId,'user'=>$userId]);if($s->rowCount()!==1)throw new CommunityException('community_thread_not_found');return['id'=>$notificationId,'status'=>'read'];
    }

    /** @return array<string,mixed> */
    public function thread(string $organisationId, string $userId, string $threadId): array
    {
        return $this->threadFor($organisationId, $userId, $threadId, true);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createThread(string $organisationId, string $userId, string $membershipRole, array $input): array
    {
        $actualRole = $this->member($organisationId, $userId);
        if ($actualRole !== $membershipRole) throw new CommunityException('community_permission_denied');
        $moderator=$this->isModerator($userId);$this->assertRateLimit($userId,$moderator);
        $channel = $this->text($input['channelId'] ?? 'general_built_environment', 80, 'community_channel_required');
        $this->assertChannelAccess($organisationId,$userId,$channel);
        $category = $this->choice($input['category'] ?? null, self::CATEGORIES, 'community_category_invalid');
        $title = $this->text($input['title'] ?? null, 220, 'community_title_required');
        $body = $this->text($input['body'] ?? null, 12000, 'community_body_required');
        $mode = $this->choice($input['appShellMode'] ?? 'embedded_platform', ['embedded_platform','standalone_mobile'], 'community_app_shell_invalid');
        $audience = $this->audiencePolicy->normalise(is_array($input['audience'] ?? null) ? $input['audience'] : []);
        $status = $this->audiencePolicy->moderationStatus($category, $audience,$moderator);
        $tags = $this->strings($input['tags'] ?? [], 20, 60, 'community_tags_invalid');
        $id = Identifier::generate();
        $this->pdo->beginTransaction();
        try {
            $attachments=$this->attachmentIds($organisationId,$input['attachmentIds']??[]);
            $this->pdo->prepare('INSERT INTO community_threads(id,author_organisation_id,author_user_id,channel_id,category,title,body,tags,audience,moderation_status,visibility_status,app_shell_mode) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:channel,:category,:title,:body,:tags,:audience,:moderation,:visibility,:mode)')->execute([
                'id'=>$id,'organisation'=>$organisationId,'user'=>$userId,'channel'=>$channel,'category'=>$category,'title'=>$title,'body'=>$body,
                'tags'=>$this->json($tags),'audience'=>$this->json($audience),'moderation'=>$status,'visibility'=>$status === 'approved' ? 'visible' : 'pending','mode'=>$mode,
            ]);
            $this->saveAttachmentLinks('community_thread_attachments','thread_id',$id,$attachments);
            $recipientCount = $status === 'approved' ? $this->resolveAndSnapshot($id, $userId, $audience,$channel) : 0;
            if($status==='approved')$this->notifyMentions($id,$userId,$input['mentionUserIds']??[]);
            if ($status === 'pending_approval') {
                $this->pdo->prepare("INSERT INTO community_notifications(id,thread_id,user_id,notification_type,priority) VALUES(UNHEX(:id),UNHEX(:thread),NULL,'moderation_required','high')")->execute(['id'=>Identifier::generate(),'thread'=>$id]);
                $this->moderationEvent($id, $organisationId, $userId, 'submitted', null);
            }
            $this->pdo->prepare('UPDATE community_threads SET recipient_count=:count WHERE id=UNHEX(:id)')->execute(['count'=>$recipientCount,'id'=>$id]);
            $this->audit()->append($organisationId, $userId, 'community.thread_created', 'community_thread', $id, ['channelId'=>$channel,'category'=>$category,'audience'=>$audience,'moderationStatus'=>$status,'recipientCount'=>$recipientCount,'appShellMode'=>$mode]);
            $this->pdo->commit();
            return $this->threadFor($organisationId, $userId, $id, true);
        } catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function feed(string $organisationId, string $userId, array $filters = []): array
    {
        $this->member($organisationId, $userId);$moderator=$this->isModerator($userId);
        $sql = "SELECT DISTINCT LOWER(HEX(t.id)) id FROM community_threads t LEFT JOIN community_thread_recipients r ON r.thread_id=t.id AND r.user_id=UNHEX(:recipient_user) LEFT JOIN community_saved_threads s ON s.thread_id=t.id AND s.user_id=UNHEX(:saved_user) WHERE t.visibility_status<>'hidden' AND ((t.moderation_status='approved' AND r.user_id IS NOT NULL) OR t.author_user_id=UNHEX(:author_user)" . ($moderator ? " OR t.moderation_status='pending_approval'" : '') . ')';
        $params = ['recipient_user'=>$userId,'saved_user'=>$userId,'author_user'=>$userId];
        if (is_string($filters['channelId'] ?? null) && $filters['channelId'] !== '') { $sql .= ' AND t.channel_id=:channel'; $params['channel'] = $filters['channelId']; }
        if (is_string($filters['category'] ?? null) && $filters['category'] !== '') { $sql .= ' AND t.category=:category'; $params['category'] = $filters['category']; }
        if (is_string($filters['search'] ?? null) && trim($filters['search']) !== '') { $search='%'.$this->text($filters['search'],120,'community_search_invalid').'%';$sql.=' AND (t.title LIKE :search_title OR t.body LIKE :search_body)';$params['search_title']=$search;$params['search_body']=$search; }
        if (($filters['saved']??false)===true) $sql.=' AND s.user_id IS NOT NULL';
        $sql .= ' ORDER BY t.created_at DESC,t.id DESC LIMIT 200';
        $statement = $this->pdo->prepare($sql); $statement->execute($params);
        return array_map(fn(string $id): array => $this->threadFor($organisationId, $userId, $id, true), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array{threadId:string,saved:bool} */
    public function saveThread(string $organisationId,string $userId,string $threadId,bool $saved):array
    {
        $this->member($organisationId,$userId);$this->threadFor($organisationId,$userId,$threadId,false);
        if($saved)$this->pdo->prepare('INSERT IGNORE INTO community_saved_threads(user_id,thread_id) VALUES(UNHEX(:user),UNHEX(:thread))')->execute(['user'=>$userId,'thread'=>$threadId]);
        else $this->pdo->prepare('DELETE FROM community_saved_threads WHERE user_id=UNHEX(:user) AND thread_id=UNHEX(:thread)')->execute(['user'=>$userId,'thread'=>$threadId]);
        return ['threadId'=>$threadId,'saved'=>$saved];
    }

    /** @return list<array<string,mixed>> */
    public function moderationQueue(string $organisationId,string $userId):array
    {
        $this->member($organisationId,$userId);if(!$this->isModerator($userId))throw new CommunityException('community_moderation_permission_denied');
        $ids=$this->pdo->query("SELECT LOWER(HEX(t.id)) FROM community_threads t WHERE t.moderation_status='pending_approval' OR EXISTS(SELECT 1 FROM community_reports r WHERE r.thread_id=t.id AND r.status='open') ORDER BY t.created_at")->fetchAll(PDO::FETCH_COLUMN);
        return array_map(fn(string$id):array=>$this->threadFor($organisationId,$userId,$id,true),$ids);
    }

    /** @return array<string,mixed> */
    public function moderate(string$organisationId,string$userId,string$membershipRole,string$threadId,string$action,string$note):array
    {
        if($this->member($organisationId,$userId)!==$membershipRole||!$this->isModerator($userId))throw new CommunityException('community_moderation_permission_denied');
        $action=$this->choice($action,['approved','rejected','hidden','restored'],'community_moderation_action_invalid');$note=$this->text($note,1000,'community_moderation_note_required');$thread=$this->threadRow($threadId);
        if($action==='approved')return$this->approve($organisationId,$userId,$membershipRole,$threadId,$note);
        $nextStatus=$action==='restored'?'approved':$action;$visibility=$action==='restored'?'visible':($action==='rejected'?'hidden':'hidden');
        $this->pdo->beginTransaction();try{
            $this->pdo->prepare('UPDATE community_threads SET moderation_status=:status,visibility_status=:visibility,recipient_count=IF(:clear=1,0,recipient_count),reviewed_by=UNHEX(:reviewer),review_note=:note,reviewed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:thread)')->execute(['status'=>$nextStatus,'visibility'=>$visibility,'clear'=>$action==='rejected'?1:0,'reviewer'=>$userId,'note'=>$note,'thread'=>$threadId]);
            if($action==='rejected'){$this->pdo->prepare('DELETE FROM community_thread_recipients WHERE thread_id=UNHEX(:thread)')->execute(['thread'=>$threadId]);$this->pdo->prepare("UPDATE community_notifications SET status='cancelled' WHERE thread_id=UNHEX(:thread) AND status='pending'")->execute(['thread'=>$threadId]);$this->pdo->prepare("UPDATE community_notification_outbox SET status='cancelled' WHERE thread_id=UNHEX(:thread) AND status='pending'")->execute(['thread'=>$threadId]);}
            $this->pdo->prepare("UPDATE community_reports SET status='actioned' WHERE thread_id=UNHEX(:thread) AND status='open'")->execute(['thread'=>$threadId]);
            $this->moderationEvent($threadId,$organisationId,$userId,$action,$note);$this->audit()->append($organisationId,$userId,'community.thread_moderated','community_thread',$threadId,['action'=>$action,'note'=>$note]);$this->pdo->commit();return$this->threadFor($organisationId,$userId,$threadId,true);
        }catch(Throwable$e){$this->rollback();throw$e;}
    }

    /** @return array{delivered:int,remaining:int} */
    public function deliverDueNotifications(int$limit=500):array
    {
        $limit=max(1,min(1000,$limit));$this->pdo->beginTransaction();try{$ids=$this->pdo->query("SELECT LOWER(HEX(id)) FROM community_notification_outbox WHERE status='pending' AND available_at<=UTC_TIMESTAMP(6) ORDER BY available_at,id LIMIT ".$limit." FOR UPDATE SKIP LOCKED")->fetchAll(PDO::FETCH_COLUMN);if($ids!==[]){$placeholders=implode(',',array_fill(0,count($ids),'UNHEX(?)'));$s=$this->pdo->prepare("UPDATE community_notification_outbox SET status='delivered',delivered_at=UTC_TIMESTAMP(6) WHERE id IN ($placeholders) AND status='pending'");$s->execute($ids);} $remaining=(int)$this->pdo->query("SELECT COUNT(*) FROM community_notification_outbox WHERE status='pending'")->fetchColumn();$this->pdo->commit();return['delivered'=>count($ids),'remaining'=>$remaining];}catch(Throwable$e){$this->rollback();throw$e;}
    }

    /** @return array<string,mixed> */
    public function approve(string $organisationId, string $userId, string $membershipRole, string $threadId, string $note): array
    {
        if ($this->member($organisationId, $userId) !== $membershipRole || !$this->isModerator($userId)) throw new CommunityException('community_moderation_permission_denied');
        $thread = $this->threadRow($threadId);
        if ($thread['moderationStatus'] !== 'pending_approval') throw new CommunityException('community_thread_not_pending');
        $note = $this->text($note, 1000, 'community_moderation_note_required');
        $audience = $thread['audience'];
        $this->pdo->beginTransaction();
        try {
            $count = $this->resolveAndSnapshot($threadId, (string) $thread['authorUserId'], $audience,(string)$thread['channelId']);
            $this->pdo->prepare("UPDATE community_threads SET moderation_status='approved',visibility_status='visible',recipient_count=:count,reviewed_by=UNHEX(:reviewer),review_note=:note,reviewed_at=UTC_TIMESTAMP(6) WHERE id=UNHEX(:id) AND moderation_status='pending_approval'")->execute(['count'=>$count,'reviewer'=>$userId,'note'=>$note,'id'=>$threadId]);
            $this->pdo->prepare("UPDATE community_notifications SET status='cancelled' WHERE thread_id=UNHEX(:thread) AND notification_type='moderation_required' AND status='pending'")->execute(['thread'=>$threadId]);
            $this->moderationEvent($threadId, $organisationId, $userId, 'approved', $note);
            $this->audit()->append($organisationId, $userId, 'community.thread_approved', 'community_thread', $threadId, ['recipientCount'=>$count,'note'=>$note]);
            $this->pdo->commit();
            return $this->threadFor($organisationId, $userId, $threadId, true);
        } catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    /** @return array<string,mixed> */
    public function reply(string $organisationId, string $userId, string $membershipRole, string $threadId, string $body, array $attachmentIds=[], array $mentionUserIds=[]): array
    {
        if ($this->member($organisationId, $userId) !== $membershipRole) throw new CommunityException('community_permission_denied');
        $this->threadFor($organisationId, $userId, $threadId, false);
        $body = $this->text($body, 12000, 'community_reply_body_required');$attachments=$this->attachmentIds($organisationId,$attachmentIds); $id = Identifier::generate();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO community_replies(id,thread_id,author_organisation_id,author_user_id,body) VALUES(UNHEX(:id),UNHEX(:thread),UNHEX(:organisation),UNHEX(:user),:body)')->execute(['id'=>$id,'thread'=>$threadId,'organisation'=>$organisationId,'user'=>$userId,'body'=>$body]);
            $this->saveAttachmentLinks('community_reply_attachments','reply_id',$id,$attachments);
            $this->pdo->prepare('UPDATE community_threads SET reply_count=reply_count+1 WHERE id=UNHEX(:id)')->execute(['id'=>$threadId]);
            $this->pdo->prepare("INSERT INTO community_notifications(id,thread_id,user_id,notification_type,priority) SELECT UNHEX(REPLACE(UUID(),'-','')),UNHEX(:notification_thread),r.user_id,'reply','medium' FROM community_thread_recipients r WHERE r.thread_id=UNHEX(:recipient_thread) AND r.user_id<>UNHEX(:actor)")->execute(['notification_thread'=>$threadId,'recipient_thread'=>$threadId,'actor'=>$userId]);
            $this->notifyMentions($threadId,$userId,$mentionUserIds);
            $this->audit()->append($organisationId, $userId, 'community.reply_created', 'community_reply', $id, ['threadId'=>$threadId]);
            $this->pdo->commit();
            return $this->replyRow($id);
        } catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    /** @return array<string,mixed> */
    public function report(string $organisationId, string $userId, string $threadId, string $reason, string $detail): array
    {
        $this->member($organisationId, $userId); $this->threadFor($organisationId, $userId, $threadId, false);
        $reason = $this->choice($reason, ['spam','abuse','privacy','misinformation','commercial_solicitation','other'], 'community_report_reason_invalid');
        $detail = $this->text($detail, 1000, 'community_report_detail_required'); $id = Identifier::generate();
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO community_reports(id,thread_id,reporter_organisation_id,reporter_user_id,reason,detail) VALUES(UNHEX(:id),UNHEX(:thread),UNHEX(:organisation),UNHEX(:user),:reason,:detail)')->execute(['id'=>$id,'thread'=>$threadId,'organisation'=>$organisationId,'user'=>$userId,'reason'=>$reason,'detail'=>$detail]);
            $this->pdo->prepare('UPDATE community_threads SET report_count=report_count+1 WHERE id=UNHEX(:id)')->execute(['id'=>$threadId]);
            $this->pdo->prepare("INSERT INTO community_notifications(id,thread_id,user_id,notification_type,priority) VALUES(UNHEX(:id),UNHEX(:thread),NULL,'moderation_required','high')")->execute(['id'=>Identifier::generate(),'thread'=>$threadId]);
            $this->audit()->append($organisationId, $userId, 'community.thread_reported', 'community_thread', $threadId, ['reportId'=>$id,'reason'=>$reason]);
            $this->pdo->commit();
            return ['id'=>$id,'threadId'=>$threadId,'reason'=>$reason,'detail'=>$detail,'status'=>'open'];
        } catch (PDOException $e) { $this->rollback(); if ($e->getCode() === '23000') throw new CommunityException('community_report_exists'); throw $e; }
        catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function convert(string $organisationId, string $userId, string $membershipRole, string $threadId, array $input): array
    {
        if ($this->member($organisationId, $userId) !== $membershipRole) throw new CommunityException('community_permission_denied');
        if (($input['humanConfirmed'] ?? false) !== true) throw new CommunityException('community_conversion_confirmation_required');
        $thread = $this->threadFor($organisationId, $userId, $threadId, false);
        $type = $this->choice($input['conversionType'] ?? null, self::CONVERSIONS, 'community_conversion_type_invalid');
        $title = $this->text($input['title'] ?? null, 220, 'community_conversion_title_required');
        $projectId = $input['projectId'] ?? null;
        if ($projectId !== null) { $projectId = $this->id($projectId, 'community_project_invalid'); if (!$this->exists('SELECT 1 FROM projects WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation)', ['id'=>$projectId,'organisation'=>$organisationId])) throw new CommunityException('community_project_not_found'); }
        $id = Identifier::generate(); $context = is_array($input['targetContext'] ?? null) ? $input['targetContext'] : [];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO community_conversions(id,thread_id,organisation_id,project_id,approved_by,conversion_type,title,target_context) VALUES(UNHEX(:id),UNHEX(:thread),UNHEX(:organisation),UNHEX(:project),UNHEX(:user),:type,:title,:context)')->execute(['id'=>$id,'thread'=>$threadId,'organisation'=>$organisationId,'project'=>$projectId,'user'=>$userId,'type'=>$type,'title'=>$title,'context'=>$this->json($context+['sourceContext'=>'community_informal','sourceTitle'=>$thread['title'],'requiresFinalReview'=>true])]);
            $this->audit()->append($organisationId, $userId, 'community.thread_converted_to_draft', 'community_conversion', $id, ['threadId'=>$threadId,'conversionType'=>$type,'projectId'=>$projectId,'humanConfirmed'=>true]);
            $this->pdo->commit();
            return ['id'=>$id,'threadId'=>$threadId,'organisationId'=>$organisationId,'projectId'=>$projectId,'conversionType'=>$type,'title'=>$title,'status'=>'draft','requiresFinalReview'=>true];
        } catch (PDOException $e) { $this->rollback(); if ($e->getCode() === '23000') throw new CommunityException('community_conversion_exists'); throw $e; }
        catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    /** @param array<string,mixed> $audience */
    private function resolveAndSnapshot(string $threadId, string $senderId, array $audience,string $channelId): int
    {
        $statement=$this->pdo->prepare("SELECT LOWER(HEX(p.user_id)) id,LOWER(HEX(p.home_organisation_id)) organisationId,p.professional_role role,p.region,p.verification_status verificationStatus,p.opted_in optedIn,p.blocked_user_ids blockedUserIds,p.muted_roles mutedRoles,p.digest_mode digestMode,COALESCE(cp.watching,0) watching,COALESCE(cp.muted,0) channelMuted,(u.status='active' AND o.status='active' AND m.status='active') active FROM community_preferences p JOIN users u ON u.id=p.user_id JOIN organisations o ON o.id=p.home_organisation_id JOIN organisation_memberships m ON m.organisation_id=p.home_organisation_id AND m.user_id=p.user_id LEFT JOIN community_channel_preferences cp ON cp.user_id=p.user_id AND cp.channel_id=:channel");$statement->execute(['channel'=>$channelId]);$rows=$statement->fetchAll();
        $channel=$this->channelRow($channelId);$eligible=$channel['eligibleRoles'];
        $candidates = array_values(array_filter(array_map(function(array $row): array { $row['active']=(bool)$row['active']; $row['optedIn']=(bool)$row['optedIn'];$row['watching']=(bool)$row['watching'];$row['channelMuted']=(bool)$row['channelMuted']; $row['blockedUserIds']=$this->decode($row['blockedUserIds']); $row['mutedRoles']=$this->decode($row['mutedRoles']); return $row; }, $rows),fn(array$row):bool=>$eligible===[]||in_array($row['role'],$eligible,true)));
        $recipients = $this->audiencePolicy->resolve($candidates, $audience, $senderId);
        $insert = $this->pdo->prepare('INSERT IGNORE INTO community_thread_recipients(thread_id,user_id,organisation_id,professional_role) VALUES(UNHEX(:thread),UNHEX(:user),UNHEX(:organisation),:role)');
        $notify = $this->pdo->prepare("INSERT INTO community_notification_outbox(id,thread_id,user_id,notification_type,priority,available_at) VALUES(UNHEX(:id),UNHEX(:thread),UNHEX(:user),:type,:priority,:available)");
        foreach ($recipients as $recipient) { $insert->execute(['thread'=>$threadId,'user'=>$recipient['id'],'organisation'=>$recipient['organisationId'],'role'=>$recipient['role']]);if($recipient['id']===$senderId||$recipient['channelMuted']||$recipient['digestMode']==='none')continue;$immediate=$recipient['watching']||$recipient['digestMode']==='immediate';$delay=$immediate?0:($recipient['digestMode']==='weekly'?7:1);$available=(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('+'.$delay.' days')->format('Y-m-d H:i:s.u');$notify->execute(['id'=>Identifier::generate(),'thread'=>$threadId,'user'=>$recipient['id'],'type'=>$immediate?'watched_channel_post':'community_digest','priority'=>$immediate?'medium':'digest','available'=>$available]); }
        return count($recipients);
    }

    /** @return array<string,mixed> */
    private function threadFor(string $organisationId, string $userId, string $threadId, bool $allowModerator): array
    {
        $role = $this->member($organisationId, $userId); $row = $this->threadRow($threadId);
        $visible = $row['authorUserId'] === $userId || $this->exists('SELECT 1 FROM community_thread_recipients WHERE thread_id=UNHEX(:thread) AND user_id=UNHEX(:user)', ['thread'=>$threadId,'user'=>$userId]) || ($allowModerator && $this->isModerator($userId));
        if (!$visible) throw new CommunityException('community_thread_not_found');
        $statement = $this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(author_user_id)) authorUserId,body,moderation_status moderationStatus,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') createdAt FROM community_replies WHERE thread_id=UNHEX(:thread) AND moderation_status='visible' ORDER BY created_at,id");
        $statement->execute(['thread'=>$threadId]); $row['replies']=array_map(function(array$reply):array{$reply['attachments']=$this->attachmentLinks('community_reply_attachments','reply_id',$reply['id']);return$reply;},$statement->fetchAll());$row['saved']=$this->exists('SELECT 1 FROM community_saved_threads WHERE user_id=UNHEX(:user) AND thread_id=UNHEX(:thread)',['user'=>$userId,'thread'=>$threadId]);
        return $row;
    }

    /** @return array<string,mixed> */
    private function threadRow(string $threadId): array
    {
        $threadId=$this->id($threadId,'community_thread_not_found');
        $s=$this->pdo->prepare("SELECT LOWER(HEX(t.id)) id,LOWER(HEX(t.author_organisation_id)) authorOrganisationId,LOWER(HEX(t.author_user_id)) authorUserId,COALESCE(p.professional_role,'unconfigured') authorRole,t.channel_id channelId,t.category,t.title,t.body,t.tags,t.audience,t.moderation_status moderationStatus,t.visibility_status visibilityStatus,t.context_type contextType,t.app_shell_mode appShellMode,t.recipient_count recipientCount,t.reply_count replyCount,t.helpful_count helpfulCount,t.report_count reportCount,LOWER(HEX(t.reviewed_by)) reviewedBy,t.review_note reviewNote,DATE_FORMAT(t.created_at,'%Y-%m-%dT%H:%i:%s.%fZ') createdAt FROM community_threads t LEFT JOIN community_preferences p ON p.user_id=t.author_user_id WHERE t.id=UNHEX(:id)"); $s->execute(['id'=>$threadId]); $row=$s->fetch();
        if (!is_array($row)) throw new CommunityException('community_thread_not_found');
        foreach (['tags','audience'] as $key) $row[$key]=$this->decode($row[$key]);$row['attachments']=$this->attachmentLinks('community_thread_attachments','thread_id',$threadId);
        foreach (['recipientCount','replyCount','helpfulCount','reportCount'] as $key) $row[$key]=(int)$row[$key];
        $row['usesSharedCommunityBackend']=true; $row['formalWorkflowConversionRequired']=true;
        return $row;
    }

    /** @return array<string,mixed> */
    private function replyRow(string $id): array { $s=$this->pdo->prepare("SELECT LOWER(HEX(id)) id,LOWER(HEX(thread_id)) threadId,LOWER(HEX(author_organisation_id)) authorOrganisationId,LOWER(HEX(author_user_id)) authorUserId,body,moderation_status moderationStatus,DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s.%fZ') createdAt FROM community_replies WHERE id=UNHEX(:id)"); $s->execute(['id'=>$id]); $row=$s->fetch(); if(!is_array($row))throw new CommunityException('community_reply_not_found');$row['attachments']=$this->attachmentLinks('community_reply_attachments','reply_id',$id); return$row; }
    /** @return array<string,mixed> */
    private function preference(string $organisationId,string $userId):array{$s=$this->pdo->prepare('SELECT LOWER(HEX(user_id)) userId,LOWER(HEX(home_organisation_id)) homeOrganisationId,professional_role role,region,verification_status verificationStatus,opted_in optedIn,blocked_user_ids blockedUserIds,muted_roles mutedRoles,digest_mode digestMode FROM community_preferences WHERE user_id=UNHEX(:user) AND home_organisation_id=UNHEX(:organisation)');$s->execute(['user'=>$userId,'organisation'=>$organisationId]);$r=$s->fetch();if(!is_array($r))throw new CommunityException('community_preference_not_found');$r['optedIn']=(bool)$r['optedIn'];$r['blockedUserIds']=$this->decode($r['blockedUserIds']);$r['mutedRoles']=$this->decode($r['mutedRoles']);return$r;}
    private function moderationEvent(string $threadId,string $organisationId,string $userId,string $action,?string $note):void{$this->pdo->prepare('INSERT INTO community_moderation_events(id,thread_id,actor_organisation_id,actor_user_id,action,note) VALUES(UNHEX(:id),UNHEX(:thread),UNHEX(:organisation),UNHEX(:user),:action,:note)')->execute(['id'=>Identifier::generate(),'thread'=>$threadId,'organisation'=>$organisationId,'user'=>$userId,'action'=>$action,'note'=>$note]);}
    /** @return array<string,mixed> */private function channelRow(string$channelId):array{$channelId=$this->text($channelId,80,'community_channel_required');$s=$this->pdo->prepare("SELECT c.id,c.label,c.description,g.eligible_roles eligibleRoles,COALESCE(g.visibility,'public') visibility,COALESCE(g.default_moderation_policy,'standard') defaultModerationPolicy FROM community_channels c LEFT JOIN community_channel_governance g ON g.channel_id=c.id WHERE c.id=:id AND c.status='active'");$s->execute(['id'=>$channelId]);$row=$s->fetch();if(!is_array($row))throw new CommunityException('community_channel_not_found');$row['eligibleRoles']=$row['eligibleRoles']===null?[]:$this->decode($row['eligibleRoles']);return$row;}
    private function assertChannelAccess(string$organisationId,string$userId,string$channelId):void{$channel=$this->channelRow($channelId);$membership=$this->member($organisationId,$userId);if($membership==='admin')return;if($channel['visibility']==='public')return;if($channel['visibility']==='admin_only')throw new CommunityException('community_channel_forbidden');$s=$this->pdo->prepare('SELECT professional_role FROM community_preferences WHERE user_id=UNHEX(:user) AND home_organisation_id=UNHEX(:organisation) AND opted_in=1');$s->execute(['user'=>$userId,'organisation'=>$organisationId]);$role=$s->fetchColumn();if(!is_string($role)||!in_array($role,$channel['eligibleRoles'],true))throw new CommunityException('community_channel_forbidden');}
    /** @return list<string> */private function attachmentIds(string$organisationId,mixed$value):array{$ids=$this->idsLimited($value,8,'community_attachments_invalid');foreach($ids as$id)if(!$this->exists("SELECT 1 FROM stored_files WHERE id=UNHEX(:id) AND organisation_id=UNHEX(:organisation) AND status='active'",['id'=>$id,'organisation'=>$organisationId]))throw new CommunityException('community_attachment_not_found');return$ids;}
    /** @param list<string> $ids */private function saveAttachmentLinks(string$table,string$column,string$parentId,array$ids):void{if(!in_array([$table,$column],[['community_thread_attachments','thread_id'],['community_reply_attachments','reply_id']],true))throw new CommunityException('community_attachments_invalid');$s=$this->pdo->prepare("INSERT INTO $table($column,file_id) VALUES(UNHEX(:parent),UNHEX(:file))");foreach($ids as$id)$s->execute(['parent'=>$parentId,'file'=>$id]);}
    /** @return list<string> */private function attachmentLinks(string$table,string$column,string$parentId):array{if(!in_array([$table,$column],[['community_thread_attachments','thread_id'],['community_reply_attachments','reply_id']],true))throw new CommunityException('community_attachments_invalid');$s=$this->pdo->prepare("SELECT LOWER(HEX(file_id)) FROM $table WHERE $column=UNHEX(:parent) ORDER BY file_id");$s->execute(['parent'=>$parentId]);return$s->fetchAll(PDO::FETCH_COLUMN);}
    private function notifyMentions(string$threadId,string$senderId,mixed$value):void{$ids=$this->idsLimited($value,10,'community_mentions_invalid');if($ids===[])return;$check=$this->pdo->prepare('SELECT 1 FROM community_thread_recipients WHERE thread_id=UNHEX(:thread) AND user_id=UNHEX(:user)');$insert=$this->pdo->prepare("INSERT INTO community_notification_outbox(id,thread_id,user_id,notification_type,priority,available_at) VALUES(UNHEX(:id),UNHEX(:thread),UNHEX(:user),'direct_mention','high',UTC_TIMESTAMP(6))");foreach($ids as$id){if($id===$senderId)continue;$check->execute(['thread'=>$threadId,'user'=>$id]);if($check->fetchColumn()===false)throw new CommunityException('community_mention_recipient_invalid');$insert->execute(['id'=>Identifier::generate(),'thread'=>$threadId,'user'=>$id]);}}
    private function assertRateLimit(string$userId,bool$isModerator):void{if($isModerator)return;$s=$this->pdo->prepare("SELECT COUNT(*) FROM community_threads WHERE author_user_id=UNHEX(:user) AND created_at>=UTC_TIMESTAMP(6)-INTERVAL 15 MINUTE");$s->execute(['user'=>$userId]);if((int)$s->fetchColumn()>=5)throw new CommunityException('community_rate_limit_exceeded');}
    private function isModerator(string$userId):bool{return$this->exists("SELECT 1 FROM community_platform_moderators WHERE user_id=UNHEX(:user) AND status='active'",['user'=>$userId]);}
    private function member(string$organisationId,string$userId):string{$organisationId=$this->id($organisationId,'community_permission_denied');$userId=$this->id($userId,'community_permission_denied');$s=$this->pdo->prepare("SELECT m.role FROM organisation_memberships m JOIN organisations o ON o.id=m.organisation_id JOIN users u ON u.id=m.user_id WHERE m.organisation_id=UNHEX(:organisation) AND m.user_id=UNHEX(:user) AND m.status='active' AND o.status='active' AND u.status='active'");$s->execute(['organisation'=>$organisationId,'user'=>$userId]);$role=$s->fetchColumn();if(!is_string($role))throw new CommunityException('community_permission_denied');return$role;}
    /** @param array<string,mixed> $params */private function exists(string$sql,array$params):bool{$s=$this->pdo->prepare($sql);$s->execute($params);return$s->fetchColumn()!==false;}
    private function audit():AuditService{return new AuditService($this->pdo);}
    private function rollback():void{if($this->pdo->inTransaction())$this->pdo->rollBack();}
    private function id(mixed$value,string$reason):string{if(!is_string($value)||preg_match('/\A[a-f0-9]{32}\z/i',$value)!==1)throw new CommunityException($reason);return strtolower($value);}
    private function text(mixed$value,int$max,string$reason):string{$value=is_string($value)?trim($value):'';if($value===''||mb_strlen($value)>$max)throw new CommunityException($reason);return$value;}
    private function nullableText(mixed$value,int$max):?string{if($value===null||$value==='')return null;if(!is_string($value)||mb_strlen(trim($value))>$max)throw new CommunityException('community_text_invalid');return trim($value);}
    /** @param list<string> $allowed */private function choice(mixed$value,array$allowed,string$reason):string{if(!is_string($value)||!in_array($value,$allowed,true))throw new CommunityException($reason);return$value;}
    /** @return list<string> */private function ids(mixed$value,string$reason):array{if(!is_array($value))throw new CommunityException($reason);return array_values(array_unique(array_map(fn(mixed$id):string=>$this->id($id,$reason),$value)));}
    /** @return list<string> */private function idsLimited(mixed$value,int$limit,string$reason):array{if(!is_array($value)||count($value)>$limit)throw new CommunityException($reason);return array_values(array_unique(array_map(fn(mixed$id):string=>$this->id($id,$reason),$value)));}
    /** @return list<string> */private function roleList(mixed$value):array{if(!is_array($value))throw new CommunityException('community_roles_invalid');if($value===[])return[];return$this->audiencePolicy->normalise(['scope'=>'selected_roles','targetRoles'=>$value])['targetRoles'];}
    /** @return list<string> */private function strings(mixed$value,int$maxItems,int$maxLength,string$reason):array{if(!is_array($value)||count($value)>$maxItems)throw new CommunityException($reason);$out=[];foreach($value as$item)$out[]=$this->text($item,$maxLength,$reason);return array_values(array_unique($out));}
    /** @param array<string,mixed>|list<mixed> $value */private function json(array$value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    /** @return array<mixed> */private function decode(mixed$value):array{$decoded=json_decode((string)$value,true,64,JSON_THROW_ON_ERROR);return is_array($decoded)?$decoded:[];}
}
