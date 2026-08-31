<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The link between an addon's own service row and the service it extends. */
class ServiceAddon extends Model
{
    protected $table = 'ext_service_addons';

    protected $guarded = [];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_service_id');
    }
}
