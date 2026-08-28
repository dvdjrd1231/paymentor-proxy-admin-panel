<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Helpers\NotificationHelper;
use App\Models\Currency;
use App\Models\CustomProperty;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Add New Client, to its screenshot: the two-column zebra form — names, company,
 * email and password on the left; address, country, phone and currency on the right — with
 * the store's own custom properties (CPF, CNPJ, and the rest) underneath.
 *
 * The property fields are not copied from the screenshot but read from `custom_properties`:
 * the CPF/CNPJ rows in Leandro's screenshot are simply this store's registered User
 * properties, so the form renders whatever is configured and survives the next property
 * being added without an edit here. The reference's Status, Client Group, Payment Method
 * and Billing Contact are rendered with the one honest value Paymenter has for each.
 *
 * Creation is one transaction: the user plus every filled property, then straight to the
 * new client's Summary — where WHMCS lands too.
 */
class AddNewClient extends Page
{
    protected string $view = 'adminops::pages.add-new-client';

    protected static ?string $slug = 'add-client';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** The keys the fixed layout places itself; everything else renders in the extras rows. */
    public const PLACED = ['company_name', 'address', 'address2', 'city', 'state', 'zip', 'country', 'phone'];

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public string $currency = '';

    /** @var array<string, string> property key => value, bound field by field in the view */
    public array $props = [];

    /**
     * The reference's Email Notifications block, all on by default as it ships them.
     * Stored as `email_pref_*` properties, so they are real preferences on the profile.
     *
     * @var array<string, bool>
     */
    public array $prefs = [
        'general' => true,
        'invoice' => true,
        'support' => true,
        'product' => true,
        'domain' => true,
        'affiliate' => true,
    ];

    /**
     * The reference's Settings toggles, with its defaults. Stored as `setting_*` properties.
     *
     * @var array<string, bool>
     */
    public array $settings = [
        'late_fees' => true,
        'overdue_notices' => true,
        'tax_exempt' => false,
        'separate_invoices' => false,
        'disable_cc' => false,
        'marketing_optin' => false,
        'status_update' => true,
        'single_sign_on' => true,
    ];

    /** The reference's Admin Notes — the same `admin_notes` property the profile edits. */
    public string $notes = '';

    /** "Check to send a New Account Information Message" — the account email, really sent. */
    public bool $sendWelcome = true;

    public static function canAccess(): bool
    {
        return UserResource::canCreate();
    }

    /** The reference's defaults: Currency USD, Country United States — when the store has them. */
    public function mount(): void
    {
        $currencies = Currency::query()->pluck('code')->all();
        $this->currency = in_array('USD', $currencies, true) ? 'USD' : ($currencies[0] ?? '');

        $country = CustomProperty::query()->where('model', User::class)->where('key', 'country')->first();
        $options = array_values((array) ($country?->allowed_values ?? []));

        if (in_array('United States', $options, true)) {
            $this->props['country'] = 'United States';
        }
    }

    public function getTitle(): string
    {
        return 'Add New Client';
    }

    /**
     * @return array{fixed: Collection, extras: Collection, currencies: array<int, string>}
     */
    protected function getViewData(): array
    {
        // ->toBase() is load-bearing: Eloquent's Collection overrides only()/except() to
        // filter by model *primary keys*, so on the keyed Eloquent collection
        // only(['phone', …]) compared property names against ids 1..15 and returned
        // nothing — every field fell silently into the extras columns.
        $properties = CustomProperty::query()
            ->where('model', User::class)
            ->get()
            ->toBase()
            ->keyBy('key');

        return [
            'fixed' => $properties->only(self::PLACED),
            'extras' => $properties->except(self::PLACED),
            'currencies' => Currency::query()->pluck('code')->all(),
        ];
    }

    public function create()
    {
        $required = CustomProperty::query()
            ->where('model', User::class)
            ->where('required', true)
            ->pluck('key');

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];

        foreach ($required as $key) {
            $rules['props.' . $key] = ['required', 'string'];
        }

        $this->validate($rules, attributes: $required->mapWithKeys(
            fn (string $key) => ['props.' . $key => str_replace('_', ' ', $key)],
        )->all());

        $user = DB::transaction(function (): User {
            $user = User::create([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);

            $values = array_filter(array_map(trim(...), $this->props), fn ($v) => $v !== '');

            if ($this->currency !== '') {
                $values['currency'] = $this->currency;
            }

            // The reference's blocks, as real stored preferences on the profile.
            foreach ($this->prefs as $key => $on) {
                $values['email_pref_' . $key] = $on ? '1' : '0';
            }

            foreach ($this->settings as $key => $on) {
                $values['setting_' . $key] = $on ? '1' : '0';
            }

            if (trim($this->notes) !== '') {
                $values['admin_notes'] = trim($this->notes);
            }

            foreach ($values as $key => $value) {
                $user->properties()->create(['key' => $key, 'value' => $value]);
            }

            return $user;
        });

        // The reference's "New Account Information Message": the account email, through the
        // same helper core uses. Best effort — a mail server that is down must not undo a
        // client that was created.
        if ($this->sendWelcome) {
            try {
                NotificationHelper::emailVerificationNotification($user);
            } catch (\Throwable $e) {
                Log::warning('AddNewClient: welcome email failed', [
                    'user' => $user->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $this->redirect(ClientSummary::getUrl(['record' => $user->id]));
    }
}
