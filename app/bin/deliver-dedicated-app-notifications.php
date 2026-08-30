<?php

declare(strict_types=1);

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Platform\DedicatedApps\DedicatedAppRegistry;
use Architex\Platform\DedicatedApps\DedicatedAppService;

require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dedicated-app delivery must run from the PHP CLI.\n");
    exit(1);
}

$environment = getenv();
$config = AppConfig::fromEnvironment(is_array($environment) ? $environment : []);
$service = new DedicatedAppService(ConnectionFactory::create($config), new DedicatedAppRegistry());
$result = $service->deliverQueuedNotifications(500);
printf(
    "dedicated_app_notifications_sent=%d integration_required=%d failed=%d remaining=%d\n",
    $result['sent'],
    $result['integrationRequired'],
    $result['failed'],
    $result['remaining'],
);
