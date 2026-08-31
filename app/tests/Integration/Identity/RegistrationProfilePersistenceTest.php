<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Identity;

use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\RegisterOrganisation;
use Architex\Platform\Identity\RegistrationService;
use Architex\Platform\Legal\LegalAcceptanceService;
use Architex\Tests\Support\MariaDbTestCase;

final class RegistrationProfilePersistenceTest extends MariaDbTestCase
{
    public function testPersistsThePublicEnrolmentRoleProfileWithThePendingIdentity(): void
    {
        $insert = $this->pdo->prepare("INSERT INTO legal_notices (id,notice_type,version,title,content_sha256,published_at,effective_at) VALUES (UNHEX(:id),:type,'1.0.0',:title,:hash,UTC_TIMESTAMP(6)-INTERVAL 1 DAY,UTC_TIMESTAMP(6)-INTERVAL 1 DAY)");
        foreach (LegalAcceptanceService::SIGNUP_NOTICE_TYPES as $type) {
            $insert->execute(['id'=>Identifier::generate(),'type'=>$type,'title'=>$type,'hash'=>hash('sha256',$type)]);
        }

        $pending = (new RegistrationService($this->pdo))->register(RegisterOrganisation::fromArray([
            'email'=>'architect@example.com',
            'password'=>'correct horse battery staple',
            'organisationName'=>'Datum Studio',
            'organisationSlug'=>'datum-studio',
            'fullName'=>'John Doe',
            'roleKey'=>'architect',
            'profile'=>['sacap_number'=>'PrArch T4321'],
            'acceptedNoticeVersions'=>array_fill_keys(LegalAcceptanceService::SIGNUP_NOTICE_TYPES, '1.0.0'),
        ]));

        $statement = $this->pdo->prepare('SELECT full_name,role_key,profile FROM registration_profiles WHERE user_id=UNHEX(:user) AND organisation_id=UNHEX(:organisation)');
        $statement->execute(['user'=>$pending->userId,'organisation'=>$pending->organisationId]);
        $row = $statement->fetch();
        self::assertIsArray($row);
        self::assertSame('John Doe', $row['full_name']);
        self::assertSame('architect', $row['role_key']);
        self::assertSame(['sacap_number'=>'PrArch T4321'], json_decode((string)$row['profile'], true, flags: JSON_THROW_ON_ERROR));
    }
}
