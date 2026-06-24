<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectChangelogResource extends JsonResource
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
            'project_id' => $this->project_id,
            'project_version' => $this->project_version,
            'changelog_content' => $this->changelog_content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project?->only([
                'id',
                'name',
                'slug',
            ])),
        ];
    }
}
