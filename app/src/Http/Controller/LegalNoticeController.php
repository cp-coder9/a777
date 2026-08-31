<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;
use Architex\Platform\Legal\LegalAcceptanceService;

final readonly class LegalNoticeController
{
    public function __construct(private LegalAcceptanceService $notices) {}

    public function signup(Request $request): Response
    {
        return Response::success($this->notices->currentSignupNotices());
    }
}
