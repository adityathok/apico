<?php

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

$incomingRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$publicRoot = $incomingRoot;
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

function artisan(string $basePath, string $command): string
{
    if (! function_exists('shell_exec')) {
        return 'shell_exec disabled. Jalankan manual: php artisan '.$command;
    }

    $php = phpBinary();
    $artisan = escapeshellarg($basePath.'/artisan');

    return (string) @shell_exec($php.' '.$artisan.' '.$command.' 2>&1');
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

function replacePath(string $source, string $target): void
{
    if (! file_exists($source) && ! is_link($source)) {
        return;
    }

    if (file_exists($target) || is_link($target)) {
        deletePath($target);
    }

    ensureParentDirectory($target);

    if (@rename($source, $target)) {
        return;
    }

    if (is_dir($source) && ! is_link($source)) {
        copyDirectoryContents($source, $target);
        deletePath($source);

        return;
    }

    copy($source, $target);
    @unlink($source);
}

function syncSharedHostingStructure(string $incomingRoot, string $basePath, string $publicRoot): array
{
    $logs = [];
    $incomingPublicDir = $incomingRoot.DIRECTORY_SEPARATOR.'public';

    if (! is_dir($incomingRoot)) {
        throw new RuntimeException('Incoming root tidak ditemukan: '.$incomingRoot);
    }

    if (! is_dir($incomingPublicDir)) {
        throw new RuntimeException('Folder public package tidak ditemukan: '.$incomingPublicDir);
    }

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
        if (! file_exists($source)) {
            continue;
        }

        replacePath($source, $basePath.DIRECTORY_SEPARATOR.$name);
        $logs[] = 'backend dir moved: '.$name;
    }

    foreach ($backendFiles as $name) {
        $source = $incomingRoot.DIRECTORY_SEPARATOR.$name;
        if (! file_exists($source)) {
            continue;
        }

        replacePath($source, $basePath.DIRECTORY_SEPARATOR.$name);
        $logs[] = 'backend file moved: '.$name;
    }

    foreach (array_diff(scandir($incomingPublicDir), ['.', '..']) as $name) {
        if (in_array($name, $publicSkip, true)) {
            continue;
        }

        replacePath($incomingPublicDir.DIRECTORY_SEPARATOR.$name, $publicRoot.DIRECTORY_SEPARATOR.$name);
        $logs[] = 'public moved: '.$name;
    }

    if (file_exists($incomingPublicDir.DIRECTORY_SEPARATOR.'.htaccess')) {
        replacePath($incomingPublicDir.DIRECTORY_SEPARATOR.'.htaccess', $publicRoot.DIRECTORY_SEPARATOR.'.htaccess');
        $logs[] = 'public moved: .htaccess';
    }

    return $logs;
}

function runUpdateTasks(string $basePath): string
{
    $logs = [];
    $logs[] = artisan($basePath, 'down');
    $logs[] = artisan($basePath, 'migrate --force');
    $logs[] = artisan($basePath, 'storage:link');
    $logs[] = artisan($basePath, 'optimize:clear');
    $logs[] = artisan($basePath, 'up');

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
                    $logs[] = artisan($basePath, 'key:generate --force');
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
                <p>Urutan update: pindah backend dari <code>public_html</code> ke folder <code>laravel</code>, pindah isi folder <code>public</code> ke <code>public_html</code>, lalu jalankan migrate dan clear cache.</p>
                <button type="submit">Jalankan Update</button>
            </form>
        <?php } ?>

        <?php if (! ($result['ok'] ?? false) && $mode !== 'update') { ?>
            <form method="post">
                <input type="hidden" name="mode" value="install">
                <p>Urutan install: pindah backend dari <code>public_html</code> ke folder <code>laravel</code>, pindah isi folder <code>public</code> ke <code>public_html</code>, buat <code>.env</code>, lalu generate key, migrate, dan clear cache.</p>
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