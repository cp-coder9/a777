<?php

declare(strict_types=1);

namespace Architex\Tests\Unit\Community;

use Architex\Platform\Community\CommunityAudiencePolicy;
use Architex\Platform\Community\CommunityException;
use PHPUnit\Framework\TestCase;

final class CommunityAudiencePolicyTest extends TestCase
{
    public function testSelectedRolesResolveOnlyActiveOptedInEligibleRecipients(): void
    {
        if (!class_exists(CommunityAudiencePolicy::class)) self::fail('CommunityAudiencePolicy is not implemented.');
        $policy=new CommunityAudiencePolicy();
        $recipients=$policy->resolve([
            ['id'=>'a1','role'=>'architect','active'=>true,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'a2','role'=>'architect','active'=>true,'optedIn'=>false,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'q1','role'=>'quantity_surveyor','active'=>true,'optedIn'=>true,'region'=>'Western Cape','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'s1','role'=>'supplier','active'=>true,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'a3','role'=>'architect','active'=>false,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'a4','role'=>'architect','active'=>true,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>['sender'],'mutedRoles'=>[]],
        ],['scope'=>'selected_roles','targetRoles'=>['architect','quantity_surveyor'],'targetRegions'=>[],'verificationStatus'=>'any'],'sender');
        self::assertSame(['a1','q1'],array_column($recipients,'id'));
        self::assertNotContains('supplier',array_column($recipients,'role'));
    }

    public function testRegionVerificationMuteAndModerationRulesFailClosed(): void
    {
        if (!class_exists(CommunityAudiencePolicy::class)) self::fail('CommunityAudiencePolicy is not implemented.');
        $policy=new CommunityAudiencePolicy();
        $recipients=$policy->resolve([
            ['id'=>'a1','role'=>'architect','active'=>true,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'a2','role'=>'architect','active'=>true,'optedIn'=>true,'region'=>'Western Cape','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'a3','role'=>'architect','active'=>true,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'self_declared','blockedUserIds'=>[],'mutedRoles'=>[]],
            ['id'=>'a4','role'=>'architect','active'=>true,'optedIn'=>true,'region'=>'Gauteng','verificationStatus'=>'verified','blockedUserIds'=>[],'mutedRoles'=>['architect']],
        ],['scope'=>'selected_roles','targetRoles'=>['architect'],'targetRegions'=>['Gauteng'],'verificationStatus'=>'verified'],'sender');
        self::assertSame(['a1'],array_column($recipients,'id'));
        self::assertSame('approved',$policy->moderationStatus('general_question',['scope'=>'selected_roles','targetRoles'=>['architect']],false));
        self::assertSame('pending_approval',$policy->moderationStatus('general_question',['scope'=>'all_community'],false));
        self::assertSame('approved',$policy->moderationStatus('admin_notice',['scope'=>'all_community'],true));
        self::assertSame('pending_approval',$policy->moderationStatus('general_question',['scope'=>'selected_roles','targetRoles'=>['architect','engineer','quantity_surveyor','town_planner']],false));
        $this->expectException(CommunityException::class);
        $policy->normalise(['scope'=>'selected_roles','targetRoles'=>['not_a_role']]);
    }

    public function testMutingAnyTargetAudienceRoleSuppressesTheMessage(): void
    {
        $policy = new CommunityAudiencePolicy();
        $resolved = $policy->resolve([
            ['id'=>'recipient','role'=>'engineer','region'=>'Gauteng','verificationStatus'=>'verified','active'=>true,'optedIn'=>true,'blockedUserIds'=>[],'mutedRoles'=>['architect']],
        ], ['scope'=>'selected_roles','targetRoles'=>['architect','engineer'],'targetRegions'=>['Gauteng'],'verificationStatus'=>'verified'], 'sender');

        self::assertSame([], $resolved);
    }
}
