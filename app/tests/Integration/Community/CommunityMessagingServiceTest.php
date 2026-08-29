<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Community;

use Architex\Platform\Community\CommunityException;
use Architex\Platform\Community\CommunityMessagingService;
use Architex\Platform\Identity\Identifier;
use Architex\Tests\Support\MariaDbTestCase;

final class CommunityMessagingServiceTest extends MariaDbTestCase
{
    public function testProductionCommunityControlsPersistSearchSaveWatchDigestAndModerationDecisions(): void
    {
        [$organisation, $owner, $admin] = $this->seedOrganisation('community-controls');
        $this->grantModerator($admin);
        $service = new CommunityMessagingService($this->pdo);
        $service->savePreference($organisation, $owner, ['role'=>'supplier','region'=>'Western Cape','verificationStatus'=>'verified','optedIn'=>true,'digestMode'=>'daily']);
        $custom=$service->saveChannel($organisation,$admin,'admin',['id'=>'low-carbon-products','label'=>'Low-carbon products','description'=>'Governed product-data discussion.','visibility'=>'role_restricted','eligibleRoles'=>['supplier','architect'],'defaultModerationPolicy'=>'standard','sortOrder'=>165]);
        self::assertSame('role_restricted',$custom['visibility']);

        $channelIds = array_column($service->channels($organisation, $owner), 'id');
        self::assertNotContains('role-architects', $channelIds);
        self::assertContains('role-suppliers', $channelIds);
        self::assertContains('low-carbon-products',$channelIds);
        self::assertTrue($service->saveChannelPreference($organisation, $owner, 'role-suppliers', true, false)['watching']);

        $thread = $service->createThread($organisation, $owner, 'owner', [
            'channelId'=>'role-suppliers','category'=>'product_or_material_query','title'=>'Low carbon masonry suppliers',
            'body'=>'Share product data sheets and delivery regions; do not post personal contact details.',
            'audience'=>['scope'=>'selected_roles','targetRoles'=>['supplier']],
        ]);
        self::assertCount(1, $service->feed($organisation, $owner, ['search'=>'carbon masonry']));
        self::assertTrue($service->saveThread($organisation, $owner, $thread['id'], true)['saved']);
        self::assertCount(1, $service->feed($organisation, $owner, ['saved'=>true]));

        $pending = $service->createThread($organisation, $owner, 'owner', [
            'channelId'=>'announcements','category'=>'admin_notice','title'=>'Moderated commercial announcement',
            'body'=>'This announcement requires a recorded moderation decision.',
            'audience'=>['scope'=>'all_community'],
        ]);
        self::assertCount(1, $service->moderationQueue($organisation, $admin));
        $rejected = $service->moderate($organisation, $admin, 'admin', $pending['id'], 'rejected', 'Commercial announcements are not allowed in this channel.');
        self::assertSame('rejected', $rejected['moderationStatus']);
        self::assertSame(0, $rejected['recipientCount']);

        $summary = $service->deliverDueNotifications(100);
        self::assertArrayHasKey('delivered', $summary);
        self::assertGreaterThanOrEqual(1, (int)$this->pdo->query("SELECT COUNT(*) FROM community_moderation_events WHERE action='rejected'")->fetchColumn());
    }

    public function testAudienceModerationConversationReportingAndHumanControlledConversionPersistAcrossOrganisations(): void
    {
        if (!class_exists(CommunityMessagingService::class)) self::fail('CommunityMessagingService is not implemented.');
        [$authorOrg, $author, $moderator, $project] = $this->seedOrganisation('community-author');
        $this->grantModerator($moderator);
        [$recipientOrg, $recipient] = $this->seedOrganisation('community-recipient');
        [$excludedOrg, $excluded] = $this->seedOrganisation('community-excluded');
        $service = new CommunityMessagingService($this->pdo);

        $service->savePreference($authorOrg, $author, ['role' => 'architect', 'region' => 'Gauteng', 'verificationStatus' => 'verified', 'optedIn' => true]);
        $service->savePreference($recipientOrg, $recipient, ['role' => 'engineer', 'region' => 'Gauteng', 'verificationStatus' => 'verified', 'optedIn' => true]);
        $service->savePreference($excludedOrg, $excluded, ['role' => 'supplier', 'region' => 'Gauteng', 'verificationStatus' => 'verified', 'optedIn' => false]);

        self::assertGreaterThanOrEqual(8, count($service->channels($authorOrg, $author)));
        $thread = $service->createThread($authorOrg, $author, 'owner', [
            'channelId' => 'general_built_environment', 'category' => 'general_question', 'title' => 'Engineer coordination question',
            'body' => 'Which information should accompany the structural coordination issue?', 'appShellMode' => 'embedded_platform',
            'audience' => ['scope' => 'selected_roles', 'targetRoles' => ['architect', 'engineer'], 'targetRegions' => ['Gauteng'], 'verificationStatus' => 'verified'],
        ]);
        self::assertSame('approved', $thread['moderationStatus']);
        self::assertSame(2, $thread['recipientCount']);
        self::assertCount(1, $service->feed($recipientOrg, $recipient));
        self::assertCount(0, $service->feed($excludedOrg, $excluded));

        $pending = $service->createThread($authorOrg, $author, 'owner', [
            'channelId' => 'announcements', 'category' => 'admin_notice', 'title' => 'Whole community notice',
            'body' => 'This must be reviewed before it is visible to the whole community.', 'appShellMode' => 'standalone_mobile',
            'audience' => ['scope' => 'all_community'],
        ]);
        self::assertSame('pending_approval', $pending['moderationStatus']);
        self::assertCount(1, $service->feed($recipientOrg, $recipient));
        $approved = $service->approve($authorOrg, $moderator, 'admin', $pending['id'], 'Approved for community distribution.');
        self::assertSame('approved', $approved['moderationStatus']);
        self::assertCount(2, $service->feed($recipientOrg, $recipient));

        $reply = $service->reply($recipientOrg, $recipient, 'owner', $thread['id'], 'Provide grid references, dimensions and the current revision.');
        self::assertSame($recipient, $reply['authorUserId']);
        $report = $service->report($recipientOrg, $recipient, $thread['id'], 'privacy', 'Potential personal information in the post.');
        self::assertSame('open', $report['status']);

        try {
            $service->convert($authorOrg, $author, 'owner', $thread['id'], ['conversionType' => 'rfi', 'title' => 'Structural coordination information', 'projectId' => $project, 'humanConfirmed' => false]);
            self::fail('Community conversion must require a human confirmation.');
        } catch (CommunityException $e) {
            self::assertSame('community_conversion_confirmation_required', $e->reason);
        }
        $conversion = $service->convert($authorOrg, $author, 'owner', $thread['id'], ['conversionType' => 'rfi', 'title' => 'Structural coordination information', 'projectId' => $project, 'humanConfirmed' => true]);
        self::assertSame('draft', $conversion['status']);
        self::assertSame('rfi', $conversion['conversionType']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM community_reports')->fetchColumn());
        self::assertGreaterThanOrEqual(8, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE action LIKE 'community.%'")->fetchColumn());
    }

    /** @return array{string,string,string,string} */
    private function seedOrganisation(string $slug): array
    {
        $organisationId = Identifier::generate(); $ownerId = Identifier::generate(); $adminId = Identifier::generate(); $projectId = Identifier::generate();
        foreach ([[$ownerId, "$slug-owner@example.test"], [$adminId, "$slug-admin@example.test"]] as [$id, $email]) {
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id' => $id, 'email' => $email]);
        }
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),:name,:slug,'active')")->execute(['id' => $organisationId, 'name' => $slug, 'slug' => $slug]);
        foreach ([[$ownerId, 'owner'], [$adminId, 'admin']] as [$id, $role]) {
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id' => Identifier::generate(), 'organisation' => $organisationId, 'user' => $id, 'role' => $role]);
        }
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,created_by) VALUES(UNHEX(:id),UNHEX(:organisation),:code,:name,'Client','Tshwane','Erf 19','Commercial','','architect',UNHEX(:user))")->execute(['id' => $projectId, 'organisation' => $organisationId, 'code' => strtoupper(substr($slug, 0, 12)), 'name' => $slug . ' project', 'user' => $ownerId]);
        return [$organisationId, $ownerId, $adminId, $projectId];
    }

    private function grantModerator(string$userId):void{$this->pdo->prepare("INSERT INTO community_platform_moderators(user_id,status,grant_reason) VALUES(UNHEX(:user),'active','Disposable integration-test moderator.')")->execute(['user'=>$userId]);}
}
