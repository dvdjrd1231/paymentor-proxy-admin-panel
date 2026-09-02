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

    /**
     * Brazil's registry fields. Leandro: Brazil is the only country that uses them, so they
     * render (and save) only when the country is Brazil — and within Brazil only the set
     * that belongs to the kind of person being registered.
     */
    public const BRAZIL_ONLY = ['person_type', 'cpf', 'rg', 'trade_name', 'cnpj', 'state_registration', 'state_registration_exempt', 'municipal_registration'];

    /** A private citizen: RG and CPF. */
    public const INDIVIDUAL_FIELDS = ['cpf', 'rg'];

    /** A constituted company: CNPJ, and the two registrations that go with it. */
    public const COMPANY_FIELDS = ['trade_name', 'cnpj', 'state_registration', 'state_registration_exempt', 'municipal_registration'];

    /** What a company writes in Inscrição Estadual when it has none — the accepted term. */
    public const EXEMPT = 'ISENTO';

    public static function isBrazil(?string $country): bool
    {
        return in_array(strtolower(trim((string) $country)), ['brazil', 'brasil', 'br'], true);
    }

    /**
     * Pessoa Jurídica or Pessoa Física. Matched loosely on purpose: the stored value is the
     * label a tax document needs to read, and it has been through a migration and a select
     * before it gets here.
     */
    public static function isCompany(?string $personType): bool
    {
        $value = strtolower(trim((string) $personType));

        return $value !== '' && (str_contains($value, 'jur') || str_contains($value, 'company') || $value === 'pj');
    }

    public static function isIndividual(?string $personType): bool
    {
        return trim((string) $personType) !== '' && !static::isCompany($personType);
    }

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
     * @return array{fixed: Collection, extras: Collection, brazil: Collection, brazilFields: array<int, string>, currencies: array<int, string>}
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
            // The registry fields have their own block, so they never fall into the
            // generic extras columns — where CPF sat next to CNPJ with nothing to say
            // which of the two the person being registered actually has.
            'extras' => $properties->except([...self::PLACED, ...self::BRAZIL_ONLY]),
            'brazil' => $properties->only(self::BRAZIL_ONLY),
            'brazilFields' => $this->brazilFields(),
            'isExempt' => $this->isExempt(),
            'currencies' => Currency::query()->pluck('code')->all(),
        ];
    }

    /**
     * Which registry fields apply right now: none outside Brazil, the selector alone until
     * the kind of person is chosen, and then that kind's documents.
     *
     * @return array<int, string>
     */
    public function brazilFields(): array
    {
        if (!static::isBrazil($this->props['country'] ?? null)) {
            return [];
        }

        $type = $this->props['person_type'] ?? '';

        return match (true) {
            static::isCompany($type) => ['person_type', ...self::COMPANY_FIELDS],
            static::isIndividual($type) => ['person_type', ...self::INDIVIDUAL_FIELDS],
            default => ['person_type'],
        };
    }

    public function isExempt(): bool
    {
        return filter_var($this->props['state_registration_exempt'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * Ticking Isento writes the word into the field it replaces, so the form shows what the
     * invoice will say instead of leaving a disabled box looking empty. Unticking takes it
     * back out, but only if it is still the word we put there.
     */
    public function updatedProps(mixed $value, ?string $key = null): void
    {
        // Livewire passes no $key when the whole array is replaced (entangled selects do
        // this) — seen live as issue #38's "Error while loading page" toasts.
        if ($key !== 'state_registration_exempt') {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_BOOL)) {
            $this->props['state_registration'] = self::EXEMPT;
        } elseif (($this->props['state_registration'] ?? '') === self::EXEMPT) {
            $this->props['state_registration'] = '';
        }
    }

    /** Field names as a Brazilian registrant would recognise them in an error message. */
    private const BRAZIL_LABELS = [
        'props.person_type' => 'person type',
        'props.cpf' => 'CPF',
        'props.rg' => 'RG',
        'props.cnpj' => 'CNPJ',
        'props.state_registration' => 'Inscrição Estadual',
        'props.municipal_registration' => 'Inscrição Municipal',
    ];

    /**
     * Brazil's own requirements, which are not the column's.
     *
     * A registration is either a citizen or a company, and the document that identifies it
     * is mandatory: CPF for a Pessoa Física, CNPJ for a Pessoa Jurídica. Everything else is
     * optional — the RG, the Nome Fantasia, the Inscrição Municipal — with the single
     * exception of the Inscrição Estadual, which a company must either state or declare
     * itself exempt from. That pair is checked in {@see create()}, where both halves of it
     * can be read at once.
     *
     * The `cpf` and `cnpj` rules are the checksum validators the Brazilian Registration
     * extension registers; they are applied only when that extension is providing them, so
     * the form still works with it switched off.
     *
     * @return array<string, array<int, string>>
     */
    private function brazilRules(): array
    {
        $fields = $this->brazilFields();

        if ($fields === []) {
            return [];
        }

        $rules = ['props.person_type' => ['required', 'string']];

        if (in_array('cpf', $fields, true)) {
            $rules['props.cpf'] = ['required', 'string'];
            $rules['props.rg'] = ['nullable', 'string', 'max:20'];
        }

        if (in_array('cnpj', $fields, true)) {
            $rules['props.cnpj'] = ['required', 'string'];
            $rules['props.state_registration'] = ['nullable', 'string', 'max:30'];
            $rules['props.municipal_registration'] = ['nullable', 'string', 'max:30'];
        }

        return $rules;
    }

    /**
     * The two checks that need both halves of a pair in hand, run after the field rules pass.
     *
     * The checksums go through the Brazilian Registration extension's own helper rather than
     * its `cpf`/`cnpj` validation rules: the rules only exist while that extension is booted,
     * and naming one that is not registered throws rather than failing the field. Calling the
     * helper directly means the form degrades to "required" if the extension is off, instead
     * of breaking.
     *
     * @return array<string, string> field => message, empty when everything holds
     */
    private function brazilProblems(): array
    {
        $fields = $this->brazilFields();
        $problems = [];
        $documents = \Paymenter\Extensions\Others\BrazilianRegistration\Support\Documents::class;
        $value = fn (string $key) => trim((string) ($this->props[$key] ?? ''));

        if (in_array('cpf', $fields, true) && class_exists($documents)
            && $value('cpf') !== '' && !$documents::isValidCpf($value('cpf'))) {
            $problems['props.cpf'] = 'That CPF is not valid — check the digits.';
        }

        if (in_array('cnpj', $fields, true) && class_exists($documents)
            && $value('cnpj') !== '' && !$documents::isValidCnpj($value('cnpj'))) {
            $problems['props.cnpj'] = 'That CNPJ is not valid — check the digits.';
        }

        // Not mandatory, but not simply omittable either: a company either has an Inscrição
        // Estadual or is exempt from one, and the invoice has to say which.
        if (in_array('state_registration', $fields, true)
            && $value('state_registration') === ''
            && !filter_var($this->props['state_registration_exempt'] ?? false, FILTER_VALIDATE_BOOL)) {
            $problems['props.state_registration'] =
                'Give the Inscrição Estadual, or tick Isento if the company is exempt.';
        }

        return $problems;
    }

    /** @return array<string, string> */
    private function brazilMessages(): array
    {
        return [
            'props.person_type.required' => 'Choose whether this is a Pessoa Física or a Pessoa Jurídica.',
            'props.cpf.required' => 'A Pessoa Física is registered by CPF, so the CPF is required.',
            'props.cnpj.required' => 'A Pessoa Jurídica is registered by CNPJ, so the CNPJ is required.',
        ];
    }

    public function create()
    {
        $required = CustomProperty::query()
            ->where('model', User::class)
            ->where('required', true)
            ->pluck('key')
            // A hidden field cannot be demanded: a registry field is only asked for when the
            // country is Brazil *and* it belongs to the kind of person being registered.
            ->reject(fn (string $key) => in_array($key, self::BRAZIL_ONLY, true)
                && !in_array($key, $this->brazilFields(), true));

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];

        foreach ($required as $key) {
            $rules['props.' . $key] = ['required', 'string'];
        }

        $attributes = $required->mapWithKeys(
            fn (string $key) => ['props.' . $key => str_replace('_', ' ', $key)],
        )->all();

        $this->validate(
            [...$rules, ...$this->brazilRules()],
            $this->brazilMessages(),
            [...$attributes, ...self::BRAZIL_LABELS],
        );

        if ($problems = $this->brazilProblems()) {
            foreach ($problems as $field => $message) {
                $this->addError($field, $message);
            }

            return null;
        }

        $user = DB::transaction(function (): User {
            $user = User::create([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);

            $values = array_filter(array_map(trim(...), $this->props), fn ($v) => $v !== '');

            // Only the registry fields that were actually asked for are kept. Without this a
            // CPF typed before the person type was switched to Jurídica would be stored on a
            // company, and the profile would carry a document it has no business holding.
            $values = array_diff_key($values, array_flip(
                array_diff(self::BRAZIL_ONLY, $this->brazilFields()),
            ));

            if (in_array('state_registration', $this->brazilFields(), true)) {
                $exempt = filter_var($this->props['state_registration_exempt'] ?? false, FILTER_VALIDATE_BOOL);
                $values['state_registration_exempt'] = $exempt ? '1' : '0';

                // An exempt company writes the word, not a number — so the invoice reads
                // "Inscrição Estadual: ISENTO" rather than leaving the line blank.
                if ($exempt) {
                    $values['state_registration'] = self::EXEMPT;
                }
            }

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
