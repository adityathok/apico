<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GithubService
{
    protected ?string $token;

    protected string $baseUrl;

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
        $project = Project::query()->find($projectId);

        if (! $project instanceof Project || blank($project->github_url)) {
            return null;
        }

        $repository = $this->parseRepositoryFromUrl($project->github_url);

        if ($repository === null) {
            return null;
        }

        $release = $this->getLatestRelease($repository['owner'], $repository['repo']);

        if ($release === null || blank($release['version_tag'])) {
            return null;
        }

        $project->forceFill([
            'version' => $release['version_tag'],
            'package_external_url' => $release['package_download_url'],
        ])->save();

        return $project->fresh();
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
}
