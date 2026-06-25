<?php

error_reporting(isset($_GET['debug']) ? E_ALL : 0);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');

$basePath = realpath(__DIR__.'/../..');
if (! $basePath || ! file_exists($basePath.'/artisan')) {
    $basePath = realpath(__DIR__.'/..');
}
$lockPath = $basePath ? $basePath.'/storage/installer.lock' : '';

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

if (! $basePath || ! file_exists($basePath.'/artisan')) {
    http_response_code(500);
    exit('Root Laravel tidak ditemukan.');
}

if (file_exists($lockPath)) {
    exit('Aplikasi sudah terinstall. Hapus storage/installer.lock jika ingin install ulang.');
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $envExample = $basePath.'/.env.example';
        $envPath = $basePath.'/.env';
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
            $result = ['ok' => false, 'message' => 'Gagal menulis .env. Pastikan root aplikasi writable.'];
        } else {
            $logs = [];
            $logs[] = artisan($basePath, 'key:generate --force');
            $logs[] = artisan($basePath, 'migrate --force');
            $logs[] = artisan($basePath, 'storage:link');
            $logs[] = artisan($basePath, 'optimize:clear');

            @file_put_contents($lockPath, date('Y-m-d H:i:s'));

            $result = ['ok' => true, 'message' => 'Install selesai.', 'logs' => implode("\n", $logs)];
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
    <title>Install API VD CO</title>
    <style>
        body { background:#0f172a; color:#e5e7eb; font-family:Inter,ui-sans-serif,system-ui,sans-serif; margin:0; }
        main { max-width:760px; margin:40px auto; padding:24px; }
        form, .box { background:#111827; border:1px solid #334155; border-radius:16px; padding:24px; }
        label { display:block; margin-top:14px; font-size:14px; color:#cbd5e1; }
        input, select { width:100%; box-sizing:border-box; margin-top:6px; padding:10px 12px; border-radius:10px; border:1px solid #475569; background:#020617; color:#fff; }
        button { margin-top:20px; border:0; border-radius:10px; padding:12px 16px; background:#2563eb; color:#fff; font-weight:600; cursor:pointer; }
        pre { white-space:pre-wrap; background:#020617; padding:12px; border-radius:10px; overflow:auto; }
        .ok { border-color:#16a34a; color:#bbf7d0; }
        .err { border-color:#dc2626; color:#fecaca; }
    </style>
</head>
<body>
<main>
    <h1>Install API VD CO</h1>
    <p>Isi konfigurasi awal. Installer akan membuat <code>.env</code>, generate key, migrate database, dan membuat lock.</p>

    <?php if ($result) { ?>
        <div class="box <?= $result['ok'] ? 'ok' : 'err' ?>">
            <strong><?= htmlspecialchars($result['message']) ?></strong>
            <?php if (! empty($result['logs'])) { ?>
                <pre><?= htmlspecialchars($result['logs']) ?></pre>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if (! ($result['ok'] ?? false)) { ?>
        <form method="post">
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
    <?php } else { ?>
        <p>Hapus folder <code>public/install</code> setelah selesai jika tidak diperlukan.</p>
    <?php } ?>
</main>
</body>
</html>
