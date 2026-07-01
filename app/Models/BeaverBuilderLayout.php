<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'type', 'content', 'meta', 'screenshot'])]
class BeaverBuilderLayout extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
