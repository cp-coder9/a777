<?php

declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Kernel;
use Architex\Http\Request;
use Architex\Platform\Demo\DemoRoleLensRegistry;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Tests\Support\MariaDbTestCase;

final class DemoSandboxHttpTest extends MariaDbTestCase
{
    public function testDemoRoutesAreAbsentInProductionAndGovernedEndToEndInDemoMode(): void
    {
        [$organisation, $users] = $this->seedOrganisation();
        $notifier = new class implements RegistrationNotifier, PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email, string $token, string $expiresAt): void {}
        };
        $production = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier))->create();
        $demo = (new AuthenticationKernelFactory($this->pdo, $notifier, $notifier, appMode: 'demo'))->create();
        $ownerSession = (new SessionService($this->pdo))->rotate($users['owner']);
        $headers = $this->headers($organisation, $ownerSession->token, $ownerSession->csrfToken);

        foreach ([
            ['POST','/api/v1/demo/sandbox/ensure'], ['GET','/api/v1/demo/sandbox'], ['PUT','/api/v1/demo/sandbox/role'],
            ['GET','/api/v1/demo/sandbox/projects'], ['GET','/api/v1/demo/sandbox/projects/'.str_repeat('0',32)],
            ['GET','/api/v1/demo/sandbox/learning'], ['POST','/api/v1/demo/sandbox/reset'],
        ] as [$method, $path]) {
            $response = $production->handle($this->request($method, $path, $headers, []));
            self::assertSame(404, $response->status(), $method . ' ' . $path . ': ' . $response->body());
        }

        self::assertSame(401, $demo->handle($this->request('POST','/api/v1/demo/sandbox/ensure',[],[]))->status());
        $withoutCsrf = $headers;
        unset($withoutCsrf['X-CSRF-Token']);
        self::assertSame(419, $demo->handle($this->request('POST','/api/v1/demo/sandbox/ensure',$withoutCsrf,[]))->status());

        $ensured = $demo->handle($this->request('POST','/api/v1/demo/sandbox/ensure',$headers,[]));
        self::assertSame(200, $ensured->status(), $ensured->body());
        self::assertSame(1, $ensured->json()['data']['generation']);
        self::assertSame(12, $ensured->json()['data']['counts']['projects']);
        self::assertSame(200, $demo->handle($this->request('GET','/api/v1/demo/sandbox',$headers))->status());

        foreach ((new DemoRoleLensRegistry())->all() as $key => $role) {
            $selected = $demo->handle($this->request('PUT','/api/v1/demo/sandbox/role',$headers,['role'=>$key]));
            self::assertSame(200, $selected->status(), $selected->body());
            self::assertSame($key, $selected->json()['data']['selectedRole']);
            $projects = $demo->handle($this->request('GET','/api/v1/demo/sandbox/projects',$headers));
            self::assertSame(200, $projects->status(), $projects->body());
            self::assertCount(count($role['projectKeys']), $projects->json()['data']);
        }
        $invalid = $demo->handle($this->request('PUT','/api/v1/demo/sandbox/role',$headers,['role'=>'platform_admin']));
        self::assertSame(422, $invalid->status(), $invalid->body());
        self::assertSame('demo_role_invalid', $invalid->json()['error']['code']);

        $projects = $demo->handle($this->request('GET','/api/v1/demo/sandbox/projects',$headers))->json()['data'];
        $detail = $demo->handle($this->request('GET','/api/v1/demo/sandbox/projects/'.$projects[0]['id'],$headers));
        self::assertSame(200, $detail->status(), $detail->body());
        self::assertSame($projects[0]['id'], $detail->json()['data']['id']);
        $learning = $demo->handle($this->request('GET','/api/v1/demo/sandbox/learning',$headers));
        self::assertSame(200, $learning->status(), $learning->body());
        self::assertSame('not_accredited_demo_record', $learning->json()['data']['relianceLabel']);

        foreach (['admin','member','viewer'] as $role) {
            $session = (new SessionService($this->pdo))->rotate($users[$role]);
            $response = $demo->handle($this->request('POST','/api/v1/demo/sandbox/ensure',$this->headers($organisation,$session->token,$session->csrfToken),[]));
            self::assertSame(403, $response->status(), $role . ': ' . $response->body());
        }
        $badReset = $demo->handle($this->request('POST','/api/v1/demo/sandbox/reset',$headers,['confirmation'=>'wrong','reason'=>'Restore the deterministic baseline.']));
        self::assertSame(422, $badReset->status(), $badReset->body());
        $reset = $demo->handle($this->request('POST','/api/v1/demo/sandbox/reset',$headers,['confirmation'=>'RESET DEMO SANDBOX','reason'=>'Restore the deterministic baseline.']));
        self::assertSame(200, $reset->status(), $reset->body());
        self::assertSame(2, $reset->json()['data']['generation']);
        $newOrganisation = $reset->json()['data']['organisationId'];
        self::assertSame(404, $demo->handle($this->request('GET','/api/v1/demo/sandbox',$headers))->status());
        $newHeaders = $this->headers($newOrganisation, $ownerSession->token, $ownerSession->csrfToken);
        self::assertSame(200, $demo->handle($this->request('GET','/api/v1/demo/sandbox',$newHeaders))->status());
    }

    /** @return array{string,array<string,string>} */
    private function seedOrganisation(): array
    {
        $organisation = Identifier::generate();
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Demo HTTP',:slug,'active')")->execute(['id'=>$organisation,'slug'=>'demo-http-'.substr($organisation,0,8)]);
        $users = [];
        foreach (['owner','admin','member','viewer'] as $role) {
            $user = Identifier::generate();
            $users[$role] = $user;
            $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$user,'email'=>'demo-'.$role.'@example.test']);
            $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:organisation),UNHEX(:user),:role,'active')")->execute(['id'=>Identifier::generate(),'organisation'=>$organisation,'user'=>$user,'role'=>$role]);
        }
        return [$organisation, $users];
    }

    /** @return array<string,string> */
    private function headers(string $organisation, string $token, string $csrf): array
    {
        return ['Cookie'=>'architex_session='.$token,'X-Organisation-Id'=>$organisation,'X-CSRF-Token'=>$csrf];
    }

    /** @param array<string,string> $headers @param array<string,mixed>|null $body */
    private function request(string $method, string $path, array $headers, ?array $body = null): Request
    {
        if ($body === null) {
            return Request::json($method, $path, $headers);
        }
        return Request::json($method, $path, $headers + ['Content-Type'=>'application/json'], json_encode($body === [] ? (object) [] : $body, JSON_THROW_ON_ERROR));
    }
}
