<?php

namespace Paymenter\Extensions\Others\AdminOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One administrator's dashboard: which widgets, in which order, and which are put away.
 *
 * The reference keeps the same two things per admin and the same third thing — whether a
 * panel is rolled up — in the browser, and this follows it exactly. Collapsing is a "how am
 * I reading this screen right now" decision, not a preference: it should not follow you to
 * another machine, and it should not cost a request.
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

    /**
     * The signed-in admin's layout, or an unsaved empty one.
     *
     * Deliberately not `firstOrCreate`: reading the dashboard should not write to the
     * database, or every admin who ever glanced at it leaves a row of empty arrays behind.
     * The row is created by the first drag.
     */
    public static function forUser(int $userId): self
    {
        return static::firstWhere('user_id', $userId)
            ?? new static(['user_id' => $userId, 'order' => [], 'hidden' => []]);
    }
}
