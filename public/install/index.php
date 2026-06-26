<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

error_reporting(isset($_GET['debug']) ? E_ALL : 0);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');

$basePathCandidates = [
    __DIR__.'/../..',
    __DIR__.'/../../laravel',
    __DIR__.'/../laravel',
    __DIR__.'/..',
];
$basePathChecks = [];
$basePath = false;

foreach ($basePathCandidates as $candidate) {
    $realPath = realpath($candidate);
    $hasArtisan = $realPath && file_exists($realPath.'/artisan');
    $basePathChecks[] = ($realPath ?: $candidate).' | artisan: '.($hasArtisan ? 'yes' : 'no');

    if ($hasArtisan) {
        $basePath = $realPath;
        break;
    }
}

if (! $basePath || ! file_exists($basePath.'/artisan')) {
    http_response_code(500);
    exit('Root Laravel tidak ditemukan.');
}

$incomingRoot = trim((string) ($_POST['source_path'] ?? $_GET['source_path'] ?? ''));
if ($incomingRoot === '') {
    $incomingRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
} else {
    $incomingRoot = realpath($incomingRoot) ?: $incomingRoot;
}
$publicRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$envPath = $basePath.'/.env';
$lockPath = $basePath.'/storage/installer.lock';
$debugLog = [
    'install_dir: '.__DIR__,
    'incoming_root: '.$incomingRoot,
    'public_root: '.$publicRoot,
    'base_path: '.$basePath,
    'env_path: '.$envPath.' | exists: '.(file_exists($envPath) ? 'yes' : 'no'),
    'lock_path: '.$lockPath.' | exists: '.(file_exists($lockPath) ? 'yes' : 'no'),
    'base_path_checks:',
    ...$basePathChecks,
];
$isInstalled = file_exists($envPath) || file_exists($lockPath);
$mode = (string) ($_POST['mode'] ?? $_GET['mode'] ?? ($isInstalled ? 'update' : 'install'));
$result = null;

function envValue(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/[\s#"\'\\]/', $value)) {
        return '"'.str_replace('"', '\\"', $value).'"';
    }

    return $value;
}

function setEnv(string $content, string $key, string $value): string
{
    $line = $key.'='.envValue($value);
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, $line, $content);
    }

    return rtrim($content, "\r\n")."\n".$line."\n";
}

function phpBinary(): string
{
    foreach (['php', '/opt/cpanel/ea-php83/root/usr/bin/php', '/opt/cpanel/ea-php82/root/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php'] as $binary) {
        $output = @shell_exec($binary.' --version 2>&1');
        if (is_string($output) && str_contains($output, 'PHP')) {
            return $binary;
        }
    }

    return 'php';
}

function bootArtisanKernel(string $basePath)
{
    static $kernel = null;

    if ($kernel !== null) {
        return $kernel;
    }

    require_once $basePath.'/vendor/autoload.php';

    /** @var Application $app */
    $app = require_once $basePath.'/bootstrap/app.php';

    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    return $kernel;
}

function artisanCall(string $basePath, string $command, array $args = []): string
{
    if (function_exists('shell_exec')) {
        $php = phpBinary();
        $artisan = escapeshellarg($basePath.'/artisan');
        $cli = $command;

        foreach ($args as $key => $value) {
            if (! str_starts_with($key, '--')) {
                continue;
            }

            if ($value === true) {
                $cli .= ' '.$key;
            } elseif ($value !== false) {
                $cli .= ' '.$key.'='.escapeshellarg((string) $value);
            }
        }

        return (string) @shell_exec($php.' '.$artisan.' '.$cli.' 2>&1');
    }

    try {
        bootArtisanKernel($basePath);
        Artisan::call($command, $args);

        return (string) Artisan::output();
    } catch (Throwable $exception) {
        return 'artisan call failed: '.$exception->getMessage();
    }
}

function ensureParentDirectory(string $path): void
{
    $parent = dirname($path);
    if (! is_dir($parent)) {
        mkdir($parent, 0755, true);
    }
}

function deletePath(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (array_diff(scandir($path), ['.', '..']) as $item) {
        deletePath($path.DIRECTORY_SEPARATOR.$item);
    }

    @rmdir($path);
}

function copyDirectoryContents(string $source, string $target): void
{
    if (! is_dir($source)) {
        return;
    }

    if (! is_dir($target)) {
        mkdir($target, 0755, true);
    }

    foreach (array_diff(scandir($source), ['.', '..']) as $item) {
        $sourcePath = $source.DIRECTORY_SEPARATOR.$item;
        $targetPath = $target.DIRECTORY_SEPARATOR.$item;

        if (is_dir($sourcePath) && ! is_link($sourcePath)) {
            copyDirectoryContents($sourcePath, $targetPath);

            continue;
        }

        ensureParentDirectory($targetPath);
        copy($sourcePath, $targetPath);
    }
}

function replacePath(string $source, string $target): bool
{
    if (! file_exists($source) && ! is_link($source)) {
        return false;
    }

    if (file_exists($target) || is_link($target)) {
        deletePath($target);
    }

    ensureParentDirectory($target);

    if (@rename($source, $target)) {
        return true;
    }

    if (is_dir($source) && ! is_link($source)) {
        copyDirectoryContents($source, $target);
        deletePath($source);

        return file_exists($target);
    }

    if (@copy($source, $target)) {
        @unlink($source);

        return true;
    }

    return false;
}

function findPackageContentDir(string $incomingRoot, array $logs): array
{
    if (file_exists($incomingRoot.'/artisan') && is_dir($incomingRoot.'/public')) {
        return [$incomingRoot, $logs];
    }

    foreach (array_diff(scandir($incomingRoot), ['.', '..']) as $name) {
        $candidate = $incomingRoot.DIRECTORY_SEPARATOR.$name;
        if (is_dir($candidate) && file_exists($candidate.'/artisan') && is_dir($candidate.'/public')) {
            $logs[] = 'package content dir detected: '.$candidate;

            return [$candidate, $logs];
        }
    }

    return [$incomingRoot, $logs];
}

function syncSharedHostingStructure(string $incomingRoot, string $basePath, string $publicRoot): array
{
    $logs = [
        'sync start',
        'incoming_root: '.$incomingRoot,
        'base_path: '.$basePath,
        'public_root: '.$publicRoot,
    ];

    if (! is_dir($incomingRoot)) {
        throw new RuntimeException('Incoming root tidak ditemukan: '.$incomingRoot);
    }

    [$incomingRoot, $logs] = findPackageContentDir($incomingRoot, $logs);
    $incomingPublicDir = $incomingRoot.DIRECTORY_SEPARATOR.'public';

    if (! is_dir($incomingPublicDir)) {
        throw new RuntimeException('Folder public package tidak ditemukan: '.$incomingPublicDir);
    }

    $logs[] = 'resolved incoming_root: '.$incomingRoot;
    $movedCount = 0;

    $backendDirs = [
        'app',
        'bootstrap',
        'config',
        'database',
        'resources',
        'routes',
        'vendor',
    ];
    $backendFiles = [
        'artisan',
        'composer.json',
        'composer.lock',
        '.env.example',
        'package.json',
        'package-lock.json',
        'vite.config.ts',
        'phpunit.xml',
        'README.md',
    ];
    $publicSkip = ['install', '.htaccess'];

    foreach ($backendDirs as $name) {
        $source = $incomingRoot.DIRECTORY_SEPARATOR.$name;
        $target = $basePath.DIRECTORY_SEPARATOR.$name;
        if (! file_exists($source)) {
            $logs[] = 'backend dir missing: '.$source;

            continue;
        }

        $logs[] = 'backend dir move: '.$source.' -> '.$target;
        $ok = replacePath($source, $target);
        $logs[] = 'backend dir '.($ok ? 'done' : 'failed').': '.$name;
        $movedCount += $ok ? 1 : 0;
    }

    foreach ($backendFiles as $name) {
        $source = $incomingRoot.DIRECTORY_SEPARATOR.$name;
        $target = $basePath.DIRECTORY_SEPARATOR.$name;
        if (! file_exists($source)) {
            $logs[] = 'backend file missing: '.$source;

            continue;
        }

        $logs[] = 'backend file move: '.$source.' -> '.$target;
        $ok = replacePath($source, $target);
        $logs[] = 'backend file '.($ok ? 'done' : 'failed').': '.$name;
        $movedCount += $ok ? 1 : 0;
    }

    foreach (array_diff(scandir($incomingPublicDir), ['.', '..']) as $name) {
        if (in_array($name, $publicSkip, true)) {
            $logs[] = 'public skip: '.$name;

            continue;
        }

        $source = $incomingPublicDir.DIRECTORY_SEPARATOR.$name;
        $target = ($name === 'build')
            ? $basePath.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$name
            : $publicRoot.DIRECTORY_SEPARATOR.$name;
        $logs[] = 'public move: '.$source.' -> '.$target;
        $ok = replacePath($source, $target);
        $logs[] = 'public '.($ok ? 'done' : 'failed').': '.$name;
        $movedCount += $ok ? 1 : 0;
    }

    $htaccessSource = $incomingPublicDir.DIRECTORY_SEPARATOR.'.htaccess';
    $htaccessTarget = $publicRoot.DIRECTORY_SEPARATOR.'.htaccess';
    if (file_exists($htaccessSource)) {
        $logs[] = 'public move: '.$htaccessSource.' -> '.$htaccessTarget;
        $ok = replacePath($htaccessSource, $htaccessTarget);
        $logs[] = 'public '.($ok ? 'done' : 'failed').': .htaccess';
        $movedCount += $ok ? 1 : 0;
    } else {
        $logs[] = 'public missing: '.$htaccessSource;
    }

    if ($movedCount === 0) {
        throw new RuntimeException('Tidak ada file/package yang dipindah. Periksa path folder package: '.$incomingRoot);
    }

    $staleHotFile = $publicRoot.DIRECTORY_SEPARATOR.'hot';
    if (file_exists($staleHotFile)) {
        @unlink($staleHotFile);
        $logs[] = 'public removed stale: hot';
    }

    $logs[] = 'sync end | moved: '.$movedCount;

    return $logs;
}

function ensurePublicHtmlStorageSymlink(string $basePath): string
{
    $publicHtmlPath = dirname($basePath).DIRECTORY_SEPARATOR.'public_html';
    $linkPath = $publicHtmlPath.DIRECTORY_SEPARATOR.'storage';
    $targetPath = $basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

    if (! is_dir($publicHtmlPath)) {
        return 'public_html/storage skipped: folder public_html tidak ditemukan.';
    }

    if (! is_dir($targetPath)) {
        return 'public_html/storage skipped: folder target storage Laravel tidak ditemukan.';
    }

    if (is_link($linkPath)) {
        $existingTarget = readlink($linkPath);

        if ($existingTarget === $targetPath) {
            return 'public_html/storage sudah ada dan mengarah ke target yang benar.';
        }

        return 'public_html/storage sudah ada tetapi mengarah ke: '.(string) $existingTarget;
    }

    if (file_exists($linkPath)) {
        return 'public_html/storage sudah ada sebagai file atau folder biasa.';
    }

    if (@symlink($targetPath, $linkPath)) {
        return 'public_html/storage dibuat: '.$linkPath.' -> '.$targetPath;
    }

    return 'public_html/storage gagal dibuat. Pastikan symlink aktif dan permission folder public_html mencukupi.';
}

function runUpdateTasks(string $basePath): string
{
    $logs = [];
    $logs[] = artisanCall($basePath, 'down');
    $logs[] = artisanCall($basePath, 'migrate', ['--force' => true]);
    $logs[] = artisanCall($basePath, 'storage:link');
    $logs[] = ensurePublicHtmlStorageSymlink($basePath);
    $logs[] = artisanCall($basePath, 'optimize:clear');
    $logs[] = artisanCall($basePath, 'up');

    return trim(implode("\n", array_filter($logs)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'update') {
        if (! file_exists($envPath)) {
            $result = ['ok' => false, 'message' => 'Mode update butuh .env lama. Jalankan install baru jika belum ada .env.', 'logs' => implode("\n", $debugLog)];
        } else {
            try {
                $logs = [];
                $logs[] = implode("\n", syncSharedHostingStructure($incomingRoot, $basePath, $publicRoot));
                $logs[] = runUpdateTasks($basePath);
                @file_put_contents($lockPath, date('Y-m-d H:i:s'));
                $result = ['ok' => true, 'message' => 'Update selesai.', 'logs' => implode("\n", array_filter($logs))];
            } catch (Throwable $exception) {
                $result = [
                    'ok' => false,
                    'message' => 'Update gagal: '.$exception->getMessage(),
                    'logs' => implode("\n", [...$debugLog, 'exception: '.$exception->getMessage()]),
                ];
            }
        }
    } else {
        $appName = trim((string) ($_POST['app_name'] ?? 'API VD CO'));
        $appUrl = trim((string) ($_POST['app_url'] ?? ''));
        $dbConnection = trim((string) ($_POST['db_connection'] ?? 'mysql'));
        $dbHost = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
        $dbDatabase = trim((string) ($_POST['db_database'] ?? ''));
        $dbUsername = trim((string) ($_POST['db_username'] ?? ''));
        $dbPassword = (string) ($_POST['db_password'] ?? '');

        if ($appUrl === '') {
            $result = ['ok' => false, 'message' => 'APP_URL wajib diisi.'];
        } elseif ($dbConnection === 'mysql' && ($dbDatabase === '' || $dbUsername === '')) {
            $result = ['ok' => false, 'message' => 'Database dan username wajib diisi untuk MySQL.'];
        } else {
            try {
                $logs = [];
                $logs[] = implode("\n", syncSharedHostingStructure($incomingRoot, $basePath, $publicRoot));

                $envExample = $basePath.'/.env.example';
                $env = file_exists($envPath) ? file_get_contents($envPath) : file_get_contents($envExample);

                $env = setEnv($env, 'APP_NAME', $appName);
                $env = setEnv($env, 'APP_ENV', 'production');
                $env = setEnv($env, 'APP_DEBUG', 'false');
                $env = setEnv($env, 'APP_URL', $appUrl);
                $env = setEnv($env, 'DB_CONNECTION', $dbConnection);

                if ($dbConnection === 'mysql') {
                    $env = setEnv($env, 'DB_HOST', $dbHost);
                    $env = setEnv($env, 'DB_PORT', $dbPort);
                    $env = setEnv($env, 'DB_DATABASE', $dbDatabase);
                    $env = setEnv($env, 'DB_USERNAME', $dbUsername);
                    $env = setEnv($env, 'DB_PASSWORD', $dbPassword);
                }

                if (! @file_put_contents($envPath, $env)) {
                    $result = ['ok' => false, 'message' => 'Gagal menulis .env. Pastikan root aplikasi writable.', 'logs' => implode("\n", [...$debugLog, ...$logs])];
                } else {
                    $logs[] = artisanCall($basePath, 'key:generate', ['--force' => true]);
                    $logs[] = runUpdateTasks($basePath);
                    @file_put_contents($lockPath, date('Y-m-d H:i:s'));
                    $result = ['ok' => true, 'message' => 'Install selesai.', 'logs' => implode("\n", array_filter($logs))];
                }
            } catch (Throwable $exception) {
                $result = [
                    'ok' => false,
                    'message' => 'Install gagal: '.$exception->getMessage(),
                    'logs' => implode("\n", [...$debugLog, 'exception: '.$exception->getMessage()]),
                ];
            }
        }
    }
}

$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$defaultUrl = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install / Update API VD CO</title>
    <style>
        body {
            background: #0f172a;
            color: #e5e7eb;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            margin: 0;
        }

        main {
            max-width: 760px;
            margin: 40px auto;
            padding: 24px;
        }

        form,
        .box {
            background: #111827;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 24px;
        }

        label {
            display: block;
            margin-top: 14px;
            font-size: 14px;
            color: #cbd5e1;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            margin-top: 6px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #475569;
            background: #020617;
            color: #fff;
        }

        button,
        .tab {
            display: inline-block;
            margin-top: 20px;
            border: 0;
            border-radius: 10px;
            padding: 12px 16px;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .tab {
            margin: 0 8px 18px 0;
            background: #334155;
        }

        .active {
            background: #2563eb;
        }

        pre {
            white-space: pre-wrap;
            background: #020617;
            padding: 12px;
            border-radius: 10px;
            overflow: auto;
        }

        .ok {
            border-color: #16a34a;
            color: #bbf7d0;
        }

        .err {
            border-color: #dc2626;
            color: #fecaca;
        }
    </style>
</head>

<body>
    <main>
        <h1>Install / Update API VD CO</h1>
        <p><strong>Install</strong> memindah backend ke folder laravel, memindah file public ke public_html, membuat <code>.env</code>, lalu lanjut key, migrate, dan clear cache. <strong>Update</strong> melakukan alur sama tanpa menulis ulang <code>.env</code>.</p>

        <a class="tab <?= $mode === 'install' ? 'active' : '' ?>" href="?mode=install">Install baru</a>
        <a class="tab <?= $mode === 'update' ? 'active' : '' ?>" href="?mode=update">Update aplikasi</a>

        <?php if ($result) { ?>
            <div class="box <?= $result['ok'] ? 'ok' : 'err' ?>">
                <strong><?= htmlspecialchars($result['message']) ?></strong>
                <?php if (! empty($result['logs'])) { ?>
                    <pre><?= htmlspecialchars($result['logs']) ?></pre>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (! ($result['ok'] ?? false) && $mode === 'update') { ?>
            <form method="post">
                <input type="hidden" name="mode" value="update">
                <p>Urutan update: pindah backend dari folder package ke folder <code>laravel</code>, pindah isi folder <code>public</code> ke <code>public_html</code>, lalu jalankan migrate dan clear cache.</p>
                <label>Path folder package baru (berisi <code>artisan</code> & <code>public/</code>)
                    <input name="source_path" value="<?= htmlspecialchars($incomingRoot) ?>" required>
                </label>
                <button type="submit">Jalankan Update</button>
            </form>
        <?php } ?>

        <?php if (! ($result['ok'] ?? false) && $mode !== 'update') { ?>
            <form method="post">
                <input type="hidden" name="mode" value="install">
                <p>Urutan install: pindah backend dari folder package ke folder <code>laravel</code>, pindah isi folder <code>public</code> ke <code>public_html</code>, buat <code>.env</code>, lalu generate key, migrate, dan clear cache.</p>
                <label>Path folder package baru (berisi <code>artisan</code> & <code>public/</code>)
                    <input name="source_path" value="<?= htmlspecialchars($incomingRoot) ?>" required>
                </label>
                <label>APP_NAME
                    <input name="app_name" value="API VD CO" required>
                </label>
                <label>APP_URL
                    <input name="app_url" value="<?= htmlspecialchars($defaultUrl) ?>" required>
                </label>
                <label>DB_CONNECTION
                    <select name="db_connection">
                        <option value="mysql">mysql</option>
                        <option value="sqlite">sqlite</option>
                    </select>
                </label>
                <label>DB_HOST
                    <input name="db_host" value="127.0.0.1">
                </label>
                <label>DB_PORT
                    <input name="db_port" value="3306">
                </label>
                <label>DB_DATABASE
                    <input name="db_database">
                </label>
                <label>DB_USERNAME
                    <input name="db_username">
                </label>
                <label>DB_PASSWORD
                    <input type="password" name="db_password">
                </label>
                <button type="submit">Install</button>
            </form>
        <?php } ?>

        <?php if (($result['ok'] ?? false)) { ?>
            <p>Hapus folder <code>public/install</code> atau <code>install</code> setelah selesai jika tidak diperlukan.</p>
        <?php } ?>
    </main>
</body>

</html>