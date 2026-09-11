<?php

namespace Paymenter\Extensions\Others\ClientTools\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A person listed on a customer's account. */
class Contact extends Model
{
    protected $table = 'ext_ct_contacts';

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'email', 'phone', 'company_name',
        'address', 'address2', 'city', 'state', 'zip', 'country', 'is_sub_account',
        'permissions', 'email_preferences',
    ];

    protected $casts = [
        'is_sub_account' => 'boolean',
        'permissions' => 'array',
        'email_preferences' => 'array',
    ];

    /**
     * Permission keys a sub-account may be granted, in the reference's order.
     * Labels live in the language file so the wording can change without a code edit.
     */
    public const PERMISSIONS = ['invoices', 'services', 'tickets', 'account', 'affiliates'];

    /**
     * The reference's Email Preferences, in its order. A list of ticked keys rather than
     * five columns, so a sixth category is a label rather than a migration.
     */
    public const EMAIL_PREFERENCES = ['general', 'invoice', 'support', 'product', 'domain'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSubAccounts(Builder $query): Builder
    {
        return $query->where('is_sub_account', true);
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
