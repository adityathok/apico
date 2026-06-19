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
            'version' => $this->version,
            'github_url' => $this->github_url,
            'package_file' => $this->package_file,
            'package_file_url' => $this->packageFileUrl(),
            'package_external_url' => $this->package_external_url,
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
        return match (true) {
            $this->package_file === null => null,
            str_starts_with($this->package_file, 'http') => $this->package_file,
            default => Storage::disk('public')->url($this->package_file),
        };
    }
}
