<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class UpdateController extends Controller
{
    private const GITHUB_REPO = 'Velocity-Developer/api-vd-co';

    private const UPDATE_TEMP_DIR = 'storage/app/updates';

    private const BACKUP_DIR = 'storage/app/backups';

    public function page()
    {
        return inertia('SystemUpdate');
    }

    public function checkUpdates(): JsonResponse
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'api-vd-co-updater',
            ])->timeout(15)->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases/latest');

            if (! $response->successful()) {
                return response()->json(['error' => 'Tidak dapat mengecek update'], 500);
            }

            $latestRelease = $response->json();
            $currentVersion = $this->getCurrentVersion();
            $latestVersion = $this->normalizeVersion((string) ($latestRelease['tag_name'] ?? '0.0.0'));

            return response()->json([
                'has_update' => version_compare($latestVersion, $currentVersion, '>'),
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'release_notes' => $latestRelease['body'] ?? '',
                'download_url' => $this->getDownloadUrl($latestRelease),
                'published_at' => $latestRelease['published_at'] ?? null,
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to check updates: '.$exception->getMessage());

            return response()->json(['error' => 'Gagal mengecek update: '.$exception->getMessage()], 500);
        }
    }

    public function performUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'download_url' => ['required', 'url'],
            'version' => ['required', 'string'],
        ]);

        try {
            set_time_limit(300);

            $version = $this->normalizeVersion($validated['version']);
            $updateFile = $this->downloadUpdate($validated['download_url'], $version);
            $extractDir = $this->extractUpdate($updateFile, $version);

            try {
                $this->installUpdateFromExtracted($extractDir, $version);
            } finally {
                if (File::exists($extractDir)) {
                    File::deleteDirectory($extractDir);
                }
            }

            $this->runPostUpdateTasks();
            $this->cleanupUpdate($updateFile);

            return response()->json([
                'success' => true,
                'message' => 'Update berhasil diinstall ke versi '.$version,
                'version' => $version,
            ]);
        } catch (Exception $exception) {
            Log::error('Update failed: '.$exception->getMessage());
            $this->restoreLatestBackupFromSnapshot();

            return response()->json(['error' => 'Update gagal: '.$exception->getMessage()], 500);
        }
    }

    public function restoreBackup(): JsonResponse
    {
        try {
            $backupDir = $this->findLatestBackupDir();
            if (! $backupDir) {
                return response()->json(['error' => 'Backup tidak ditemukan'], 404);
            }

            $this->restoreFromSnapshot($backupDir);

            return response()->json([
                'success' => true,
                'message' => 'Backup berhasil direstore',
            ]);
        } catch (Exception $exception) {
            Log::error('Restore backup failed: '.$exception->getMessage());

            return response()->json(['error' => 'Restore backup gagal: '.$exception->getMessage()], 500);
        }
    }

    private function getCurrentVersion(): string
    {
        $composerPath = base_path('composer.json');

        if (! file_exists($composerPath)) {
            return '0.0.0';
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);

        return $this->normalizeVersion((string) ($composer['version'] ?? '0.0.0'));
    }

    private function getDownloadUrl(array $release): ?string
    {
        $assets = $release['assets'] ?? [];

        foreach ($assets as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if (preg_match('/^api-vd-co[.-].+\.zip$/i', $name)) {
                return $asset['browser_download_url'] ?? null;
            }
        }

        foreach ($assets as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if (str_ends_with(strtolower($name), '.zip')) {
                return $asset['browser_download_url'] ?? null;
            }
        }

        return $release['zipball_url'] ?? null;
    }

    private function downloadUpdate(string $url, string $version): string
    {
        $updateDir = base_path(self::UPDATE_TEMP_DIR);
        if (! File::exists($updateDir)) {
            File::makeDirectory($updateDir, 0755, true);
        }

        $updateFile = $updateDir.'/update-'.$version.'.zip';

        $response = Http::withHeaders([
            'User-Agent' => 'api-vd-co-updater',
        ])->timeout(180)->sink($updateFile)->get($url);

        if (! $response->successful() || ! file_exists($updateFile) || filesize($updateFile) < 1024) {
            if (file_exists($updateFile)) {
                @unlink($updateFile);
            }

            throw new Exception('Gagal download update');
        }

        return $updateFile;
    }

    private function extractUpdate(string $updateFile, string $version): string
    {
        $zip = new ZipArchive;
        $extractDir = base_path(self::UPDATE_TEMP_DIR.'/extracted-'.$version);

        if ($zip->open($updateFile) !== true) {
            throw new Exception('Gagal extract update file');
        }

        $zip->extractTo($extractDir);
        $zip->close();

        return $extractDir;
    }

    private function findPackageContentDirectory(string $extractDir): string
    {
        if (File::exists($extractDir.'/artisan')) {
            return $extractDir;
        }

        $dirs = File::directories($extractDir);
        if (count($dirs) === 1) {
            $candidate = $dirs[0];
            if (File::exists($candidate.'/artisan')) {
                return $candidate;
            }
        }

        throw new Exception('Struktur package tidak valid');
    }

    private function installUpdateFromExtracted(string $extractDir, string $version): void
    {
        $contentDir = $this->findPackageContentDirectory($extractDir);

        if (! File::exists($contentDir.'/artisan') || ! File::exists($contentDir.'/vendor/autoload.php')) {
            throw new Exception('Package update tidak valid');
        }

        $backupDir = $this->createSnapshotBackupDir($version);

        try {
            try {
                Artisan::call('down');
            } catch (Exception $exception) {
                Log::warning('Failed to enable maintenance mode: '.$exception->getMessage());
            }

            $this->backupAndReplaceApplication($contentDir, base_path(), $backupDir.'/app');

            try {
                Artisan::call('up');
            } catch (Exception $exception) {
                Log::warning('Failed to disable maintenance mode: '.$exception->getMessage());
            }
        } catch (Exception $exception) {
            $this->restoreFromSnapshot($backupDir);
            throw $exception;
        }
    }

    private function backupAndReplaceApplication(string $sourceRoot, string $targetRoot, string $backupRoot): void
    {
        if (! File::exists($backupRoot)) {
            File::makeDirectory($backupRoot, 0755, true);
        }

        $preserveNames = [
            '.env',
            '.env.production',
            '.env.backup',
            'storage',
            '.git',
            'node_modules',
        ];

        foreach (File::directories($targetRoot) as $dir) {
            $name = basename($dir);
            if (in_array($name, $preserveNames, true)) {
                continue;
            }

            File::move($dir, $backupRoot.'/'.$name);
        }

        foreach (File::files($targetRoot) as $file) {
            $name = $file->getFilename();
            if (in_array($name, $preserveNames, true)) {
                continue;
            }

            File::move($file->getPathname(), $backupRoot.'/'.$name);
        }

        $this->copyDirectoryContents($sourceRoot, $targetRoot, [
            '.env',
            'storage',
            '.git',
            'node_modules',
        ]);

        $this->clearBootstrapCache($targetRoot);
    }

    private function runPostUpdateTasks(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        $commands = [
            'config:clear',
            'route:clear',
            'view:clear',
            'migrate --force',
        ];

        foreach ($commands as $command) {
            try {
                Artisan::call($command);
            } catch (Exception $exception) {
                Log::warning('Post-update command failed: '.$command.' - '.$exception->getMessage());
            }
        }
    }

    private function cleanupUpdate(string $updateFile): void
    {
        if (file_exists($updateFile)) {
            unlink($updateFile);
        }

        $backupDir = base_path(self::BACKUP_DIR);
        if (! File::exists($backupDir)) {
            return;
        }

        $backups = collect(File::directories($backupDir))
            ->sortByDesc(static fn (string $dir): int => filemtime($dir) ?: 0)
            ->skip(3);

        foreach ($backups as $dir) {
            File::deleteDirectory($dir);
        }
    }

    private function createSnapshotBackupDir(string $version): string
    {
        $backupBase = base_path(self::BACKUP_DIR);
        if (! File::exists($backupBase)) {
            File::makeDirectory($backupBase, 0755, true);
        }

        $dir = $backupBase.'/backup-'.now()->format('Y-m-d-H-i-s').'-'.$version;
        File::makeDirectory($dir, 0755, true);

        return $dir;
    }

    private function findLatestBackupDir(): ?string
    {
        $backupBase = base_path(self::BACKUP_DIR);
        if (! File::exists($backupBase)) {
            return null;
        }

        $dirs = File::directories($backupBase);
        if ($dirs === []) {
            return null;
        }

        usort($dirs, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return $dirs[0] ?? null;
    }

    private function restoreLatestBackupFromSnapshot(): void
    {
        $backupDir = $this->findLatestBackupDir();
        if ($backupDir) {
            $this->restoreFromSnapshot($backupDir);
        }
    }

    private function restoreFromSnapshot(string $backupDir): void
    {
        $backupApp = $backupDir.'/app';
        if (! File::exists($backupApp)) {
            return;
        }

        $this->restoreSnapshotInto($backupApp, base_path(), [
            '.env',
            '.env.production',
            '.env.backup',
            'storage',
            '.git',
            'node_modules',
        ]);

        $this->clearBootstrapCache(base_path());
    }

    private function restoreSnapshotInto(string $backupRoot, string $targetRoot, array $preserveNames): void
    {
        foreach (File::directories($backupRoot) as $dir) {
            $name = basename($dir);
            if (in_array($name, $preserveNames, true)) {
                continue;
            }

            $targetPath = $targetRoot.'/'.$name;
            if (File::exists($targetPath)) {
                File::deleteDirectory($targetPath);
            }

            File::move($dir, $targetPath);
        }

        foreach (File::files($backupRoot) as $file) {
            $name = $file->getFilename();
            if (in_array($name, $preserveNames, true)) {
                continue;
            }

            $targetPath = $targetRoot.'/'.$name;
            if (File::exists($targetPath)) {
                File::delete($targetPath);
            }

            File::move($file->getPathname(), $targetPath);
        }
    }

    private function copyDirectoryContents(string $source, string $dest, array $excludeBasenames): void
    {
        if (! File::exists($dest)) {
            File::makeDirectory($dest, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $itemPath = (string) $item;
            $relative = ltrim(str_replace($this->normalizePath($source), '', $this->normalizePath($itemPath)), '/');
            if ($relative === '') {
                continue;
            }

            $top = explode('/', $relative)[0] ?? $relative;
            if (in_array($top, $excludeBasenames, true)) {
                continue;
            }

            $target = $this->normalizePath($dest).'/'.$relative;
            if ($item->isDir()) {
                if (! File::exists($target)) {
                    File::makeDirectory($target, 0755, true);
                }

                continue;
            }

            $targetDir = dirname($target);
            if (! File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            File::copy($itemPath, $target);
        }
    }

    private function clearBootstrapCache(string $appRoot): void
    {
        $cacheDir = $this->normalizePath($appRoot).'/bootstrap/cache';
        if (! File::exists($cacheDir)) {
            return;
        }

        foreach (File::glob($cacheDir.'/*.php') as $file) {
            File::delete($file);
        }
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        $version = ltrim($version, "vV \t\n\r\0\x0B");

        return $version === '' ? '0.0.0' : $version;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
