<?php

namespace Paymenter\Extensions\Others\ClientTools\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A file the operator publishes on the Downloads page — setup guides, proxy
 * configuration files, tooling.
 */
class Download extends Model
{
    protected $table = 'ext_ct_downloads';

    protected $fillable = [
        'title', 'description', 'category', 'url',
        'requires_login', 'is_active', 'download_count', 'sort',
    ];

    protected $casts = [
        'requires_login' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * What the given viewer may see. A guest is shown only the public entries, so the
     * page works signed out without leaking the customer-only files.
     */
    public function scopeVisibleTo(Builder $query, bool $isAuthenticated): Builder
    {
        return $query->where('is_active', true)
            ->when(!$isAuthenticated, fn ($q) => $q->where('requires_login', false));
    }
}
