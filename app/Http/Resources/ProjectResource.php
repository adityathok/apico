<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'package_file_url' => $this->package_file_url,
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
}
