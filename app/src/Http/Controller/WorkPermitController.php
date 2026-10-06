<?php

declare(strict_types=1);
namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\SiteExecution\WorkPermitException;
use Architex\Platform\SiteExecution\WorkPermitPolicy;
use Architex\Platform\SiteExecution\WorkPermitService;
use JsonException;

final readonly class WorkPermitController
{
    public function __construct(private WorkPermitService $service) {}
    public function handle(Request $r,string $action): Response
    {
        try {
            $o=$r->attribute('organisationId');$identity=$r->attribute('identity');
            if(!is_string($o)||!$identity instanceof AuthenticatedIdentity)return Response::error(401,'authentication_required','Authentication required.');
            $p=WorkPermitPolicy::id($r->attribute('projectId'));$u=$identity->userId;
            $data=match($action){
                'index'=>$this->service->workspace($o,$u,$p),
                'show'=>$this->service->get($o,$u,$p,WorkPermitPolicy::id($r->attribute('permitId'))),
                'create'=>$this->service->create($o,$u,$p,$r->jsonBody()),
                'transition'=>$this->service->transition($o,$u,$p,WorkPermitPolicy::id($r->attribute('permitId')),$r->jsonBody()),
                default=>throw new WorkPermitException('work_permit_action_not_found',404),
            };
            return Response::success($data,status:$action==='create'?201:200);
        }catch(JsonException){return Response::error(400,'invalid_json','Request body must contain valid JSON.');}
        catch(WorkPermitException $e){return Response::error($e->status,$e->reason,'Internal work-permit request could not be completed.');}
    }
}