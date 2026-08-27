<?php

declare(strict_types=1);

$options = getopt('', ['source:', 'public:']);
$source = isset($options['source']) ? (string) $options['source'] : dirname(__DIR__, 2) . '/v1.html';
$public = isset($options['public']) ? rtrim((string) $options['public'], '/\\') : dirname(__DIR__) . '/public';
$html = file_get_contents($source);
if (!is_string($html)) {
    fwrite(STDERR, "Unable to read canonical V1 source.\n");
    exit(1);
}
if (preg_match('/<style>(.*?)<\/style>/s', $html, $styleMatch) !== 1) {
    fwrite(STDERR, "Canonical stylesheet was not found.\n");
    exit(1);
}
if (preg_match('/const TOOLS=(\{.*?\});\s*const /s', $html, $toolsMatch) !== 1) {
    fwrite(STDERR, "Canonical tool registry was not found.\n");
    exit(1);
}
$tools = json_decode($toolsMatch[1], true, flags: JSON_THROW_ON_ERROR);
if (!is_array($tools) || count($tools) !== 45) {
    fwrite(STDERR, 'Expected 45 canonical tools, found ' . (is_array($tools) ? count($tools) : 0) . ".\n");
    exit(1);
}
$assets = $public . '/assets';
foreach ([$public, $assets, $assets . '/css', $assets . '/js'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create ' . $directory);
    }
}
$sourceHash = hash('sha256', $html);
$registry = [];
$live = 0;
foreach ($tools as $id => $tool) {
    $status = ($tool['status'] ?? '') === 'live' ? 'prototype_live' : 'scaffold';
    if ($status === 'prototype_live') $live++;
    $registry[] = ['id' => $id, 'name' => $tool['name'] ?? $id, 'status' => $status, 'group' => $tool['group'] ?? '', 'stage' => $tool['stage'] ?? ''];
}
$manifest = ['source'=>'v1.html','sourceSha256'=>$sourceHash,'toolCount'=>count($registry),'prototypeLiveCount'=>$live,'scaffoldCount'=>count($registry)-$live];
$generated = preg_replace('/<style>.*?<\/style>/s', '<link rel="stylesheet" href="assets/css/architex-v1.css">', $html, 1);
if (!is_string($generated)) throw new RuntimeException('Unable to generate shell.');
$headAssets = '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22%3E%3Crect width=%2232%22 height=%2232%22 rx=%228%22 fill=%22%2319b7b0%22/%3E%3C/svg%3E">';
$generated = str_replace('</head>', $headAssets . "\n</head>", $generated);
$scripts = '<script src="assets/js/api-client.js"></script><script src="assets/js/project-datum.js"></script><script src="assets/js/tool-registry.js"></script><script src="assets/js/shell.js"></script>';
$generated = str_replace('</body>', $scripts . "\n</body>", $generated);
file_put_contents($assets . '/css/architex-v1.css', "/* canonical-source-sha256: {$sourceHash} */\n" . trim($styleMatch[1]) . "\n");
file_put_contents($assets . '/tool-registry.json', json_encode(['tools'=>$registry], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
file_put_contents($assets . '/v1-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
file_put_contents($public . '/app.php', $generated);
printf("source_sha256=%s tools=%d prototype_live=%d scaffold=%d\n", $sourceHash, count($registry), $live, count($registry)-$live);
