<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One administrator's dashboard: which widgets, in which order, and which are put away.
 *
 * @property array<int, string> $order widget keys, first to last
 * @property array<int, string> $hidden widget keys the admin has put away
 */
class DashboardLayout extends Model
{
    protected $table = 'ext_adminops_dashboard_layouts';

    protected $guarded = [];

    protected $casts = [
        'order' => 'array',
        'hidden' => 'array',
    ];

    /** The signed-in admin's layout, or an unsaved empty one. */
    public static function forUser(int $userId): self
    {
        return static::firstWhere('user_id', $userId)
            ?? new static(['user_id' => $userId, 'order' => [], 'hidden' => []]);
    }
}
