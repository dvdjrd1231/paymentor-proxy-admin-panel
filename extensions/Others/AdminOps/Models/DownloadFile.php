<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stored file of the reference's Downloads area. */
class DownloadFile extends Model
{
    protected $table = 'ext_downloads';

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DownloadCategory::class, 'category_id');
    }
}
