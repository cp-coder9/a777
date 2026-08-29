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
$existingRegistry = is_file($assets . '/tool-registry.json') ? json_decode((string) file_get_contents($assets . '/tool-registry.json'), true) : [];
$existingStatuses = [];
foreach (is_array($existingRegistry['tools'] ?? null) ? $existingRegistry['tools'] : [] as $existingTool) {
    if (is_string($existingTool['id'] ?? null) && is_string($existingTool['status'] ?? null)) $existingStatuses[$existingTool['id']] = $existingTool['status'];
}
$registry = [];
$live = 0;
foreach ($tools as $id => $tool) {
    $sourceIsLive = ($tool['status'] ?? '') === 'live';
    $status = $sourceIsLive ? (($existingStatuses[$id] ?? null) === 'live' ? 'live' : 'prototype_live') : 'scaffold';
    if ($sourceIsLive) $live++;
    $registry[] = ['id' => $id, 'name' => $tool['name'] ?? $id, 'status' => $status, 'group' => $tool['group'] ?? '', 'stage' => $tool['stage'] ?? ''];
}
$manifest = ['source'=>'v1.html','sourceSha256'=>$sourceHash,'toolCount'=>count($registry),'prototypeLiveCount'=>$live,'scaffoldCount'=>count($registry)-$live];
$generated = preg_replace('/<style>.*?<\/style>/s', '<link rel="stylesheet" href="/assets/css/architex-v1.css">' . "\n" . '<link rel="stylesheet" href="/assets/css/community.css">' . "\n" . '<link rel="stylesheet" href="/assets/css/demo-sandbox.css">', $html, 1);
if (!is_string($generated)) throw new RuntimeException('Unable to generate shell.');
$description = '<meta name="description" content="Architex OS project and standalone built-environment workflows with governed evidence and professional review gates.">';
$generated = str_replace('<title>', $description . "\n<title>", $generated);
$drawingTool = 'TOOLS.drawing_compliance_navigator={name:"SA Council Drawing Compliance Navigator",icon:"council_navigator",tone:"cobalt",group:"Planning & Approvals",stage:"Design / Comply",summary:"Evidence-first drawing package, Part K boundary, occupancy, cross-reference and SPLUMA screening with a responsible-professional gate.",tabs:[{label:"Review Workspace",kind:"top",icon:"documents_drawings"}],status:"live",source:"Pack 20 RevF · illustrative POC until verified"};';
$generated = str_replace('const project={', $drawingTool . "\n" . 'const project={', $generated);
$accessStart = strpos($generated, '  const V8_ROLE_ACCESS={');
$accessEnd = $accessStart === false ? false : strpos($generated, "\n  };", $accessStart);
if ($accessStart === false || $accessEnd === false) throw new RuntimeException('V8 role access registry was not found.');
$accessLength = $accessEnd + 5 - $accessStart;
$access = substr($generated, $accessStart, $accessLength);
$access = str_replace("'planning','municipal','xa'", "'planning','municipal','drawing_compliance_navigator','xa'", $access);
$access = str_replace("'ncr_manager','xa'", "'ncr_manager','drawing_compliance_navigator','xa'", $access);
$access = str_replace("'planning','municipal','forms'", "'planning','municipal','drawing_compliance_navigator','forms'", $access);
$generated = substr_replace($generated, $access, $accessStart, $accessLength);
$headAssets = '<link rel="icon" href="/assets/icons/community.svg">' . "\n" . '<link rel="manifest" href="/community.webmanifest">' . "\n" . '<meta name="theme-color" content="#116b67">';
$generated = str_replace('</head>', $headAssets . "\n</head>", $generated);
$generated = str_replace('<select class="project-select">', '<select class="project-select" name="projectSelector" aria-label="Active project">', $generated);
$generated = str_replace('<textarea id="feedbackText"', '<textarea id="feedbackText" name="feedback" aria-label="Feedback description"', $generated);
$scripts = '<script src="/assets/js/api-client.js"></script><script src="/assets/js/project-datum.js"></script><script src="/assets/js/tool-registry.js"></script><script src="/assets/js/shell.js"></script><script src="/assets/js/demo-sandbox.js"></script>';
$generated = str_replace('</body>', $scripts . "\n</body>", $generated);
$existingCss = is_file($assets . '/css/architex-v1.css') ? (string) file_get_contents($assets . '/css/architex-v1.css') : '';
$extensionMarker = '/* Release 2 project-team workspace */';
$extensionOffset = strpos($existingCss, $extensionMarker);
$productionExtensions = $extensionOffset === false ? '' : "\n" . substr($existingCss, $extensionOffset);
file_put_contents($assets . '/css/architex-v1.css', "/* canonical-source-sha256: {$sourceHash} */\n" . trim($styleMatch[1]) . "\n" . $productionExtensions);
file_put_contents($assets . '/tool-registry.json', json_encode(['tools'=>$registry], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
file_put_contents($assets . '/v1-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
file_put_contents($public . '/app.php', rtrim($generated) . "\n");
printf("source_sha256=%s tools=%d prototype_live=%d scaffold=%d\n", $sourceHash, count($registry), $live, count($registry)-$live);
