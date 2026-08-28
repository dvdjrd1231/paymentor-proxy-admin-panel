<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\UserResource;
use App\Models\Currency;
use App\Models\CustomProperty;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public static function canAccess(): bool
    {
        return UserResource::canCreate();
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

            foreach ($values as $key => $value) {
                $user->properties()->create(['key' => $key, 'value' => $value]);
            }

            return $user;
        });

        return $this->redirect(ClientSummary::getUrl(['record' => $user->id]));
    }
}
