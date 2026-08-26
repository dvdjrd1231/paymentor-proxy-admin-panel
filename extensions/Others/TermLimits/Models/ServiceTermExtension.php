<?php

namespace Paymenter\Extensions\Others\TermLimits\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One grant of extra time, and the reason for it.
 *
 * The brief asks for extensions "based on specific, justifiable needs regarding maintenance
 * or downtime", so the reason is a required column rather than an optional note: an
 * extension nobody can account for later is the thing this record exists to prevent. The
 * rows are append-only — a granted hour is a fact about the past, and taking it back is a
 * new negative grant with its own reason, not an edit.
 */
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
