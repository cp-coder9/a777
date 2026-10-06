<?php
declare(strict_types=1);

namespace Architex\Platform\RemoteDesktop;

use Architex\Infrastructure\Integration\BridgeClient;
use Architex\Infrastructure\Integration\BridgeConfig;
use Architex\Infrastructure\Integration\BridgeTransport;
use Architex\Infrastructure\Integration\CurlBridgeTransport;
use Architex\Infrastructure\Integration\IntegrationException;
use Architex\Infrastructure\Integration\SignedCallback;
use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use PDO;
use Throwable;

final readonly class RemoteDesktopService
{
    public function __construct(private PDO $pdo,private BridgeTransport $transport=new CurlBridgeTransport(),private ?\Closure $configuration=null) {}

    private function config(string $org): BridgeConfig
    {
        return $this->configuration !== null ? ($this->configuration)($org) : BridgeConfig::fromEnvironment('DESKTOP_BRIDGE_'.strtoupper($org));
    }

    public function capabilities(string $org,string $actor,string $project): array
    {
        $this->scope($org,$actor,$project);
        $data=$this->client($org)->call('GET','/desktop/capabilities',['organisationId'=>$org,'projectId'=>$project,'actorId'=>$actor]);
        $result=[];
        if (!is_array($data['hosts'] ?? null) || count($data['hosts'])>100) throw new IntegrationException('desktop_host_policy_unavailable');
        // Provider output is projected; credentials and arbitrary provider metadata never reach the browser.
        foreach ($data['hosts'] as $host) {
            if (!is_array($host) || !is_string($host['hostId'] ?? null) || !is_array($host['apps'] ?? null)) continue;
            $hostId=$this->externalId($host['hostId']); $apps=[];
            foreach (array_slice($host['apps'],0,100) as $app) {
                if (!is_string($app)) continue;
                try { $app=$this->externalId($app); DesktopPolicy::host($data,$hostId,$app); $apps[]=$app; }
                catch (IntegrationException) { continue; }
            }
            if ($apps!==[]) $result[]=['hostId'=>$hostId,'apps'=>$apps,'policySha256'=>$host['policySha256'],'selectedAppOnly'=>true,'platform'=>'windows'];
        }
        return ['adapter'=>'architex_https_bridge_v1','transportVerified'=>true,'hosts'=>$result,'publicDesktopExposure'=>false];
    }

    public function create(string $org,string $actor,string $project,array $input): array
    {
        $this->scope($org,$actor,$project);
        if (($input['humanConfirmed'] ?? false)!==true) throw new IntegrationException('desktop_consent_required',422);
        $host=$this->externalId($input['hostId'] ?? null); $app=$this->externalId($input['appId'] ?? null);
        $request=$this->externalId($input['clientRequestId'] ?? null); $booking=$this->externalId($input['bookingReference'] ?? null);
        $caps=$this->client($org)->call('GET','/desktop/capabilities',['organisationId'=>$org,'projectId'=>$project,'actorId'=>$actor]);
        $policy=DesktopPolicy::host($caps,$host,$app);
        $id=$this->transaction(function () use ($org,$actor,$project,$host,$app,$request,$booking,$policy): string {
            // Serialize request-key creation for this actor, including concurrent first requests.
            $lock=$this->pdo->prepare("SELECT id FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:a) AND status='active' FOR UPDATE");
            $lock->execute(['o'=>$org,'a'=>$actor]);
            if ($lock->fetchColumn()===false) throw new IntegrationException('desktop_project_not_found',404);
            $s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(project_id)) project_id,host_id,app_id,booking_reference FROM remote_desktop_sessions WHERE organisation_id=UNHEX(:o) AND actor_user_id=UNHEX(:a) AND client_request_id=:request FOR UPDATE');
            $s->execute(['o'=>$org,'a'=>$actor,'request'=>$request]); $existing=$s->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if ($existing['project_id']!==$project || $existing['host_id']!==$host || $existing['app_id']!==$app || $existing['booking_reference']!==$booking) throw new IntegrationException('desktop_idempotency_conflict',409);
                return $existing['id'];
            }
            $id=Identifier::generate();
            $this->pdo->prepare('INSERT INTO remote_desktop_sessions(id,organisation_id,project_id,actor_user_id,host_id,app_id,booking_reference,client_request_id,expires_at,policy_sha256) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:p),UNHEX(:a),:host,:app,:booking,:request,:expiry,:policy)')
                ->execute(['id'=>$id,'o'=>$org,'p'=>$project,'a'=>$actor,'host'=>$host,'app'=>$app,'booking'=>$booking,'request'=>$request,'expiry'=>gmdate('Y-m-d H:i:s',time()+900),'policy'=>$policy['policySha256']]);
            (new AuditService($this->pdo))->append($org,$actor,'desktop.session_requested','remote_desktop_session',$id,['projectId'=>$project,'hostId'=>$host,'appId'=>$app,'policySha256'=>$policy['policySha256']]);
            return $id;
        });
        try {
            return $this->transaction(function () use ($org,$actor,$id,$policy): array {
                $row=$this->session($org,$actor,$id,true);
                if ($row['status']!=='requested') return $row;
                $this->scope($org,$actor,$row['projectId']);
                $caps=$this->client($org)->call('GET','/desktop/capabilities',['organisationId'=>$org,'projectId'=>$row['projectId'],'actorId'=>$actor]);
                $currentPolicy=DesktopPolicy::host($caps,$row['hostId'],$row['appId']);
                if (!hash_equals($row['policySha256'],$currentPolicy['policySha256']) || !hash_equals($row['policySha256'],$policy['policySha256'])) throw new IntegrationException('desktop_policy_changed',409);
                if ($row['expiresAt']<=time()) { $this->status($org,$id,'expired'); return $this->session($org,$actor,$id); }
                $payload=$this->binding($row)+['bookingReference'=>$row['bookingReference'],'expiresAt'=>$row['expiresAt'],'policySha256'=>$row['policySha256'],'humanConfirmed'=>true];
                $result=$this->client($org)->call('POST','/desktop/sessions',$payload,'desktop-'.$id);
                if (($result['status'] ?? null)!=='ready' || ($result['bookingAuthorized'] ?? false)!==true
                    || ($result['policySha256'] ?? null)!==$row['policySha256'] || ($result['expiresAt'] ?? null)!==$row['expiresAt']) throw new IntegrationException('desktop_session_response_invalid',502);
                $reference=$this->reference($result['brokerReference'] ?? null);
                $this->pdo->prepare("UPDATE remote_desktop_sessions SET status='ready',broker_reference=:reference,last_error_code=NULL,version=version+1 WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)")->execute(['reference'=>$reference,'o'=>$org,'id'=>$id]);
                (new AuditService($this->pdo))->append($org,$actor,'desktop.session_ready','remote_desktop_session',$id,['brokerReference'=>$reference]);
                return $this->session($org,$actor,$id);
            });
        } catch (IntegrationException $e) {
            $this->pdo->prepare('UPDATE remote_desktop_sessions SET last_error_code=:error WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)')->execute(['error'=>$e->reason,'o'=>$org,'id'=>$id]);
            throw $e;
        }
    }

    public function find(string $org,string $actor,string $id): array
    {
        return $this->transaction(function () use ($org,$actor,$id): array {
            $row=$this->session($org,$actor,$id,true); $this->scope($org,$actor,$row['projectId']);
            if ($row['expiresAt']<=time() && in_array($row['status'],['requested','ready','active'],true)) {
                $this->status($org,$id,'expired');
                (new AuditService($this->pdo))->append($org,$actor,'desktop.session_access_expired','remote_desktop_session',$id,[]);
                return $this->session($org,$actor,$id);
            }
            return $row;
        });
    }

    public function viewerTicket(string $org,string $actor,string $id,array $input): array
    {
        if (($input['humanConfirmed'] ?? false)!==true) throw new IntegrationException('desktop_consent_required',422);
        return $this->transaction(function () use ($org,$actor,$id): array {
            $row=$this->session($org,$actor,$id,true); $this->scope($org,$actor,$row['projectId']);
            if (!in_array($row['status'],['ready','active'],true) || $row['expiresAt']<=time()) throw new IntegrationException('desktop_session_not_connectable',409);
            $caps=$this->client($org)->call('GET','/desktop/capabilities',['organisationId'=>$org,'projectId'=>$row['projectId'],'actorId'=>$actor]);
            $host=DesktopPolicy::host($caps,$row['hostId'],$row['appId']);
            if (!hash_equals($row['policySha256'],$host['policySha256'])) throw new IntegrationException('desktop_policy_changed',409);
            $result=$this->client($org)->call('POST','/desktop/viewer-tickets',$this->binding($row)+['brokerReference'=>$row['brokerReference'],'policySha256'=>$row['policySha256'],'expiresAt'=>min(time()+60,$row['expiresAt'])]);
            if (($result['brokerReference'] ?? null)!==$row['brokerReference'] || ($result['policySha256'] ?? null)!==$row['policySha256']) throw new IntegrationException('desktop_session_response_invalid',502);
            return DesktopPolicy::ticket($result,$this->config($org)->url,$row['expiresAt']);
        });
    }

    public function close(string $org,string $actor,string $id,array $input): array
    {
        if (($input['humanConfirmed'] ?? false)!==true) throw new IntegrationException('desktop_consent_required',422);
        $this->transaction(function () use ($org,$actor,$id): void {
            $row=$this->session($org,$actor,$id,true);
            // Cleanup is ownership-scoped, not contingent on still having launch permission.
            if ($row['status']==='closed') return;
            if ($row['status']!=='closing') $this->status($org,$id,'closing');
        });
        return $this->transaction(function () use ($org,$actor,$id): array {
            $row=$this->session($org,$actor,$id,true);
            if ($row['status']!=='closing') return $row;
            if ($row['brokerReference']===null) {
                // Broker must tombstone the original create key, including requests still in flight.
                $identity=$this->binding($row)+['bookingReference'=>$row['bookingReference'],'expiresAt'=>$row['expiresAt'],'policySha256'=>$row['policySha256'],'creationIdempotencyKey'=>'desktop-'.$id];
                $result=$this->client($org)->call('POST','/desktop/cancel-by-identity',$identity,'desktop-close-'.$id);
                foreach ($identity as $key=>$value) if (($result[$key] ?? null)!==$value) throw new IntegrationException('desktop_session_response_invalid',502);
                if (($result['status'] ?? null)!=='closed' || ($result['creationKeyTombstoned'] ?? false)!==true) throw new IntegrationException('desktop_session_response_invalid',502);
            } else {
                $result=$this->client($org)->call('POST','/desktop/close',$this->binding($row)+['brokerReference'=>$row['brokerReference']],'desktop-close-'.$id);
                if (($result['status'] ?? null)!=='closed' || ($result['brokerReference'] ?? null)!==$row['brokerReference']) throw new IntegrationException('desktop_session_response_invalid',502);
            }
            $this->status($org,$id,'closed');
            (new AuditService($this->pdo))->append($org,$actor,'desktop.session_closed','remote_desktop_session',$id,[]);
            return $this->session($org,$actor,$id);
        });
    }

    public function callback(string $org,string $body,string $timestamp,string $signature): array
    {
        $this->id($org);
        $payload=SignedCallback::verify($body,$timestamp,$signature,$this->config($org)->webhookSecret);
        if (($payload['organisationId'] ?? null)!==$org) throw new IntegrationException('integration_callback_scope_mismatch',422);
        $id=$this->id($payload['sessionId'] ?? null); $actor=$this->id($payload['actorId'] ?? null);
        $event=$this->reference($payload['eventId'] ?? null); $next=$payload['status'] ?? null;
        if (!is_string($next)) throw new IntegrationException('integration_callback_payload_invalid',422);
        return $this->transaction(function () use ($org,$actor,$id,$event,$next,$payload,$body): array {
            // Lock tenant before replay lookup: event ids are unique across tenant sessions.
            $s=$this->pdo->prepare('SELECT id FROM organisations WHERE id=UNHEX(:o) FOR UPDATE'); $s->execute(['o'=>$org]);
            if ($s->fetchColumn()===false) throw new IntegrationException('desktop_session_not_found',404);
            $s=$this->pdo->prepare('SELECT LOWER(HEX(session_id)) session_id,payload_sha256 FROM remote_desktop_events WHERE organisation_id=UNHEX(:o) AND external_event_id=:event'); $s->execute(['o'=>$org,'event'=>$event]);
            $existing=$s->fetch(PDO::FETCH_ASSOC); $sha=hash('sha256',$body);
            if (is_array($existing)) {
                if ($existing['session_id']!==$id || !hash_equals($existing['payload_sha256'],$sha)) throw new IntegrationException('desktop_callback_event_conflict',409);
                return ['sessionId'=>$id,'duplicate'=>true];
            }
            $row=$this->session($org,$actor,$id,true);
            foreach ($this->binding($row)+['brokerReference'=>$row['brokerReference'],'policySha256'=>$row['policySha256']] as $key=>$value) {
                if ($value===null || ($payload[$key] ?? null)!==$value) throw new IntegrationException('integration_callback_scope_mismatch',422);
            }
            if (!DesktopPolicy::allows($row['status'],$next) || ($row['expiresAt']<=time() && in_array($next,['ready','active'],true))) throw new IntegrationException('desktop_callback_transition_invalid',409);
            if (in_array($next,['ready','active'],true)) {
                $this->scope($org,$actor,$row['projectId']);
                $caps=$this->client($org)->call('GET','/desktop/capabilities',['organisationId'=>$org,'projectId'=>$row['projectId'],'actorId'=>$actor]);
                $host=DesktopPolicy::host($caps,$row['hostId'],$row['appId']);
                if (!hash_equals($row['policySha256'],$host['policySha256'])) throw new IntegrationException('desktop_policy_changed',409);
            }
            $this->pdo->prepare('INSERT INTO remote_desktop_events(id,organisation_id,session_id,external_event_id,payload_sha256,status) VALUES(UNHEX(:id),UNHEX(:o),UNHEX(:session),:event,:sha,:status)')->execute(['id'=>Identifier::generate(),'o'=>$org,'session'=>$id,'event'=>$event,'sha'=>$sha,'status'=>$next]);
            if ($row['status']!==$next) $this->status($org,$id,$next);
            (new AuditService($this->pdo))->append($org,null,'desktop.callback_applied','remote_desktop_session',$id,['eventId'=>$event,'status'=>$next,'payloadSha256'=>$sha]);
            return ['sessionId'=>$id,'status'=>$next,'duplicate'=>false];
        });
    }

    private function scope(string $org,string $actor,string $project): void
    {
        $this->id($org); $this->id($actor); $this->id($project);
        $s=$this->pdo->prepare("SELECT m.role FROM organisation_memberships m JOIN projects p ON p.organisation_id=m.organisation_id JOIN organisations o ON o.id=m.organisation_id JOIN users u ON u.id=m.user_id WHERE m.organisation_id=UNHEX(:o) AND m.user_id=UNHEX(:a) AND p.id=UNHEX(:p) AND m.status='active' AND o.status='active' AND u.status='active' FOR UPDATE"); $s->execute(['o'=>$org,'a'=>$actor,'p'=>$project]); $role=$s->fetchColumn();
        if (!is_string($role)) throw new IntegrationException('desktop_project_not_found',404);
        if (in_array($role,['owner','admin'],true)) return;
        if (!in_array($role,['member'],true)) throw new IntegrationException('desktop_project_not_found',404);
        $s=$this->pdo->prepare("SELECT 1 FROM project_team_members WHERE organisation_id=UNHEX(:o) AND project_id=UNHEX(:p) AND user_id=UNHEX(:a) AND status='active' FOR UPDATE"); $s->execute(['o'=>$org,'p'=>$project,'a'=>$actor]);
        if ($s->fetchColumn()===false) throw new IntegrationException('desktop_project_not_found',404);
    }

    private function session(string $org,string $actor,string $id,bool $lock=false): array
    {
        $this->id($org); $this->id($actor); $this->id($id);
        $s=$this->pdo->prepare('SELECT LOWER(HEX(id)) id,LOWER(HEX(organisation_id)) organisationId,LOWER(HEX(project_id)) projectId,LOWER(HEX(actor_user_id)) actorId,host_id hostId,app_id appId,booking_reference bookingReference,status,broker_reference brokerReference,UNIX_TIMESTAMP(expires_at) expiresAt,policy_sha256 policySha256,last_error_code lastErrorCode,version FROM remote_desktop_sessions WHERE organisation_id=UNHEX(:o) AND actor_user_id=UNHEX(:a) AND id=UNHEX(:id)'.($lock ? ' FOR UPDATE' : ''));
        $s->execute(['o'=>$org,'a'=>$actor,'id'=>$id]); $row=$s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new IntegrationException('desktop_session_not_found',404);
        $row['expiresAt']=(int)$row['expiresAt']; $row['version']=(int)$row['version'];
        return $row;
    }

    private function binding(array $row): array
    {
        return ['organisationId'=>$row['organisationId'],'projectId'=>$row['projectId'],'actorId'=>$row['actorId'],'sessionId'=>$row['id'],'hostId'=>$row['hostId'],'appId'=>$row['appId']];
    }

    private function status(string $org,string $id,string $status): void
    {
        $this->pdo->prepare('UPDATE remote_desktop_sessions SET status=:status,version=version+1 WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)')->execute(['status'=>$status,'o'=>$org,'id'=>$id]);
    }
    private function id(mixed $id): string { if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/D',$id)!==1) throw new IntegrationException('desktop_scope_invalid',422); return $id; }
    private function externalId(mixed $id): string { if (!is_string($id) || preg_match('/\A[A-Za-z0-9_-]{1,100}\z/D',$id)!==1) throw new IntegrationException('desktop_external_id_invalid',422); return $id; }
    private function reference(mixed $id): string { if (!is_string($id) || preg_match('/\A[A-Za-z0-9_.:-]{1,180}\z/D',$id)!==1) throw new IntegrationException('desktop_reference_invalid',422); return $id; }
    private function client(string $org): BridgeClient { return new BridgeClient($this->config($org),$this->transport); }
    private function transaction(callable $operation): mixed { $this->pdo->beginTransaction(); try { $result=$operation(); $this->pdo->commit(); return $result; } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; } }
}
