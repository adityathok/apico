<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BeaverBuilderLayoutResource extends JsonResource
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
            'title' => $this->title,
            'type' => $this->type,
            'content' => $this->content,
            'meta' => $this->meta,
            'screenshot' => $this->screenshot
                ? (Str::startsWith($this->screenshot, ['http://', 'https://'])
                    ? $this->screenshot
                    : Storage::disk('public')->url($this->screenshot))
                : null,
            'theme_layout_type' => $this->theme_layout_type,
            'categories' => BeaverBuilderTemplateCategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
