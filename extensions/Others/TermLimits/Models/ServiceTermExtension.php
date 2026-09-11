<?php

namespace Paymenter\Extensions\Others\TermLimits\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One grant of extra time, and the reason for it. */
class ServiceTermExtension extends Model
{
    protected $table = 'ext_term_limit_extensions';

    protected $guarded = [];

    protected $casts = [
        'hours' => 'integer',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(ServiceTerm::class, 'term_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
