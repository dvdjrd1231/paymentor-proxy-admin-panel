<?php

namespace App\Models;

use App\Classes\Price as PriceClass;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;

class Plan extends Model implements Auditable
{
    use HasFactory, Traits\Auditable;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
        'billing_period',
        'billing_unit',
        'sort',
    ];

    protected $casts = [
        'billing_period' => 'integer',
    ];

    /**
     * Get the available prices of the plan.
     */
    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    /**
     * Get the priceable model of the plan.
     */
    public function priceable()
    {
        return $this->morphTo();
    }

    /**
     * Get the price of the plan.
     *
     * @param  string|null  $currency  Optional currency code to get the price for. If not provided, it will use the current session currency or default currency.
     */
    public function price($currency = null): PriceClass
    {
        if ($this->type === 'free') {
            return new PriceClass(['currency' => Currency::find($currency ?? session('currency', config('settings.default_currency')))], free: true);
        }
        $currency = $currency ?? session('currency', config('settings.default_currency'));
        $price = $this->prices->where('currency_code', $currency)->first();

        // A plan has one price row per currency, so there is none when a visitor is browsing
        // in a currency this plan was never priced in — a new product before the exchange-rate
        // sync has run, or a currency added after the catalogue. Dereferencing $price here
        // was a fatal "Attempt to read property on null" that took down the storefront and
        // checkout entirely.
        //
        // `price` and `currency` are deliberately left null rather than substituted:
        // PriceClass derives `available` from the currency being set (see Price::formatted,
        // 'available' => $this->currency || $this->is_free), and renders "Not available in
        // your currency" when it is not. Falling back to a looked-up Currency here would
        // mark an unpriced plan *available at 0.00* and let it be ordered for free.
        //
        // setup_fee is the one value safe to default, since a plan with no price row has no
        // setup fee to charge either.
        return new PriceClass((object) [
            'price' => $price,
            'setup_fee' => $price->setup_fee ?? 0,
            'currency' => $price->currency ?? null,
        ]);
    }

    // Time between billing periods
    public function billingDuration(): Attribute
    {
        if ($this->type === 'free' || $this->type == 'one-time') {
            return Attribute::make(get: fn () => 0);
        }
        $diffInDays = match ($this->billing_unit) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
        };

        return Attribute::make(
            get: fn () => $diffInDays * $this->billing_period
        );
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
