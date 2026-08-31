<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry of the reference's Network Issues board. */
class NetworkIssue extends Model
{
    protected $table = 'ext_network_issues';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public const TYPES = ['server' => 'Server', 'system' => 'System', 'other' => 'Other'];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'investigating' => 'Investigating',
        'in_progress' => 'In Progress',
        'outage' => 'Outage',
        'resolved' => 'Resolved',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** The reference's list split: Open is everything not yet resolved or merely scheduled. */
    public function scopeView($query, string $view)
    {
        return match ($view) {
            'scheduled' => $query->where('status', 'scheduled'),
            'resolved' => $query->where('status', 'resolved'),
            'open' => $query->whereNotIn('status', ['scheduled', 'resolved']),
            default => $query,
        };
    }
}
