<?php

declare(strict_types=1);

use Architex\Infrastructure\Config\AppConfig;
use Architex\Infrastructure\Database\ConnectionFactory;
use Architex\Infrastructure\Mail\MailRuntime;
use Architex\Platform\Jobs\JobDispatcher;

require dirname(__DIR__) . '/bootstrap.php';

$environment = getenv();
$environment = is_array($environment) ? $environment : [];
$limit = 25;
foreach (array_slice($argv ?? [], 1) as $argument) {
    if (preg_match('/\A--limit=([0-9]{1,3})\z/', $argument, $matches) === 1) {
        $limit = max(1, min(100, (int)$matches[1]));
    } elseif ($argument !== '--once') {
        fwrite(STDERR, "Unsupported dispatch option.\n");
        exit(2);
    }
}

try {
    $pdo = ConnectionFactory::create(AppConfig::fromEnvironment($environment));
    $runtime = MailRuntime::fromEnvironment($pdo, $environment);
    if ($runtime->cipher === null || $runtime->appUrl === '') {
        throw new RuntimeException('Mail dispatch is not configured.');
    }
    $report = (new JobDispatcher($pdo, $runtime->cipher, $runtime->mailer, $runtime->appUrl))->run($limit);
    fwrite(STDOUT, json_encode([
        'claimed' => $report->claimed,
        'succeeded' => $report->succeeded,
        'retried' => $report->retried,
        'dead' => $report->dead,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Mail dispatch is unavailable.\n");
    exit(1);
}
