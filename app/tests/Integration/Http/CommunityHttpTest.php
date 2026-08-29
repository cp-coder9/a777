<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class CommunityHttpTest extends MariaDbTestCase
{
    public function testAuthenticatedCommunityRoutesUseOneSharedBackendForBothShellModes(): void
    {
        [$organisationId, $ownerId] = $this->seedMember();
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier { public function isAvailable(): bool{return false;} public function send(string $email,string $token,string $expiresAt): void{} };
        $kernel = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $session = (new SessionService($this->pdo))->rotate($ownerId);
        $headers = ['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$organisationId,'X-CSRF-Token'=>$session->csrfToken];

        $preference = $kernel->handle($this->json('PUT', '/api/v1/community/preference', $headers, ['role'=>'architect','region'=>'Gauteng','verificationStatus'=>'verified','optedIn'=>true]));
        self::assertSame(200, $preference->status(), $preference->body());
        self::assertTrue($preference->json()['data']['optedIn']);
        $channels = $kernel->handle(Request::json('GET', '/api/v1/community/channels', $headers));
        self::assertSame(200, $channels->status(), $channels->body()); self::assertGreaterThanOrEqual(8, count($channels->json()['data']));
        $capabilities=$kernel->handle(Request::json('GET','/api/v1/community/capabilities',$headers));self::assertSame(200,$capabilities->status(),$capabilities->body());self::assertFalse($capabilities->json()['data']['canModerate']);
        $deniedChannel=$kernel->handle($this->json('POST','/api/v1/community/channels',$headers,['id'=>'http-governed','label'=>'HTTP governed','description'=>'Admin-governed channel.','visibility'=>'public']));
        self::assertSame(403,$deniedChannel->status(),$deniedChannel->body());
        $watch = $kernel->handle($this->json('PUT', '/api/v1/community/channel-preference', $headers, ['channelId'=>'role-architects','watching'=>true,'muted'=>false]));
        self::assertSame(200,$watch->status(),$watch->body());self::assertTrue($watch->json()['data']['watching']);
        $created = $kernel->handle($this->json('POST', '/api/v1/community/threads', $headers, ['channelId'=>'role-architects','category'=>'general_question','title'=>'HTTP community topic','body'=>'Shared backend from the embedded shell.','appShellMode'=>'embedded_platform','audience'=>['scope'=>'selected_roles','targetRoles'=>['architect']]]));
        self::assertSame(201, $created->status(), $created->body()); self::assertSame('embedded_platform', $created->json()['data']['appShellMode']);
        $feed = $kernel->handle(Request::json('GET', '/api/v1/community/feed?channelId=role-architects', $headers));
        self::assertSame(200, $feed->status(), $feed->body()); self::assertCount(1, $feed->json()['data']);
        $threadId=$created->json()['data']['id'];
        $saved=$kernel->handle($this->json('PUT','/api/v1/community/threads/'.$threadId.'/saved',$headers,['saved'=>true]));
        self::assertSame(200,$saved->status(),$saved->body());self::assertTrue($saved->json()['data']['saved']);
        $search=$kernel->handle(Request::json('GET','/api/v1/community/feed?search=HTTP%20community&saved=true',$headers));
        self::assertSame(200,$search->status(),$search->body());self::assertCount(1,$search->json()['data']);
        $notifications=$kernel->handle(Request::json('GET','/api/v1/community/notifications',$headers));
        self::assertSame(200,$notifications->status(),$notifications->body());
    }

    /** @return array{string,string} */
    private function seedMember(): array
    {
        $organisationId=Identifier::generate();$userId=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),'community-http@example.test','hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$userId]);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Community HTTP','community-http','active')")->execute(['id'=>$organisationId]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),'owner','active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisationId,'user'=>$userId]);
        return[$organisationId,$userId];
    }
    /** @param array<string,string> $headers @param array<string,mixed> $body */private function json(string$method,string$path,array$headers,array$body):Request{return Request::json($method,$path,$headers+['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR));}
}
