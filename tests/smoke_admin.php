<?php
declare(strict_types=1);

$uri = $argv[1] ?? '/admin';
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/admin/index.php';
