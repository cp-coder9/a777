<?php

declare(strict_types=1);

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Platform\Community\CommunityMessagingService;

require dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Community delivery must run from the PHP CLI.\n");
    exit(1);
}

$environment = getenv();
$config = AppConfig::fromEnvironment(is_array($environment) ? $environment : []);
$service = new CommunityMessagingService(ConnectionFactory::create($config));
$result = $service->deliverDueNotifications(500);
printf("community_notifications_delivered=%d remaining=%d\n", $result['delivered'], $result['remaining']);
