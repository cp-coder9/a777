<?php

declare(strict_types=1);

namespace Architex\Http\Controller;

use Architex\Http\Request;
use Architex\Http\Response;

final class HealthController
{
    public function __invoke(Request $request): Response
    {
        return Response::success(['status' => 'ok']);
    }
}
