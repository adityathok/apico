<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;
use ZipArchive;

#[Signature('build:package {--output=dist/api-vd-co.{version}.zip} {--force}')]
#[Description('Build package update dari aplikasi saat ini')]
class BuildPackage extends Command
{
    public function handle(): int
    {
        if (! File::exists(base_path('vendor/autoload.php'))) {
            $this->error('Folder vendor tidak ditemukan. Jalankan composer install dulu.');

            return self::FAILURE;
        }

        $outputPath = $this->resolveOutputPath((string) $this->option('output'));
        $distDir = dirname($outputPath);
        $tempDir = $distDir.DIRECTORY_SEPARATOR.'temp-package';

        if (basename($distDir) !== 'dist') {
            $this->error('Output harus di folder dist.');

            return self::FAILURE;
        }

        if (File::exists($distDir)) {
            File::deleteDirectory($distDir);
        }

        File::makeDirectory($tempDir, 0755, true);

        $this->info('Build frontend assets...');
        $this->executeCommand('npm run build');

        $this->info('Copy application files...');
        $this->copyApplicationFiles($tempDir);

        $this->info('Create zip package...');
        $this->createZipPackage($tempDir, $outputPath);

        File::deleteDirectory($tempDir);

        $this->info('Package created: '.$outputPath);

        return self::SUCCESS;
    }

    private function resolveOutputPath(string $outputPath): string
    {
        $version = 'dev';
        $composerPath = base_path('composer.json');

        if (File::exists($composerPath)) {
            $composer = json_decode((string) File::get($composerPath), true);
            if (is_array($composer) && is_string($composer['version'] ?? null) && $composer['version'] !== '') {
                $version = $composer['version'];
            }
        }

        $version = preg_replace('/[^0-9A-Za-z._-]+/', '-', ltrim(trim($version), 'vV')) ?: 'dev';

        return str_replace('{version}', $version, $outputPath);
    }

    private function copyApplicationFiles(string $tempDir): void
    {
        $excludes = [
            '.git',
            'node_modules',
            'tests',
            'storage/logs',
            'storage/app/updates',
            'storage/app/backups',
            'dist',
            '.env',
        ];

        $this->copyDirectory(base_path(), $tempDir, $excludes);

        foreach (
            [
                'storage/app/private',
                'storage/app/public',
                'storage/framework/cache',
                'storage/framework/sessions',
                'storage/framework/testing',
                'storage/framework/views',
                'storage/logs',
                'bootstrap/cache',
            ] as $dir
        ) {
            File::ensureDirectoryExists($tempDir.'/'.$dir);
            File::put($tempDir.'/'.$dir.'/.gitkeep', '');
        }

        if (File::exists($tempDir.'/public/install')) {
            File::copyDirectory($tempDir.'/public/install', $tempDir.'/install');
        }
    }

    private function copyDirectory(string $source, string $dest, array $excludes): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            $relativePath = str_replace($source.DIRECTORY_SEPARATOR, '', $itemPath);
            $relativePath = str_replace('\\', '/', $relativePath);

            foreach ($excludes as $exclude) {
                if ($relativePath === $exclude || str_starts_with($relativePath, $exclude.'/')) {
                    continue 2;
                }
            }

            $target = $dest.DIRECTORY_SEPARATOR.$relativePath;
            if ($item->isDir()) {
                File::ensureDirectoryExists($target);

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($itemPath, $target);
        }
    }

    private function createZipPackage(string $tempDir, string $outputPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat zip package.');
        }

        $tempDir = realpath($tempDir);
        if (! $tempDir) {
            throw new \RuntimeException('Temp package tidak valid.');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            if (! $filePath) {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', substr($filePath, strlen($tempDir) + 1)), '/');

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);

                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
    }

    private function executeCommand(string $command): void
    {
        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout(null);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Command failed: '.$command);
        }
    }
}
