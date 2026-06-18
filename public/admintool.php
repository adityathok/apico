<?php

declare(strict_types=1);

session_start();

function admintoolResolveBasePath(string $currentDirectory): string
{
    $defaultBasePath = dirname($currentDirectory);
    $siblingLaravelPath = $defaultBasePath.DIRECTORY_SEPARATOR.'laravel';

    if (basename($currentDirectory) === 'public_html' && is_dir($siblingLaravelPath)) {
        return $siblingLaravelPath;
    }

    return $defaultBasePath;
}

$basePath = admintoolResolveBasePath(__DIR__);
$artisanPath = $basePath.DIRECTORY_SEPARATOR.'artisan';
$envPath = $basePath.DIRECTORY_SEPARATOR.'.env';

if (empty($_SESSION['admintool_csrf'])) {
    $_SESSION['admintool_csrf'] = bin2hex(random_bytes(32));
}

/**
 * @return array<string, string>
 */
function admintoolReadEnv(string $path): array
{
    if (! is_file($path) || ! is_readable($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}

/**
 * @param  list<string>  $arguments
 * @return array{exit_code: int, output: string}
 */
function admintoolFunctionAvailable(string $functionName): bool
{
    if (! function_exists($functionName)) {
        return false;
    }

    $disabledFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return ! in_array($functionName, $disabledFunctions, true);
}

/**
 * @param  list<string>  $command
 */
function admintoolBuildShellCommand(array $command): string
{
    return implode(' ', array_map('escapeshellarg', $command));
}

/**
 * @param  list<string>  $arguments
 * @return array{exit_code: int, output: string}
 */
function admintoolRunArtisan(string $phpBinary, string $artisanPath, array $arguments): array
{
    $command = array_merge([$phpBinary, $artisanPath], $arguments);
    $workingDirectory = dirname($artisanPath);

    if (admintoolFunctionAvailable('proc_open')) {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);

        if (is_resource($process)) {
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $errorOutput = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            return [
                'exit_code' => $exitCode,
                'output' => trim((string) $output.PHP_EOL.(string) $errorOutput),
            ];
        }
    }

    $shellCommand = admintoolBuildShellCommand($command);

    if (admintoolFunctionAvailable('exec')) {
        $currentDirectory = getcwd();
        $outputLines = [];
        $exitCode = 1;

        chdir($workingDirectory);
        exec($shellCommand.' 2>&1', $outputLines, $exitCode);

        if ($currentDirectory !== false) {
            chdir($currentDirectory);
        }

        return [
            'exit_code' => $exitCode,
            'output' => trim(implode(PHP_EOL, $outputLines)),
        ];
    }

    if (admintoolFunctionAvailable('shell_exec')) {
        $currentDirectory = getcwd();

        chdir($workingDirectory);
        $output = shell_exec($shellCommand.' 2>&1');

        if ($currentDirectory !== false) {
            chdir($currentDirectory);
        }

        return [
            'exit_code' => $output === null ? 1 : 0,
            'output' => trim((string) $output),
        ];
    }

    return [
        'exit_code' => 1,
        'output' => 'Semua fungsi eksekusi command dinonaktifkan di server ini (proc_open, exec, shell_exec). Hubungi hosting atau aktifkan salah satu fungsi tersebut.',
    ];
}

/**
 * @return array{exit_code: int, output: string}
 */
function admintoolCopyPublicToPublicHtml(string $sourcePath, string $targetPath): array
{
    if (! is_dir($sourcePath)) {
        return [
            'exit_code' => 1,
            'output' => 'Folder public tidak ditemukan.',
        ];
    }

    if (! is_dir($targetPath) && ! mkdir($targetPath, 0755, true)) {
        return [
            'exit_code' => 1,
            'output' => 'Gagal membuat folder public_html.',
        ];
    }

    $copied = 0;
    $failed = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($sourcePath) + 1);
        $destinationPath = $targetPath.DIRECTORY_SEPARATOR.$relativePath;

        if ($item->isDir()) {
            if (! is_dir($destinationPath) && ! mkdir($destinationPath, 0755, true)) {
                $failed[] = $relativePath;
            }

            continue;
        }

        $destinationDirectory = dirname($destinationPath);

        if (! is_dir($destinationDirectory) && ! mkdir($destinationDirectory, 0755, true)) {
            $failed[] = $relativePath;

            continue;
        }

        if (copy($item->getPathname(), $destinationPath)) {
            $copied++;

            continue;
        }

        $failed[] = $relativePath;
    }

    $output = [
        'Source: '.$sourcePath,
        'Target: '.$targetPath,
        'File copied: '.$copied,
    ];

    if ($failed !== []) {
        $output[] = 'Failed:';
        $output = array_merge($output, $failed);
    }

    return [
        'exit_code' => $failed === [] ? 0 : 1,
        'output' => implode(PHP_EOL, $output),
    ];
}

/**
 * @return array{exit_code: int, output: string}
 */
function admintoolCreatePublicHtmlStorageSymlink(string $basePath): array
{
    $publicHtmlPath = dirname($basePath).DIRECTORY_SEPARATOR.'public_html';
    $linkPath = $publicHtmlPath.DIRECTORY_SEPARATOR.'storage';
    $targetPath = $basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public';

    if (! is_dir($publicHtmlPath)) {
        return [
            'exit_code' => 1,
            'output' => 'Folder public_html tidak ditemukan.',
        ];
    }

    if (! is_dir($targetPath)) {
        return [
            'exit_code' => 1,
            'output' => 'Folder target storage Laravel tidak ditemukan.',
        ];
    }

    if (is_link($linkPath)) {
        $existingTarget = readlink($linkPath);

        if ($existingTarget === $targetPath) {
            return [
                'exit_code' => 0,
                'output' => 'Symlink storage sudah ada dan mengarah ke target yang benar.',
            ];
        }

        return [
            'exit_code' => 1,
            'output' => 'Symlink storage sudah ada tetapi mengarah ke: '.(string) $existingTarget,
        ];
    }

    if (file_exists($linkPath)) {
        return [
            'exit_code' => 1,
            'output' => 'Path public_html/storage sudah ada sebagai file atau folder biasa.',
        ];
    }

    if (@symlink($targetPath, $linkPath)) {
        return [
            'exit_code' => 0,
            'output' => 'Symlink berhasil dibuat: '.$linkPath.' -> '.$targetPath,
        ];
    }

    return [
        'exit_code' => 1,
        'output' => 'Gagal membuat symlink. Pastikan fungsi symlink aktif dan permission folder public_html mencukupi.',
    ];
}

function admintoolEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admintoolRenderPhpInfo(): string
{
    ob_start();
    phpinfo();
    $output = (string) ob_get_clean();

    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $output, $matches) === 1) {
        return trim($matches[1]);
    }

    return $output;
}

$env = admintoolReadEnv($envPath);
$password = $env['ADMINTOOL_PASSWORD'] ?? $env['ADMINS_TOOL_PASSWORD'] ?? $env['APP_KEY'] ?? '123456789';
$sessionKey = hash('sha256', $basePath.'|admintool');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    unset($_SESSION[$sessionKey]);
    header('Location: '.strtok((string) $_SERVER['REQUEST_URI'], '?'));
    exit;
}

$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($password !== '' && hash_equals($password, (string) $_POST['password'])) {
        $_SESSION[$sessionKey] = true;
        header('Location: '.strtok((string) $_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $loginError = 'Password tidak cocok. Isi ADMINTOOL_PASSWORD di .env, atau gunakan APP_KEY jika belum diset.';
}

$isAuthenticated = (bool) ($_SESSION[$sessionKey] ?? false);

$commands = [
    'migrate' => [
        'label' => 'Migrate',
        'description' => 'Menjalankan migration yang belum dieksekusi.',
        'arguments' => ['migrate', '--force', '--no-interaction'],
        'danger' => false,
    ],
    'migrate_status' => [
        'label' => 'Migrate Status',
        'description' => 'Melihat status migration.',
        'arguments' => ['migrate:status', '--no-interaction'],
        'danger' => false,
    ],
    'config_clear' => [
        'label' => 'Clear Config',
        'description' => 'Menghapus cache konfigurasi.',
        'arguments' => ['config:clear', '--no-interaction'],
        'danger' => false,
    ],
    'cache_clear' => [
        'label' => 'Clear Cache',
        'description' => 'Menghapus application cache.',
        'arguments' => ['cache:clear', '--no-interaction'],
        'danger' => false,
    ],
    'route_clear' => [
        'label' => 'Clear Route',
        'description' => 'Menghapus route cache.',
        'arguments' => ['route:clear', '--no-interaction'],
        'danger' => false,
    ],
    'view_clear' => [
        'label' => 'Clear View',
        'description' => 'Menghapus compiled Blade views.',
        'arguments' => ['view:clear', '--no-interaction'],
        'danger' => false,
    ],
    'optimize_clear' => [
        'label' => 'Optimize Clear',
        'description' => 'Menghapus seluruh cache framework umum.',
        'arguments' => ['optimize:clear', '--no-interaction'],
        'danger' => false,
    ],
    'config_cache' => [
        'label' => 'Cache Config',
        'description' => 'Membuat ulang cache konfigurasi.',
        'arguments' => ['config:cache', '--no-interaction'],
        'danger' => false,
    ],
    'storage_link' => [
        'label' => 'Storage Link',
        'description' => 'Membuat symbolic link storage publik.',
        'arguments' => ['storage:link', '--no-interaction'],
        'danger' => false,
    ],
    'queue_restart' => [
        'label' => 'Queue Restart',
        'description' => 'Meminta queue worker restart setelah job selesai.',
        'arguments' => ['queue:restart', '--no-interaction'],
        'danger' => false,
    ],
    'seed_user' => [
        'label' => 'Seed User',
        'description' => 'Menjalankan seeder UserSeeder untuk membuat user default.',
        'arguments' => ['db:seed', '--class=UserSeeder', '--no-interaction'],
        'danger' => false,
    ],
    'copy_public_html' => [
        'label' => 'Copy Public',
        'description' => 'Copy isi folder public ke public_html sejajar folder Laravel.',
        'handler' => 'copy_public_html',
        'danger' => false,
    ],
    'public_html_storage_link' => [
        'label' => 'Public HTML Storage Link',
        'description' => 'Membuat symlink public_html/storage ke folder laravel/storage/app/public.',
        'handler' => 'public_html_storage_link',
        'danger' => false,
    ],
    'php_info' => [
        'label' => 'PHP Info',
        'description' => 'Menampilkan informasi konfigurasi PHP server saat ini.',
        'handler' => 'php_info',
        'danger' => false,
    ],
];

$result = null;
$selectedCommand = null;

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $selectedCommand = (string) $_POST['command'];
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (! hash_equals((string) $_SESSION['admintool_csrf'], $submittedToken)) {
        $result = [
            'exit_code' => 1,
            'output' => 'Token form tidak valid. Refresh halaman lalu coba lagi.',
        ];
    } elseif (! isset($commands[$selectedCommand])) {
        $result = [
            'exit_code' => 1,
            'output' => 'Command tidak tersedia.',
        ];
    } elseif (($commands[$selectedCommand]['handler'] ?? null) === 'copy_public_html') {
        $result = admintoolCopyPublicToPublicHtml(
            $basePath.DIRECTORY_SEPARATOR.'public',
            dirname($basePath).DIRECTORY_SEPARATOR.'public_html'
        );
    } elseif (($commands[$selectedCommand]['handler'] ?? null) === 'public_html_storage_link') {
        $result = admintoolCreatePublicHtmlStorageSymlink($basePath);
    } elseif (($commands[$selectedCommand]['handler'] ?? null) === 'php_info') {
        $result = [
            'exit_code' => 0,
            'output' => admintoolRenderPhpInfo(),
            'is_html' => true,
        ];
    } elseif (! is_file($artisanPath)) {
        $result = [
            'exit_code' => 1,
            'output' => 'File artisan tidak ditemukan.',
        ];
    } else {
        $result = admintoolRunArtisan(PHP_BINARY, $artisanPath, $commands[$selectedCommand]['arguments']);
    }
}

?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel Admin Tool</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f6f7f9;
            --panel: #ffffff;
            --text: #16181d;
            --muted: #646b78;
            --border: #d9dde5;
            --primary: #166534;
            --primary-hover: #14532d;
            --danger: #b42318;
            --code: #111827;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111318;
                --panel: #191c22;
                --text: #f4f6f8;
                --muted: #a6adbb;
                --border: #333845;
                --primary: #22c55e;
                --primary-hover: #16a34a;
                --danger: #f97066;
                --code: #080a0f;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font: 15px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(1040px, calc(100% - 32px));
            margin: 0 auto;
            padding: 40px 0;
        }

        header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: clamp(28px, 5vw, 42px);
            line-height: 1.1;
        }

        p {
            margin: 0;
            color: var(--muted);
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 22px;
            box-shadow: 0 14px 40px rgb(15 23 42 / 8%);
        }

        .login {
            max-width: 460px;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-top: 18px;
        }

        label {
            font-weight: 700;
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            background: transparent;
            color: var(--text);
            font: inherit;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .command {
            display: grid;
            gap: 12px;
            align-content: space-between;
            min-height: 148px;
            text-align: left;
        }

        .command strong {
            display: block;
            font-size: 17px;
            margin-bottom: 4px;
        }

        button {
            border: 0;
            border-radius: 8px;
            padding: 11px 14px;
            background: var(--primary);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .secondary {
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
        }

        .secondary:hover {
            background: rgb(100 107 120 / 12%);
        }

        .message {
            margin-bottom: 16px;
            border-radius: 8px;
            padding: 12px 14px;
            border: 1px solid var(--border);
        }

        .error {
            color: var(--danger);
        }

        .output {
            margin-top: 18px;
        }

        .phpinfo-content {
            margin-top: 12px;
            overflow: auto;
            max-height: 70vh;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--panel);
        }

        .phpinfo-content table {
            width: 100%;
            border-collapse: collapse;
        }

        .phpinfo-content th,
        .phpinfo-content td {
            padding: 8px 10px;
            border: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }

        .phpinfo-content h1,
        .phpinfo-content h2 {
            margin-top: 0;
        }

        pre {
            overflow: auto;
            max-height: 520px;
            margin: 12px 0 0;
            padding: 16px;
            border-radius: 8px;
            background: var(--code);
            color: #e5e7eb;
            white-space: pre-wrap;
        }

        code {
            font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace;
            font-size: 13px;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>
    <main>
        <header>
            <div>
                <h1>Laravel Admin Tool</h1>
                <p>Jalankan command artisan yang umum dipakai untuk maintenance.</p>
            </div>

            <?php if ($isAuthenticated) { ?>
                <form method="post">
                    <button class="secondary" type="submit" name="logout" value="1">Logout</button>
                </form>
            <?php } ?>
        </header>

        <?php if (! $isAuthenticated) { ?>
            <section class="panel login">
                <?php if ($password === '') { ?>
                    <div class="message error">Set <code>ADMINTOOL_PASSWORD</code> di file <code>.env</code> terlebih dahulu.</div>
                <?php } ?>

                <?php if ($loginError !== null) { ?>
                    <div class="message error"><?= admintoolEscape($loginError) ?></div>
                <?php } ?>

                <form method="post">
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
                    </div>

                    <p style="margin-top: 10px;">Gunakan <code>ADMINTOOL_PASSWORD</code> dari <code>.env</code>. Jika belum ada, fallback sementara adalah <code>APP_KEY</code>.</p>

                    <div class="top-actions" style="margin-top: 18px;">
                        <button type="submit">Masuk</button>
                    </div>
                </form>
            </section>
        <?php } else { ?>
            <?php if ($result !== null) { ?>
                <section class="panel output">
                    <strong>
                        <?= admintoolEscape($commands[$selectedCommand]['label'] ?? 'Command') ?>
                        selesai dengan exit code <?= (int) $result['exit_code'] ?>
                    </strong>
                    <?php if (($result['is_html'] ?? false) === true) { ?>
                        <div class="phpinfo-content"><?= $result['output'] !== '' ? $result['output'] : '<p>Tidak ada output.</p>' ?></div>
                    <?php } else { ?>
                        <pre><code><?= admintoolEscape($result['output'] !== '' ? $result['output'] : 'Tidak ada output.') ?></code></pre>
                    <?php } ?>
                </section>
            <?php } ?>

            <section class="grid" style="margin-top: 18px;">
                <?php foreach ($commands as $key => $command) { ?>
                    <form class="panel command" method="post">
                        <input type="hidden" name="csrf_token" value="<?= admintoolEscape((string) $_SESSION['admintool_csrf']) ?>">
                        <div>
                            <strong><?= admintoolEscape($command['label']) ?></strong>
                            <p><?= admintoolEscape($command['description']) ?></p>
                        </div>

                        <button type="submit" name="command" value="<?= admintoolEscape($key) ?>">
                            Jalankan
                        </button>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>
    </main>
</body>

</html>
