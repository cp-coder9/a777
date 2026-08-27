<?php
declare(strict_types=1);
namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Identity\AuthenticatedIdentity;
use Architex\Platform\Inbox\InboxService;

final readonly class InboxController
{
    public function __construct(private InboxService $inbox) {}
    public function index(Request $request): Response
    {
        $organisationId = $request->attribute('organisationId');
        $role = $request->attribute('role');
        $identity = $request->attribute('identity');
        if (!is_string($organisationId) || !is_string($role) || !$identity instanceof AuthenticatedIdentity) return Response::error(401, 'authentication_required', 'Authentication is required.');
        return Response::success($this->inbox->list($organisationId, $identity->userId, $role));
    }
}
