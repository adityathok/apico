<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GithubService
{
    protected ?string $token;

    protected string $baseUrl;

    protected ?string $lastSyncError = null;

    public function __construct()
    {
        $this->token = config('services.github.token');
        $this->baseUrl = rtrim((string) config('services.github.url'), '/');
    }

    /**
     * @return array{
     *     repository: string,
     *     version_tag: string|null,
     *     release_name: string|null,
     *     published_at: string|null,
     *     package_download_url: string|null,
     *     files: array<int, array{
     *         file_name: string|null,
     *         file_size: int|null,
     *         download_url: string|null
     *     }>
     * }|null
     */
    public function getLatestRelease(string $owner, string $repo): ?array
    {
        $response = $this->githubRequest("repos/{$owner}/{$repo}/releases/latest");

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $assets = collect(Arr::get($data, 'assets', []))
            ->map(function (array $asset): array {
                return [
                    'file_name' => $asset['name'] ?? null,
                    'file_size' => $asset['size'] ?? null,
                    'download_url' => $asset['browser_download_url'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'repository' => "{$owner}/{$repo}",
            'version_tag' => $data['tag_name'] ?? null,
            'release_name' => $data['name'] ?? 'N/A',
            'published_at' => $data['published_at'] ?? 'N/A',
            'package_download_url' => $assets[0]['download_url'] ?? ($data['zipball_url'] ?? null),
            'files' => $assets,
        ];
    }

    public function syncGithubProjectRelease(int $projectId): ?Project
    {
        $this->lastSyncError = null;
        $project = Project::query()->find($projectId);

        if (! $project instanceof Project) {
            $this->lastSyncError = 'Project not found.';

            return null;
        }

        if (blank($project->github_url)) {
            $this->lastSyncError = 'Project has no GitHub URL.';

            return null;
        }

        $repository = $this->parseRepositoryFromUrl($project->github_url);

        if ($repository === null) {
            $this->lastSyncError = 'GitHub URL format is invalid.';

            return null;
        }

        $release = $this->getLatestRelease($repository['owner'], $repository['repo']);

        if ($release === null) {
            $this->lastSyncError = 'Latest release not found or GitHub API failed.';

            return null;
        }

        if (blank($release['version_tag'])) {
            $this->lastSyncError = 'Latest release has no version tag.';

            return null;
        }

        $normalizedVersion = $this->normalizeVersionTag($release['version_tag']);
        $isPrivateRepository = $this->isRepositoryPrivate($repository['owner'], $repository['repo']);

        if ($isPrivateRepository) {
            $packageFile = $this->downloadReleasePackage($project, $release);

            if ($packageFile === null) {
                $this->lastSyncError = 'Release package download failed.';

                return null;
            }

            $project->forceFill([
                'version' => $normalizedVersion,
                'package_external_url' => null,
                'package_file' => $packageFile,
            ])->save();

            return $project->fresh();
        }

        $project->forceFill([
            'version' => $normalizedVersion,
            'package_external_url' => $release['package_download_url'],
        ])->save();

        return $project->fresh();
    }

    public function isRepositoryPrivate(string $owner, string $repo): bool
    {
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
        ])->get("{$this->baseUrl}/repos/{$owner}/{$repo}");

        return $response->status() === 404;
    }

    public function lastSyncError(): ?string
    {
        return $this->lastSyncError;
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    private function parseRepositoryFromUrl(string $githubUrl): ?array
    {
        $path = trim((string) parse_url($githubUrl, PHP_URL_PATH), '/');

        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) < 2) {
            return null;
        }

        return [
            'owner' => $segments[0],
            'repo' => preg_replace('/\.git$/', '', $segments[1]) ?: $segments[1],
        ];
    }

    private function githubRequest(string $path): Response
    {
        return Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
        ])
            ->when(
                filled($this->token),
                fn ($request) => $request->withToken($this->token),
            )
            ->get("{$this->baseUrl}/{$path}");
    }

    /**
     * @param  array{
     *     version_tag: string|null,
     *     package_download_url: string|null,
     *     files: array<int, array{
     *         file_name: string|null,
     *         file_size: int|null,
     *         download_url: string|null
     *     }>
     * }  $release
     */
    private function downloadReleasePackage(Project $project, array $release): ?string
    {
        $downloadUrl = $release['package_download_url'];

        if (blank($downloadUrl)) {
            return null;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/octet-stream',
        ])
            ->when(
                filled($this->token),
                fn ($request) => $request->withToken($this->token),
            )
            ->get($downloadUrl);

        if ($response->failed()) {
            return null;
        }

        $this->deleteStoredPackageFile($project);

        $fileName = $release['files'][0]['file_name']
            ?? Str::slug($project->name).'-'.Str::slug((string) $release['version_tag']).'.zip';
        $folder = 'project-packages/'.Str::slug($project->name);
        $path = $folder.'/'.$fileName;

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function deleteStoredPackageFile(Project $project): void
    {
        if (blank($project->package_file) || str_starts_with($project->package_file, 'http')) {
            return;
        }

        Storage::disk('public')->delete($project->package_file);
    }

    private function normalizeVersionTag(string $versionTag): string
    {
        return preg_replace('/^[vV](?=\d)/', '', trim($versionTag)) ?: trim($versionTag);
    }
}
