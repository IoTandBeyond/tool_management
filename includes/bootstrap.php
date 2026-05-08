<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
$localPath = dirname(__DIR__) . '/config/config.local.php';
$config = file_exists($localPath) ? array_replace_recursive(require $configPath, require $localPath) : require $configPath;
$GLOBALS['config'] = $config;

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/scope.php';
