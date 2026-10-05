<?php
declare(strict_types=1);
namespace Architex\Http\Controller;
use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Feedback\FeedbackService;
use Architex\Platform\Feedback\FeedbackInput;
use Architex\Platform\Feedback\FeedbackException;
use JsonException;
final readonly class FeedbackController
{
    public function __construct(private FeedbackService $service) {}
    public function index(Request $request): Response { return $this->handle($request,'list'); }
    public function show(Request $request): Response { return $this->handle($request,'find'); }
    public function create(Request $request): Response { return $this->handle($request,'create'); }
    private function handle(Request $request,string $action): Response
    {
        $identity=$request->attribute('identity');$org=$request->attribute('organisationId');
        if (!$identity instanceof AuthenticatedIdentity || !is_string($org)) return Response::error(401,'authentication_required','Authentication and an organisation context are required.');
        try {
            $data=match($action) {
                'list'=>$this->service->list($org,$identity->userId),
                'find'=>$this->service->find($org,$identity->userId,is_string($request->attribute('id'))?$request->attribute('id'):''),
                'create'=>$this->service->create($org,$identity->userId,FeedbackInput::fromArray($request->jsonBody())),
            };
            return Response::success($data,status:$action==='create'?201:200);
        } catch (JsonException) { return Response::error(400,'invalid_json','Request body must contain a JSON object.'); }
        catch (FeedbackException $e) { return Response::error($e->status,$e->reason,'The feedback request could not be completed.'); }
    }
}
