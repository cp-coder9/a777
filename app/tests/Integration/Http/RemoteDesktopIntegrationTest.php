<?php
declare(strict_types=1);

namespace Architex\Tests\Integration\Http;

use Architex\Http\AuthenticationKernelFactory;
use Architex\Http\Request;
use Architex\Infrastructure\Integration\BridgeConfig;
use Architex\Infrastructure\Integration\BridgeTransport;
use Architex\Infrastructure\Integration\IntegrationException;
use Architex\Platform\Identity\Identifier;
use Architex\Platform\Identity\PasswordResetNotifier;
use Architex\Platform\Identity\RegistrationNotifier;
use Architex\Platform\Identity\SessionService;
use Architex\Platform\RemoteDesktop\RemoteDesktopService;
use Architex\Tests\Support\IntegrationBoundaryTestCase;

final class RemoteDesktopIntegrationTest extends IntegrationBoundaryTestCase
{
    public function testBrokerSessionsPersistScopedLifecycleWithoutPersistingViewerCredentials(): void
    {
        [$org,$actor,$project]=$this->tenant(); [$otherOrg,$otherActor,$otherProject]=$this->tenant();
        $transport=$this->transport();
        $config=BridgeConfig::fromValues(['URL'=>'https://broker.example/api','TOKEN'=>str_repeat('fixture-token-',3),'WEBHOOK_SECRET'=>str_repeat('fixture-secret-',3)]);
        $service=new RemoteDesktopService($this->pdo,$transport,static fn(string $o): BridgeConfig=>$config);
        $caps=$service->capabilities($org,$actor,$project);
        self::assertCount(1,$caps['hosts']); self::assertArrayNotHasKey('privateMetadata',$caps['hosts'][0]);
        $input=['hostId'=>'fixture-host','appId'=>'cad','bookingReference'=>'fixture-booking','clientRequestId'=>'fixture-request','humanConfirmed'=>true];
        $transport->failCreation=true;
        try { $service->create($org,$actor,$project,$input); self::fail('Unknown transport must not return success.'); }
        catch (IntegrationException $e) { self::assertSame('integration_transport_unknown',$e->reason); }
        self::assertSame('requested',$this->pdo->query('SELECT status FROM remote_desktop_sessions')->fetchColumn());
        $transport->failCreation=false;
        $session=$service->create($org,$actor,$project,$input);
        self::assertSame('ready',$session['status']);
        self::assertSame($transport->creationBodies[0],$transport->creationBodies[1]);
        self::assertSame($transport->creationKeys[0],$transport->creationKeys[1]);
        self::assertSame($session['id'],$service->create($org,$actor,$project,$input)['id']);
        self::assertCount(2,$transport->creationBodies);
        $ticket=$service->viewerTicket($org,$actor,$session['id'],['humanConfirmed'=>true]);
        self::assertSame('https://broker.example/viewer',$ticket['viewerUrl']);
        self::assertLessThanOrEqual(time()+120,$ticket['expiresAt']);
        $persisted=implode('',array_map(static fn($value): string => (string)$value,$this->pdo->query('SELECT * FROM remote_desktop_sessions')->fetch(\PDO::FETCH_ASSOC)));
        self::assertStringNotContainsString($ticket['viewerToken'],$persisted);
        self::assertStringNotContainsString('viewerUrl',$persisted);
        foreach ([[$otherOrg,$otherActor],[$org,$otherActor]] as [$o,$a]) {
            try { $service->find($o,$a,$session['id']); self::fail('Cross tenant/actor session access must fail.'); }
            catch (IntegrationException $e) { self::assertSame('desktop_session_not_found',$e->reason); }
        }
        try { $service->create($org,$actor,$project,array_replace($input,['appId'=>'shell'])); self::fail('App outside host policy must fail.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_host_policy_unavailable',$e->reason); }
        try { $service->create($org,$actor,$project,array_replace($input,['bookingReference'=>'different-booking'])); self::fail('Request key cannot change booking.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_idempotency_conflict',$e->reason); }
        $payload=['organisationId'=>$org,'projectId'=>$project,'actorId'=>$actor,'sessionId'=>$session['id'],'hostId'=>'fixture-host','appId'=>'cad','brokerReference'=>$session['brokerReference'],'policySha256'=>str_repeat('a',64),'eventId'=>'fixture-started','status'=>'active'];
        $body=json_encode($payload,JSON_THROW_ON_ERROR); $time=(string)time(); $signature=hash_hmac('sha256',$time.'.'.$body,$config->webhookSecret);
        $event=$service->callback($org,$body,$time,$signature); self::assertFalse($event['duplicate']);
        self::assertTrue($service->callback($org,$body,$time,$signature)['duplicate']);
        self::assertSame('active',$service->find($org,$actor,$session['id'])['status']);
        $bad=json_encode(array_replace($payload,['projectId'=>$otherProject,'eventId'=>'cross-project']),JSON_THROW_ON_ERROR);
        try { $service->callback($org,$bad,$time,hash_hmac('sha256',$time.'.'.$bad,$config->webhookSecret)); self::fail('Signed cross-project callback must fail.'); }
        catch (IntegrationException $e) { self::assertSame('integration_callback_scope_mismatch',$e->reason); }
        $conflict=json_encode(array_replace($payload,['status'=>'closed']),JSON_THROW_ON_ERROR);
        try { $service->callback($org,$conflict,$time,hash_hmac('sha256',$time.'.'.$conflict,$config->webhookSecret)); self::fail('Event reuse must fail.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_callback_event_conflict',$e->reason); }
        $transport->failClose=true;
        try { $service->close($org,$actor,$session['id'],['humanConfirmed'=>true]); self::fail('Unknown close response must remain closing.'); }
        catch (IntegrationException $e) { self::assertSame('integration_transport_unknown',$e->reason); }
        self::assertSame('closing',$service->find($org,$actor,$session['id'])['status']);
        try { $service->viewerTicket($org,$actor,$session['id'],['humanConfirmed'=>true]); self::fail('Closing session cannot issue viewer token.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_session_not_connectable',$e->reason); }
        $transport->failClose=false;
        self::assertSame('closed',$service->close($org,$actor,$session['id'],['humanConfirmed'=>true])['status']);
        $reopen=json_encode(array_replace($payload,['eventId'=>'reopen']),JSON_THROW_ON_ERROR);
        try { $service->callback($org,$reopen,$time,hash_hmac('sha256',$time.'.'.$reopen,$config->webhookSecret)); self::fail('Closed session cannot reopen.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_callback_transition_invalid',$e->reason); }
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM remote_desktop_events')->fetchColumn());
        $expirySession=$service->create($org,$actor,$project,array_replace($input,['clientRequestId'=>'fixture-expiry']));
        $this->pdo->prepare('UPDATE remote_desktop_sessions SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE id=UNHEX(:id)')->execute(['id'=>$expirySession['id']]);
        self::assertSame('expired',$service->find($org,$actor,$expirySession['id'])['status']);
        try { $service->viewerTicket($org,$actor,$expirySession['id'],['humanConfirmed'=>true]); self::fail('Expired access cannot issue ticket.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_session_not_connectable',$e->reason); }
    }

    public function testRevocationBlocksActivationButNeverOwnershipScopedCleanup(): void
    {
        [$org,$actor,$project]=$this->tenant();
        $transport=$this->transport();
        $config=BridgeConfig::fromValues(['URL'=>'https://broker.example/api','TOKEN'=>str_repeat('fixture-token-',3),'WEBHOOK_SECRET'=>str_repeat('fixture-secret-',3)]);
        $service=new RemoteDesktopService($this->pdo,$transport,static fn(string $o): BridgeConfig=>$config);
        $input=['hostId'=>'fixture-host','appId'=>'cad','bookingReference'=>'fixture-booking','clientRequestId'=>'revocation-request','humanConfirmed'=>true];
        $session=$service->create($org,$actor,$project,$input);
        $payload=['organisationId'=>$org,'projectId'=>$project,'actorId'=>$actor,'sessionId'=>$session['id'],'hostId'=>'fixture-host','appId'=>'cad','brokerReference'=>$session['brokerReference'],'policySha256'=>str_repeat('a',64),'eventId'=>'revoked-active','status'=>'active'];
        $send=function (array $data) use ($service,$org,$config): array { $body=json_encode($data,JSON_THROW_ON_ERROR); $time=(string)time(); return $service->callback($org,$body,$time,hash_hmac('sha256',$time.'.'.$body,$config->webhookSecret)); };
        $transport->policySha=str_repeat('b',64);
        try { $send($payload); self::fail('Changed current policy must block callback.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_policy_changed',$e->reason); }
        $transport->policySha=str_repeat('a',64); $transport->hostAvailable=false;
        try { $send($payload); self::fail('Withdrawn authenticated host consent must block callback.'); }
        catch (IntegrationException $e) { self::assertSame('desktop_host_policy_unavailable',$e->reason); }
        $transport->hostAvailable=true;
        // Scope uses current organisation/user state, not the scope at creation.
        foreach (['organisations','users'] as $table) {
            $subject=$table==='organisations' ? $org : $actor;
            $this->pdo->prepare("UPDATE ".$table." SET status='suspended' WHERE id=UNHEX(:id)")->execute(['id'=>$subject]);
            try { $send($payload); self::fail('Suspended current identity must block activation.'); }
            catch (IntegrationException $e) { self::assertSame('desktop_project_not_found',$e->reason); }
            $this->pdo->prepare("UPDATE ".$table." SET status='active' WHERE id=UNHEX(:id)")->execute(['id'=>$subject]);
        }
        $this->pdo->prepare("UPDATE organisation_memberships SET status='revoked' WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:a)")->execute(['o'=>$org,'a'=>$actor]);
        foreach (['ready','active'] as $next) {
            try { $send(array_replace($payload,['status'=>$next,'eventId'=>'revoked-'.$next])); self::fail('Current membership must be rechecked.'); }
            catch (IntegrationException $e) { self::assertSame('desktop_project_not_found',$e->reason); }
        }
        self::assertSame('ready',$this->pdo->query('SELECT status FROM remote_desktop_sessions')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM remote_desktop_events')->fetchColumn());
        self::assertSame('closed',$send(array_replace($payload,['status'=>'closed','eventId'=>'revoked-cleanup']))['status']);
        // Lost create response persists a null reference. Cancellation must not recreate it.
        $this->pdo->prepare("UPDATE organisation_memberships SET status='active' WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:a)")->execute(['o'=>$org,'a'=>$actor]);
        $transport->failCreation=true;
        try { $service->create($org,$actor,$project,array_replace($input,['clientRequestId'=>'lost-create'])); self::fail('Lost response must remain uncertain.'); }
        catch (IntegrationException $e) { self::assertSame('integration_transport_unknown',$e->reason); }
        $id=$this->pdo->query("SELECT LOWER(HEX(id)) FROM remote_desktop_sessions WHERE client_request_id='lost-create'")->fetchColumn();
        $this->pdo->prepare("UPDATE organisation_memberships SET status='revoked' WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:a)")->execute(['o'=>$org,'a'=>$actor]);
        $transport->hostAvailable=false;
        $transport->failClose=true;
        try { $service->close($org,$actor,$id,['humanConfirmed'=>true]); self::fail('Lost cancellation acknowledgement must remain closing.'); }
        catch (IntegrationException $e) { self::assertSame('integration_transport_unknown',$e->reason); }
        $transport->failClose=false;
        self::assertSame('closed',$service->close($org,$actor,$id,['humanConfirmed'=>true])['status']);
        self::assertSame($transport->cancelBodies[0],$transport->cancelBodies[1]);
        self::assertSame('desktop-'.$id,json_decode($transport->cancelBodies[1],true)['creationIdempotencyKey']);
        self::assertCount(2,$transport->creationBodies);
        self::assertSame('closed',$service->close($org,$actor,$id,['humanConfirmed'=>true])['status']);
        self::assertCount(2,$transport->cancelBodies);
        [$otherOrg,$otherActor]=$this->tenant();
        foreach ([[$otherOrg,$otherActor],[$org,$otherActor]] as [$o,$a]) {
            try { $service->close($o,$a,$id,['humanConfirmed'=>true]); self::fail('Cleanup cannot cross ownership scope.'); }
            catch (IntegrationException $e) { self::assertSame('desktop_session_not_found',$e->reason); }
        }
    }

    public function testIntegrationRoutesKeepAuthenticationTenantPermissionsAndCsrf(): void
    {
        [$org,$actor,$project]=$this->tenant(); [$otherOrg,$otherActor,$otherProject]=$this->tenant();
        $notifier=new class implements RegistrationNotifier,PasswordResetNotifier {
            public function isAvailable(): bool { return false; }
            public function send(string $email,string $token,string $expiresAt): void {}
        };
        $kernel=(new AuthenticationKernelFactory($this->pdo,$notifier,$notifier))->create();
        $session=(new SessionService($this->pdo))->rotate($actor);
        $headers=['Cookie'=>'architex_session='.$session->token,'X-Organisation-Id'=>$org,'X-CSRF-Token'=>$session->csrfToken,'Content-Type'=>'application/json'];
        $path='/api/v1/projects/'.$project.'/remote-desktop/capabilities';
        self::assertSame(401,$kernel->handle(Request::json('GET',$path))->status());
        $response=$kernel->handle(Request::json('GET',$path,$headers));
        self::assertSame(503,$response->status(),$response->body()); self::assertSame('integration_not_configured',$response->json()['error']['code']);
        self::assertSame(404,$kernel->handle(Request::json('GET','/api/v1/projects/'.$otherProject.'/remote-desktop/capabilities',$headers))->status());
        foreach (['/api/v1/projects/'.$project.'/remote-desktop/sessions','/api/v1/remote-desktop/sessions/'.str_repeat('a',32).'/viewer-ticket','/api/v1/remote-desktop/sessions/'.str_repeat('a',32).'/close','/api/v1/provider-release-requests/'.str_repeat('a',32).'/submit'] as $route) {
            self::assertSame(419,$kernel->handle(Request::json('POST',$route,array_diff_key($headers,['X-CSRF-Token'=>true]),'{}'))->status());
        }
        $this->pdo->prepare("UPDATE organisation_memberships SET role='viewer' WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:a)")->execute(['o'=>$org,'a'=>$actor]);
        self::assertSame(403,$kernel->handle(Request::json('POST','/api/v1/projects/'.$project.'/remote-desktop/sessions',$headers,'{}'))->status());
    }

    private function tenant(): array
    {
        $org=Identifier::generate(); $actor=Identifier::generate(); $project=Identifier::generate();
        $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status,email_verified_at) VALUES(UNHEX(:id),:email,'fixture-hash','active',UTC_TIMESTAMP(6))")->execute(['id'=>$actor,'email'=>$actor.'@example.test']);
        $this->pdo->prepare("INSERT INTO organisations(id,name,slug,status) VALUES(UNHEX(:id),'Integration fixture',:slug,'active')")->execute(['id'=>$org,'slug'=>'fixture-'.$org]);
        $this->pdo->prepare("INSERT INTO organisation_memberships(id,organisation_id,user_id,role,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:a),'owner','active')")->execute(['id'=>Identifier::generate(),'o'=>$org,'a'=>$actor]);
        $this->pdo->prepare("INSERT INTO projects(id,organisation_id,project_code,project_name,client_name,municipality,property_reference,property_use,land_use_notes,lead_professional_role,current_phase,created_by) VALUES(UNHEX(:id),UNHEX(:o),:code,'Fixture project','Fixture client','Fixture municipality','Fixture property','Residential','','architect','appointment',UNHEX(:a))")->execute(['id'=>$project,'o'=>$org,'code'=>substr($project,0,8),'a'=>$actor]);
        return [$org,$actor,$project];
    }

    private function transport(): BridgeTransport
    {
        return new class implements BridgeTransport {
            public bool $hostAvailable=true; public string $policySha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'; public array $cancelBodies=[];
            public bool $failCreation=false; public bool $failClose=false; public array $creationBodies=[]; public array $creationKeys=[];
            public function request(string $method,string $url,array $headers,string $body): array {
                if ($method==='GET') {
                    parse_str((string)parse_url($url,PHP_URL_QUERY),$scope);
                    $data=$scope+['contract'=>'architex.integration.v1','hosts'=>[['hostId'=>'fixture-host','platform'=>'windows','available'=>$this->hostAvailable,'signedAgent'=>true,'hostConsentVerified'=>true,'processBinding'=>true,'inputFiltering'=>true,'workspaceConfinement'=>true,'selectedAppOnly'=>true,'autoExpire'=>true,'policySha256'=>$this->policySha,'apps'=>['cad'],'privateMetadata'=>'must not leak']]];
                } else {
                    $data=json_decode($body,true,flags:JSON_THROW_ON_ERROR);
                    if (str_ends_with($url,'/desktop/sessions')) {
                        $this->creationBodies[]=$body; $this->creationKeys[]=$headers['Idempotency-Key'];
                        if ($this->failCreation) throw new IntegrationException('integration_transport_unknown',502);
                        $data+=['status'=>'ready','bookingAuthorized'=>true,'brokerReference'=>'fixture-'.$data['sessionId']];
                    } elseif (str_ends_with($url,'/viewer-tickets')) $data+=['viewerUrl'=>'https://broker.example/viewer','viewerToken'=>str_repeat('t',43)];
                    elseif (str_ends_with($url,'/cancel-by-identity')) { $this->cancelBodies[]=$body; if ($this->failClose) throw new IntegrationException('integration_transport_unknown',502); $data+=['status'=>'closed','creationKeyTombstoned'=>true]; }
                    elseif (str_ends_with($url,'/close')) { if ($this->failClose) throw new IntegrationException('integration_transport_unknown',502); $data+=['status'=>'closed']; }
                }
                return ['status'=>200,'body'=>json_encode($data,JSON_THROW_ON_ERROR)];
            }
        };
    }
}
