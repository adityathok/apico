<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appBaseCandidates = [
    __DIR__.'/..',
    __DIR__.'/../laravel',
];
$appBasePath = null;

foreach ($appBaseCandidates as $candidate) {
    if (file_exists($candidate.'/vendor/autoload.php') && file_exists($candidate.'/bootstrap/app.php')) {
        $appBasePath = $candidate;
        break;
    }
}

if ($appBasePath === null) {
    throw new RuntimeException('Laravel base path tidak ditemukan dari public/index.php');
}

if (file_exists($maintenance = $appBasePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBasePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appBasePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
