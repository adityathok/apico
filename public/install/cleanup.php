<?php

$basePath = realpath(__DIR__.'/../..');
if (! $basePath || ! file_exists($basePath.'/artisan')) {
    $basePath = realpath(__DIR__.'/..');
}
$lockPath = $basePath ? $basePath.'/storage/installer.lock' : '';

if ($lockPath === '' || ! file_exists($lockPath)) {
    http_response_code(403);
    exit('Installation not completed');
}

function deleteDirectory(string $dir): bool
{
    if (! is_dir($dir)) {
        return false;
    }

    foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
        $path = $dir.DIRECTORY_SEPARATOR.$file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }

    return rmdir($dir);
}

header('Content-Type: application/json');
echo json_encode([
    'success' => deleteDirectory(__DIR__),
]);
