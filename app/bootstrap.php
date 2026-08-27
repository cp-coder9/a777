<?php

declare(strict_types=1);

$applicationRoot = __DIR__;
$projectRoot = is_file($applicationRoot . '/vendor/autoload.php') ? $applicationRoot : dirname($applicationRoot);
$autoload = $projectRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Runtime dependencies are unavailable.');
}
require $autoload;
\Architex\Infrastructure\Config\EnvironmentLoader::apply($applicationRoot);
