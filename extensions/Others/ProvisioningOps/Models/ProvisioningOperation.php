<?php

namespace Paymenter\Extensions\Others\ProvisioningOps\Models;

use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One provisioning attempt (per service + extension + action).
 *
 * A row is created the first time an operation fails and updated in place on every
 * later attempt, so the admin list shows one line per broken thing rather than one
 * line per retry.
 */
class ProvisioningOperation extends Model
{
    public const STATUS_FAILED = 'failed';

    public const STATUS_SUCCEEDED = 'succeeded';

    protected $fillable = [
        'service_id',
        'extension',
        'action',
        'status',
        'attempts',
        'error',
        'context',
        'resolved_at',
        'last_attempt_at',
    ];

    protected $casts = [
        'context' => 'array',
        'resolved_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * The ExtensionHelper method that re-runs this operation.
     *
     * Only lifecycle actions are retryable; `callback` rows are informational.
     */
    public function retryMethod(): ?string
    {
        return match ($this->action) {
            'create' => 'createServer',
            'suspend' => 'suspendServer',
            'unsuspend' => 'unsuspendServer',
            'terminate' => 'terminateServer',
            'upgrade' => 'upgradeServer',
            default => null,
        };
    }
}
