<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticle extends Model
{
    protected $table = 'ext_kb_articles';

    protected $fillable = [
        'category_id', 'title', 'slug', 'description', 'content',
        'is_active', 'published_at', 'views',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    /**
     * Everything a customer is allowed to see. A draft (no `published_at`) or a future
     * date stays hidden, so an article can be written ahead of time.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
