<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Knowledge;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Knowledge\KnowledgeException;
use Architex\Platform\Knowledge\KnowledgeService;
use Architex\Tests\Support\MariaDbTestCase;

final class KnowledgeServiceTest extends MariaDbTestCase
{
    public function testIndependentReviewControlsWingmanEligibleKnowledge():void
    {
        [$o,$owner,$admin,$member]=$this->tenant();$service=new KnowledgeService($this->pdo);
        $source=$service->create($o,$owner,'owner',['sourceKey'=>'municipal.tshwane.submission','sourceCategory'=>'municipal_guideline','title'=>'Tshwane submission practice note','sourceReference'=>'Human reviewed municipal source, 2026-09-01','content'=>'Confirm the official municipal checklist and current portal status before relying.','humanConfirmed'=>true]);
        self::assertSame('review_required',$source['reviewStatus']);self::assertCount(0,$service->workspace($o,$member,'member',null,null)['items']);
        $approved=$service->review($o,$admin,'admin',$source['id'],['decision'=>'approved','expectedVersion'=>1,'reason'=>'Admin checked the exact source reference and content.','humanConfirmed'=>true]);
        self::assertSame('approved',$approved['reviewStatus']);self::assertSame(2,$approved['recordVersion']);
        $visible=$service->workspace($o,$member,'member',null,'Tshwane');self::assertCount(1,$visible['items']);self::assertSame($source['contentSha256'],$visible['items'][0]['contentSha256']);
    }

    public function testCreatorCannotApproveOwnKnowledgeRevision():void
    {
        [$o,$owner]=$this->tenant();$service=new KnowledgeService($this->pdo);$source=$service->create($o,$owner,'owner',['sourceKey'=>'sans.xa.note','sourceCategory'=>'nbr_sans','title'=>'XA note','sourceReference'=>'Licensed source reference','content'=>'Human-entered explanatory source content.','humanConfirmed'=>true]);
        $this->expectException(KnowledgeException::class);$this->expectExceptionMessage('knowledge_independent_review_required');
        $service->review($o,$owner,'owner',$source['id'],['decision'=>'approved','expectedVersion'=>1,'reason'=>'Self approval must fail.','humanConfirmed'=>true]);
    }

    /** @return array{string,string,string,string} */private function tenant():array
    {
        $o=Identifier::generate();$owner=Identifier::generate();$admin=Identifier::generate();$member=Identifier::generate();
        foreach([[$owner,'knowledge-owner@example.test','owner'],[$admin,'knowledge-admin@example.test','admin'],[$member,'knowledge-member@example.test','member']]as[$u,$email,$role]){$this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:u),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['u'=>$u,'email'=>$email]);if($role==='owner')$this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:o),'Knowledge Org',:slug,'active')")->execute(['o'=>$o,'slug'=>'knowledge-'.substr($o,0,8)]);$this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:u),:role,'active')")->execute(['id'=>Identifier::generate(),'o'=>$o,'u'=>$u,'role'=>$role]);}
        return[$o,$owner,$admin,$member];
    }
}
