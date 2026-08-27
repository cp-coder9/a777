<?php

declare(strict_types=1);

use Architex\Http\Controller\HealthController;
use Architex\Http\Kernel;
use Architex\Http\Request;
use Architex\Http\Router;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$router = new Router();
$router->get('/api/v1/health', new HealthController());

(new Kernel($router))->handle(Request::fromGlobals())->emit();
