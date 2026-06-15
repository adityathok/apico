<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'user_id' => $this->user_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->image,
            'image_url' => $this->imageUrl(),
            'image_caption' => $this->image_caption,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'post_tag' => $this->whenLoaded(
                'tags',
                fn(): string => $this->tags->pluck('name')->implode(', '),
                '',
            ),
            'post_tag_id' => $this->whenLoaded(
                'tags',
                fn(): string => $this->tags->pluck('id')->implode(', '),
                '',
            ),
            'category' => $this->whenLoaded(
                'categories',
                fn(): string => $this->categories->pluck('name')->implode(', '),
                '',
            ),
            'published_at' => $this->published_at,
            'user' => $this->whenLoaded('user'),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }

    private function imageUrl(): ?string
    {
        return match (true) {
            $this->image === null => null,
            str_starts_with($this->image, 'http') => $this->image,
            default => rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($this->image, '/'),
        };
    }
}
