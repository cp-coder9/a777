<?php
declare(strict_types=1);
namespace Architex\Tests\Unit\Toolbox;
use Architex\Platform\Toolbox\ProductionToolRegistry;
use PHPUnit\Framework\TestCase;
final class ProductionWorkflowDiscoveryTest extends TestCase
{
    public function testDiscoveryUsesMembershipScopesNotProfessionalLabels(): void
    {
        $viewer=ProductionToolRegistry::workspaceAccess('admin_review','viewer');
        self::assertFalse($viewer['read']);
        self::assertFalse($viewer['write']);
        self::assertTrue(ProductionToolRegistry::workspaceAccess('project_explorer','viewer')['read']);
        self::assertFalse(ProductionToolRegistry::workspaceAccess('bom','client')['read']);
        self::assertTrue(ProductionToolRegistry::workspaceAccess('admin_review','owner')['review']);
        self::assertFalse(ProductionToolRegistry::workspaceAccess('admin_review','member')['review']);
        self::assertFalse(ProductionToolRegistry::workspaceAccess('not_registered','owner')['read']);
    }
    public function testCapabilityDiscoveryRetainsIntegrationBoundariesAndClosedDefault(): void
    {
        $service=new \Architex\Platform\OperatingSystem\ApiCapabilityService();
        $report=$service->report('viewer','client');
        self::assertSame('provider_gated',$report['capabilities']['payments']['status']);
        self::assertSame('human_gated',$report['capabilities']['municipal']['status']);
        self::assertNotEmpty($report['workspaces']);
        foreach($service->report()['workspaces'] as $tool) self::assertFalse($tool['access']['read']);
        foreach($report['workspaces'] as $tool) self::assertFalse($tool['access']['write']);
    }
    public function testEveryActualProfileAndServerRoleHasAClosedStageToolInventory(): void
    {
        $roles=\Architex\Platform\Wingman\WingmanRoleTutorials::roles();
        $registration=file_get_contents(dirname(__DIR__,3).'/src/Platform/Identity/RegisterOrganisation.php');
        preg_match('/\\$roles = (\\[[^;]+\\]);/',$registration,$match);
        preg_match_all("/'([^']+)'/",$match[1],$registered);
        self::assertSame($registered[1],$roles);
        $scopes=array_keys((new \ReflectionClass(\Architex\Platform\Authorization\PermissionService::class))->getConstant('ROLE_PERMISSIONS'));
        $ids=array_column(json_decode(file_get_contents(dirname(__DIR__,3).'/public/assets/tool-registry.json'),true)['tools'],'id');
        foreach($roles as $profile)foreach($scopes as $scope){
            $catalogue=ProductionToolRegistry::workspaceCatalogue($scope,$profile);
            self::assertSame($ids,array_column($catalogue,'id'));
            self::assertNotEmpty(array_filter($catalogue,fn($tool)=>$tool['recommended']&&$tool['access']['read']),$profile.'/'.$scope);
            foreach($catalogue as $tool){self::assertNotNull($tool['placement']);self::assertSame('server_membership',$tool['authorizationSource']);if($scope==='viewer')self::assertFalse($tool['access']['write']);}
        }
        foreach(ProductionToolRegistry::workspaceCatalogue('platform_admin','platform_admin') as $tool)self::assertFalse($tool['access']['read']);
        foreach(ProductionToolRegistry::workspaceCatalogue('owner','invalid') as $tool)self::assertFalse($tool['recommended']);
    }
    public function testMalformedRegistryFailsUnavailableRatherThanAnEmptySuccessfulCatalogue(): void
    {
        $path=dirname(__DIR__).'/broken-tools-registry.json';
        $org=str_repeat('a',32);$identity=new \Architex\Platform\Identity\AuthenticatedIdentity(str_repeat('b',32),'owner@example.test',[['organisationId'=>$org,'role'=>'owner']]);
        $request=\Architex\Http\Request::json('GET','/api/v1/tools')->withAttribute('identity',$identity)->withAttribute('organisationId',$org)->withAttribute('role','owner');
        try{foreach(['null','{}','{"tools":"invalid"}','{"tools":[{"id":"bom"},{"id":"bom"}]}'] as $json){file_put_contents($path,$json);self::assertSame(503,(new \Architex\Http\Controller\ToolRegistryController($path))->index($request)->status());}}finally{if(is_file($path))unlink($path);}
    }
    public function testRegistryRejectsMissingOrMismatchedServerScope(): void
    {
        $controller=new \Architex\Http\Controller\ToolRegistryController(dirname(__DIR__,3).'/public/assets/tool-registry.json');
        $request=\Architex\Http\Request::json('GET','/api/v1/tools');
        self::assertSame(401,$controller->index($request)->status());
        $org=str_repeat('a',32);
        $identity=new \Architex\Platform\Identity\AuthenticatedIdentity(str_repeat('b',32),'viewer@example.test',[['organisationId'=>$org,'role'=>'viewer','profileRole'=>'platform_admin']]);
        $scoped=$request->withAttribute('identity',$identity)->withAttribute('organisationId',$org)->withAttribute('role','viewer');
        $response=$controller->index($scoped);
        self::assertSame(200,$response->status());
        $tools=array_column($response->json()['data'],null,'id');
        self::assertFalse($tools['admin_review']['access']['read']);
        self::assertFalse($tools['safety']['nativeActions'][0]['allowed']);
        self::assertSame(403,$controller->index($scoped->withAttribute('role','owner'))->status());
    }
}
