<?php

namespace Paymenter\Extensions\Others\TermLimits\Models;

use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The clock on one fixed-term service.
 *
 * `ends_at` is the whole truth: it starts as `started_at` + the contracted hours and moves
 * forward as extensions are granted. Nothing recomputes it from the plan afterwards, so a
 * plan re-timed next month does not change a term somebody has already bought.
 *
 * @property Carbon $started_at
 * @property Carbon $ends_at
 * @property Carbon|null $ended_at
 */
class ServiceTerm extends Model
{
    protected $table = 'ext_term_limits';

    protected $guarded = [];

    protected $casts = [
        'hours' => 'integer',
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public const OUTCOME_TERMINATED = 'terminated';

    public const OUTCOME_SUSPENDED = 'suspended';

    /** Closed by hand — the service was cancelled or terminated before its time ran out. */
    public const OUTCOME_RELEASED = 'released';

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ServiceTermExtension::class, 'term_id');
    }

    /** Still running: the clock has not been stopped, whatever the wall clock says. */
    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function hasExpired(): bool
    {
        return $this->isOpen() && $this->ends_at->isPast();
    }

    /** Hours granted on top of what was bought — the sum of every extension. */
    public function extendedHours(): int
    {
        return (int) $this->extensions()->sum('hours');
    }

    /**
     * What a customer should be told: how long is left, or that it has run out.
     *
     * Rounded down to the minute rather than shown to the second — a countdown that ticks
     * is a promise about precision the once-a-minute sweeper does not keep.
     */
    public function remainingForHumans(): string
    {
        if (!$this->isOpen()) {
            return 'Ended';
        }

        return $this->ends_at->isPast()
            ? 'Expired'
            : $this->ends_at->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]);
    }
}
