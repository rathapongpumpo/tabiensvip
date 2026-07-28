<?php
declare(strict_types=1);

$uri = $argv[1] ?? '/';
$_SERVER['REQUEST_URI'] = $uri;
parse_str((string) parse_url($uri, PHP_URL_QUERY), $_GET);
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/index.php';
