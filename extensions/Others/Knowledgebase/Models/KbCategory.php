<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    protected $table = 'ext_kb_categories';

    protected $fillable = ['name', 'slug', 'description', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    /** Only what a customer may see: active articles that are published. */
    public function publishedArticles(): HasMany
    {
        return $this->articles()->where('is_active', true)->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
