<?php

declare(strict_types=1);

namespace Architex\Platform\OperatingSystem;

use Architex\Platform\Audit\AuditService;
use Architex\Platform\Identity\Identifier;
use DateTimeImmutable;
use JsonException;
use PDO;

final readonly class DeploymentReleaseGateService
{
    public function __construct(private PDO $pdo){}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function record(string$o,string$u,string$role,array$input):array
    {
        $this->governor($o,$u,$role);
        if(($input['humanConfirmed']??false)!==true)throw new ProductOperatingSystemException('operating_human_confirmation_required');
        $environment=$this->choice($input['environment']??null,['staging','production'],'release_environment_invalid');
        $repoBranch=$this->text($input['repoBranch']??null,160,'release_repo_branch_required');$deployedBranch=$this->text($input['deployedBranch']??null,160,'release_deployed_branch_required');
        $repoCommit=$this->commit($input['repoCommit']??null,'release_repo_commit_invalid');$deployedCommit=$this->commit($input['deployedCommit']??null,'release_deployed_commit_invalid');
        $built=$this->timestamp($input['buildTimestamp']??null);$apiMode=$this->text($input['apiMode']??null,100,'release_api_mode_required');
        $api=$this->boolean($input['apiHealthOk']??null,'release_api_health_invalid');$database=$this->boolean($input['databaseReady']??null,'release_database_status_invalid');$migrations=$this->boolean($input['migrationsReady']??null,'release_migration_status_invalid');$mail=$this->boolean($input['mailReady']??null,'release_mail_status_invalid');
        $routes=$input['routeSmoke']??null;if(!is_array($routes)||$routes===[]||count($routes)>100)throw new ProductOperatingSystemException('release_route_smoke_required');$routePass=true;$cleanRoutes=[];foreach($routes as$route){if(!is_array($route))throw new ProductOperatingSystemException('release_route_smoke_invalid');$path=$this->text($route['route']??null,300,'release_route_smoke_invalid');$status=$route['status']??null;if(!is_int($status)||$status<100||$status>599)throw new ProductOperatingSystemException('release_route_smoke_invalid');if($status<200||$status>=400)$routePass=false;$cleanRoutes[]=['route'=>$path,'status'=>$status];}
        $vulnerabilities=$input['dependencyVulnerabilityCount']??null;if(!is_int($vulnerabilities)||$vulnerabilities<0)throw new ProductOperatingSystemException('release_dependency_count_invalid');
        $checks=[
            ['key'=>'deployed_commit_matches_repo','status'=>$repoCommit===$deployedCommit?'pass':'fail','detail'=>$repoCommit===$deployedCommit?'Deployed commit matches the reviewed source commit.':'Deployment is stale: deployed commit does not match the reviewed source commit.'],
            ['key'=>'api_health','status'=>$api?'pass':'fail','detail'=>$api?'API health responded.':'API health failed.'],
            ['key'=>'database','status'=>$database?'pass':'fail','detail'=>$database?'Database communication is ready.':'Database communication is unavailable.'],
            ['key'=>'migrations','status'=>$migrations?'pass':'fail','detail'=>$migrations?'Schema migrations are current.':'Schema migrations are pending or unavailable.'],
            ['key'=>'route_smoke','status'=>$routePass?'pass':'fail','detail'=>$routePass?'All supplied route probes were loadable.':'At least one supplied route probe failed.'],
            ['key'=>'mail','status'=>$mail?'pass':($environment==='production'?'fail':'warn'),'detail'=>$mail?'Mail delivery is ready.':'Mail delivery is unavailable.'],
            ['key'=>'dependency_audit','status'=>$vulnerabilities===0?'pass':'warn','detail'=>$vulnerabilities.' dependency vulnerabilities were reported.'],
        ];
        $status=$this->gateStatus($checks);$summary=$status==='fail'?'Release gate failed: stale or unavailable deployment evidence blocks review readiness.':($status==='warn'?'Release gate warning: core deployment matches, but unresolved readiness warnings remain.':'Release gate passed with exact provenance and readiness evidence.');$id=Identifier::generate();
        $this->pdo->prepare('INSERT INTO deployment_release_gates(id,organisation_id,environment,repo_branch,repo_commit,deployed_branch,deployed_commit,build_timestamp,api_mode,api_health_ok,database_ready,migrations_ready,mail_ready,route_smoke,dependency_vulnerability_count,status,checks,summary,human_confirmed,created_by) VALUES(UNHEX(:id),UNHEX(:o),:environment,:repoBranch,:repoCommit,:deployedBranch,:deployedCommit,:built,:apiMode,:api,:database,:migrations,:mail,:routes,:vulnerabilities,:status,:checks,:summary,TRUE,UNHEX(:u))')->execute(['id'=>$id,'o'=>$o,'environment'=>$environment,'repoBranch'=>$repoBranch,'repoCommit'=>$repoCommit,'deployedBranch'=>$deployedBranch,'deployedCommit'=>$deployedCommit,'built'=>$built,'apiMode'=>$apiMode,'api'=>$api?1:0,'database'=>$database?1:0,'migrations'=>$migrations?1:0,'mail'=>$mail?1:0,'routes'=>$this->json($cleanRoutes),'vulnerabilities'=>$vulnerabilities,'status'=>$status,'checks'=>$this->json($checks),'summary'=>$summary,'u'=>$u]);
        (new AuditService($this->pdo))->append($o,$u,'operating.release_gate_recorded','deployment_release_gate',$id,['environment'=>$environment,'status'=>$status,'repoCommit'=>$repoCommit,'deployedCommit'=>$deployedCommit]);
        return$this->find($o,$id);
    }

    /** @return list<array<string,mixed>> */
    public function history(string$o,string$u,string$role):array
    {
        $this->membership($o,$u,$role);$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM deployment_release_gates WHERE organisation_id=UNHEX(:o) ORDER BY created_at DESC LIMIT 50');$s->execute(['o'=>$o]);return array_map($this->serialize(...),$s->fetchAll());
    }

    /** @return array<string,mixed> */ private function find(string$o,string$id):array{$s=$this->pdo->prepare('SELECT *,LOWER(HEX(id)) id FROM deployment_release_gates WHERE organisation_id=UNHEX(:o) AND id=UNHEX(:id)');$s->execute(['o'=>$o,'id'=>$id]);$r=$s->fetch();if(!is_array($r))throw new ProductOperatingSystemException('release_gate_not_found');return$this->serialize($r);}
    /** @param array<string,mixed> $row @return array<string,mixed> */ private function serialize(array$row):array{return['id'=>$row['id'],'environment'=>$row['environment'],'repoBranch'=>$row['repo_branch'],'repoCommit'=>$row['repo_commit'],'deployedBranch'=>$row['deployed_branch'],'deployedCommit'=>$row['deployed_commit'],'buildTimestamp'=>$row['build_timestamp'],'apiMode'=>$row['api_mode'],'apiHealthOk'=>(bool)$row['api_health_ok'],'databaseReady'=>(bool)$row['database_ready'],'migrationsReady'=>(bool)$row['migrations_ready'],'mailReady'=>(bool)$row['mail_ready'],'routeSmoke'=>$this->decode($row['route_smoke']),'dependencyVulnerabilityCount'=>(int)$row['dependency_vulnerability_count'],'status'=>$row['status'],'checks'=>$this->decode($row['checks']),'summary'=>$row['summary'],'humanConfirmed'=>(bool)$row['human_confirmed'],'createdAt'=>$row['created_at']];}
    /** @param list<array{status:string}> $checks */ private function gateStatus(array$checks):string{foreach($checks as$c)if($c['status']==='fail')return'fail';foreach($checks as$c)if($c['status']==='warn')return'warn';return'pass';}
    private function governor(string$o,string$u,string$role):void{$this->membership($o,$u,$role);if(!in_array($role,['owner','admin'],true))throw new ProductOperatingSystemException('release_gate_forbidden');}
    private function membership(string$o,string$u,string$role):void{$s=$this->pdo->prepare("SELECT role FROM organisation_memberships WHERE organisation_id=UNHEX(:o) AND user_id=UNHEX(:u) AND status='active'");$s->execute(['o'=>$o,'u'=>$u]);if($s->fetchColumn()!==$role)throw new ProductOperatingSystemException('operating_membership_required');}
    private function text(mixed$v,int$max,string$error):string{if(!is_string($v)||trim($v)===''||mb_strlen(trim($v))>$max)throw new ProductOperatingSystemException($error);return trim($v);}
    private function choice(mixed$v,array$choices,string$error):string{if(!is_string($v)||!in_array($v,$choices,true))throw new ProductOperatingSystemException($error);return$v;}
    private function commit(mixed$v,string$error):string{if(!is_string($v)||preg_match('/\A[a-f0-9]{40}\z/i',$v)!==1)throw new ProductOperatingSystemException($error);return strtolower($v);}
    private function timestamp(mixed$v):string{if(!is_string($v))throw new ProductOperatingSystemException('release_build_timestamp_invalid');try{return(new DateTimeImmutable($v))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}catch(\Throwable){throw new ProductOperatingSystemException('release_build_timestamp_invalid');}}
    private function boolean(mixed$v,string$error):bool{if(!is_bool($v))throw new ProductOperatingSystemException($error);return$v;}
    private function json(mixed$v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
    /** @return array<mixed> */ private function decode(mixed$v):array{if(!is_string($v))return[];try{$d=json_decode($v,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){return[];}return is_array($d)?$d:[];}
}
