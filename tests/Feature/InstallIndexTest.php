<?php

test('installer creates public html storage symlink when missing', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'localhost';

    $root = base_path('storage/framework/testing/install-index');
    $testBasePath = $root.DIRECTORY_SEPARATOR.'laravel';
    $publicHtmlPath = $root.DIRECTORY_SEPARATOR.'public_html';
    $targetPath = $testBasePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

    $delete = function (string $path) use (&$delete): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $item) {
            $delete($path.DIRECTORY_SEPARATOR.$item);
        }

        @rmdir($path);
    };

    $delete($root);
    mkdir($targetPath, 0755, true);
    mkdir($publicHtmlPath, 0755, true);

    ob_start();
    require base_path('public/install/index.php');
    ob_end_clean();

    $result = ensurePublicHtmlStorageSymlink($testBasePath);

    expect($result)
        ->toContain('public_html/storage dibuat')
        ->toContain('storage/app/public');

    $delete($root);
});
