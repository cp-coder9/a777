<?php

declare(strict_types=1);

use Architex\Requirements\PackLedgerBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$options = getopt('', ['packs:', 'json:', 'markdown:']);
if (!isset($options['packs'], $options['json'], $options['markdown'])) {
    fwrite(STDERR, "Usage: php app/bin/build-pack-ledger.php --packs=<directory> --json=<file> --markdown=<file>\n");
    exit(64);
}

$builder = new PackLedgerBuilder();
$ledger = $builder->build($options['packs']);

foreach ([$options['json'], $options['markdown']] as $path) {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create output directory: %s', $directory));
    }
}

$jsonHandle = fopen($options['json'], 'wb');
if ($jsonHandle === false) {
    throw new RuntimeException(sprintf('Unable to write JSON ledger: %s', $options['json']));
}

try {
    fwrite($jsonHandle, "{\n  \"schemaVersion\": 1,\n  \"sources\": [\n");
    foreach ($ledger['sources'] as $index => $source) {
        fwrite($jsonHandle, '    ' . json_encode($source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        fwrite($jsonHandle, $index === array_key_last($ledger['sources']) ? "\n" : ",\n");
    }
    fwrite($jsonHandle, "  ],\n  \"requirements\": [\n");
    foreach ($ledger['requirements'] as $index => $requirement) {
        fwrite($jsonHandle, '    ' . json_encode($requirement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        fwrite($jsonHandle, $index === array_key_last($ledger['requirements']) ? "\n" : ",\n");
    }
    fwrite($jsonHandle, "  ]\n}\n");
} finally {
    fclose($jsonHandle);
}

$builder->writeMarkdown($options['markdown'], $ledger);

printf(
    "Generated %d sources, %d requirements, %d implemented requirements.\n",
    count($ledger['sources']),
    count($ledger['requirements']),
    count(array_filter($ledger['requirements'], static fn (array $requirement): bool => $requirement['status'] === 'implemented')),
);
