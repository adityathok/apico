<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
            'requires' => $this->requires_wp,
            'requires_php' => $this->requires_php,
            'plugin_wp_required' => $this->plugin_wp_required === null
                ? null
                : (bool) $this->plugin_wp_required,
            'github_url' => $this->github_url,
            'package_file' => $this->package_file,
            'package_file_url' => $this->packageFileUrl(),
            'package_external_url' => $this->package_external_url,
            'icon' => $this->icon,
            'icon_url' => $this->fileUrl($this->icon),
            'screenshot' => $this->screenshot,
            'screenshot_url' => $this->fileUrl($this->screenshot),
            'description' => $this->description,
            'type' => $this->type,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'parent' => $this->whenLoaded('parent', fn (): ?array => $this->parent?->only([
                'id',
                'name',
            ])),
        ];
    }

    private function packageFileUrl(): ?string
    {
        return $this->fileUrl($this->package_file);
    }

    private function fileUrl(?string $path): ?string
    {
        return match (true) {
            $path === null => null,
            str_starts_with($path, 'http') => $path,
            default => Storage::disk('public')->url($path),
        };
    }
}
