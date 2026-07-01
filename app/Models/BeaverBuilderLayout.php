<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'type', 'theme_layout_type', 'content', 'meta', 'screenshot'])]
class BeaverBuilderLayout extends Model
{
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BeaverBuilderTemplateCategory::class, 'beaver_builder_layout_category');
    }

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
