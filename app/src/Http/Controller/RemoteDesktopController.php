<?php
declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Infrastructure\Integration\IntegrationException;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\RemoteDesktop\RemoteDesktopService;

final readonly class RemoteDesktopController
{
    public function __construct(private RemoteDesktopService $service) {}
    public function capabilities(Request $r): Response { return $this->authenticated($r,fn($o,$a)=>$this->service->capabilities($o,$a,$this->id($r,'projectId'))); }
    public function create(Request $r): Response { return $this->authenticated($r,fn($o,$a)=>$this->service->create($o,$a,$this->id($r,'projectId'),$r->jsonBody())); }
    public function show(Request $r): Response { return $this->authenticated($r,fn($o,$a)=>$this->service->find($o,$a,$this->id($r,'sessionId'))); }
    public function ticket(Request $r): Response { return $this->authenticated($r,fn($o,$a)=>$this->service->viewerTicket($o,$a,$this->id($r,'sessionId'),$r->jsonBody())); }
    public function close(Request $r): Response { return $this->authenticated($r,fn($o,$a)=>$this->service->close($o,$a,$this->id($r,'sessionId'),$r->jsonBody())); }
    public function cleanup(Request $r): Response
    {
        // Use persisted session ownership; clients omit X-Organisation-Id after revocation.
        $identity=$r->attribute('identity');
        if (!$identity instanceof AuthenticatedIdentity) return Response::error(401,'authentication_required','Authentication is required.');
        try { return Response::success($this->service->close($this->id($r,'organisationId'),$identity->userId,$this->id($r,'sessionId'),$r->jsonBody()),headers:['Cache-Control'=>'no-store']); }
        catch (\JsonException) { return Response::error(400,'invalid_json','Request body must contain valid JSON.'); }
        catch (IntegrationException $e) { return $this->error($e); }
    }
    public function callback(Request $r): Response
    {
        try { return Response::success($this->service->callback($this->id($r,'organisationId'),$r->body(),$r->header('X-Architex-Provider-Timestamp') ?? '',$r->header('X-Architex-Provider-Signature') ?? ''),status:202); }
        catch (IntegrationException $e) { return $this->error($e); }
    }
    private function authenticated(Request $r,callable $operation): Response
    {
        $org=$r->attribute('organisationId'); $identity=$r->attribute('identity');
        if (!is_string($org) || !$identity instanceof AuthenticatedIdentity) return Response::error(401,'authentication_required','Authentication is required.');
        try { return Response::success($operation($org,$identity->userId),headers:['Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer']); }
        catch (\JsonException) { return Response::error(400,'invalid_json','Request body must contain valid JSON.'); }
        catch (IntegrationException $e) { return $this->error($e); }
    }
    private function id(Request $r,string $key): string { $id=$r->attribute($key); if (!is_string($id) || preg_match('/\A[a-f0-9]{32}\z/iD',$id)!==1) throw new IntegrationException('desktop_scope_invalid',422); return strtolower($id); }
    private function error(IntegrationException $e): Response { return Response::error($e->httpStatus,$e->reason,'The remote desktop integration could not complete this request.',headers:['Cache-Control'=>'no-store']); }
}
