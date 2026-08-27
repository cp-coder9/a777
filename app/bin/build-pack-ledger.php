<?php

declare(strict_types=1);

use Architex\Requirements\LedgerArtifactWriter;
use Architex\Requirements\PackLedgerBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$options = getopt('', ['packs:', 'json:', 'markdown:']);
if (!isset($options['packs'], $options['json'], $options['markdown'])) {
    fwrite(STDERR, "Usage: php app/bin/build-pack-ledger.php --packs=<directory> --json=<file> --markdown=<file>\n");
    exit(64);
}

$builder = new PackLedgerBuilder();
$ledger = $builder->build($options['packs']);
(new LedgerArtifactWriter())->write($options['json'], $options['markdown'], $ledger, $builder);
printf("Generated %d sources, %d requirements, 0 implemented requirements.\n", count($ledger['sources']), count($ledger['requirements']));
