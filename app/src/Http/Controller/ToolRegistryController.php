<?php
declare(strict_types=1);
namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;

final readonly class ToolRegistryController
{
    public function __construct(private string $registryPath) {}
    public function index(Request $request): Response
    {
        $identity=$request->attribute('identity');
        $organisationId=$request->attribute('organisationId');
        $role=$request->attribute('role');
        if(!$identity instanceof \Architex\Platform\Identity\AuthenticatedIdentity || !is_string($organisationId)) return Response::error(401,'authentication_required','An authenticated organisation is required.');
        $membership=null;
        foreach($identity->memberships as $candidate) if(($candidate['organisationId']??null)===$organisationId) {$membership=$candidate;break;}
        if(!is_string($role)||($membership['role']??null)!==$role||!(new \Architex\Platform\Authorization\PermissionService())->allows($role,'projects.read')) return Response::error(403,'forbidden','This membership cannot discover workspace tools.');
        $json = @file_get_contents($this->registryPath);
        if (!is_string($json)) return Response::error(503, 'tool_registry_unavailable', 'Tool registry is unavailable.');
        try {
            $registry = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if(!is_array($registry)||!is_array($registry['tools']??null)||!array_is_list($registry['tools']))return Response::error(503,'tool_registry_unavailable','Tool registry is invalid.');
            $seen=[];
            $catalogue=array_column(\Architex\Platform\Toolbox\ProductionToolRegistry::workspaceCatalogue($role,is_string($membership['profileRole']??null)?$membership['profileRole']:null),null,'id');
            $tools=[];
            foreach($registry['tools']??[] as $tool) {
                if(!is_array($tool)||!is_string($tool['id']??null)||isset($seen[$tool['id']])) return Response::error(503,'tool_registry_unavailable','Tool registry is invalid.');
                $seen[$tool['id']]=true;
                $tools[]=$catalogue[$tool['id']]??array_replace($tool,['access'=>['read'=>false,'write'=>false,'review'=>false],'nativeActions'=>[]]);
            }
            return Response::success($tools, ['organisationId'=>$organisationId,'membershipRole'=>$role,'authorizationSource'=>'server_membership'],headers:['Cache-Control'=>'private, no-store']);
        } catch (\JsonException) {
            return Response::error(503, 'tool_registry_unavailable', 'Tool registry is unavailable.');
        }
    }
}
