<?php

namespace App\Models;

use Database\Factories\WebsitePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title', 'content', 'meta_title', 'meta_description', 'is_published'])]
class WebsitePage extends Model
{
    /** @use HasFactory<WebsitePageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['content' => 'array', 'is_published' => 'boolean'];
    }
}
