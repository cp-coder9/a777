<?php

declare(strict_types=1);

use Architex\Infrastructure\Deployment\ReleaseVerifier;

require dirname(__DIR__) . '/bootstrap.php';

if ($argc !== 2) { fwrite(STDERR, "Usage: php app/bin/verify-release.php <release-directory>\n"); exit(2); }
$report = (new ReleaseVerifier())->verify($argv[1]);
if (!$report->valid) {
    fwrite(STDERR, 'Release verification failed: ' . implode(',', $report->errors) . PHP_EOL);
    exit(1);
}
fwrite(STDOUT, "Release verification passed: {$report->fileCount} files.\n");
exit(0);
