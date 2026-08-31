<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A folder of the reference's Downloads area. */
class DownloadCategory extends Model
{
    protected $table = 'ext_download_categories';

    protected $guarded = [];

    public function files(): HasMany
    {
        return $this->hasMany(DownloadFile::class, 'category_id');
    }
}
