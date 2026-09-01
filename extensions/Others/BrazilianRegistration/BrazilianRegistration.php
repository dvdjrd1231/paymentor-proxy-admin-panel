<?php

namespace Paymenter\Extensions\Others\BrazilianRegistration;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Property;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\BrazilianRegistration\Support\Documents;

/**
 * Brazilian customer registration.
 *
 * Tax fields on the registration and account forms via Paymenter's Custom Properties, so
 * they render and persist with no core edits. On top of that: CPF/CNPJ checksum validation
 * as `cpf` and `cnpj` rules, encryption at rest for CPF/RG/CNPJ through model events on
 * Property (the form sees plaintext, the database stores ciphertext), input masks via the
 * theme's footer hook, and an admin permission for viewing the documents.
 *
 * Seeded on User: a Person Type selector, CPF and RG for individuals, and Razão Social,
 * Nome Fantasia, CNPJ, Inscrição Estadual and IE Isento for businesses.
 *
 * @see docs/modules/brazilian-registration.md
 */
#[ExtensionMeta(
    name: 'Brazilian Customer Registration',
    description: 'CPF/CNPJ registration fields with validation, masks, encrypted storage, and access control.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class BrazilianRegistration extends Extension
{
    /** Sensitive document keys that are encrypted at rest. */
    private const SENSITIVE_KEYS = ['cpf', 'rg', 'cnpj'];

    private const MIGRATIONS_PATH = 'extensions/Others/BrazilianRegistration/database/migrations';

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Adds Brazilian tax fields (CPF, RG, CNPJ, Razão Social, Inscrição Estadual, …) to the '
                    . 'registration and account forms, with CPF/CNPJ validation, input masks, and encrypted '
                    . 'storage of sensitive documents. Enable this extension to seed the fields; disable it to remove them.'
                ),
            ],
        ];
    }

    /** Seed the Custom Property field definitions. */
    public function installed()
    {
        ExtensionHelper::runMigrations(self::MIGRATIONS_PATH);
    }

    /** Remove the seeded fields (and, via FK cascade, their stored values). */
    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations(self::MIGRATIONS_PATH);
    }

    public function boot()
    {
        View::addNamespace('brazilianregistration', __DIR__ . '/resources/views');

        $this->registerValidators();
        $this->registerEncryptionAtRest();
        $this->registerInputMasks();
        $this->registerPermissions();
    }

    /**
     * Register the `cpf` and `cnpj` rules. The seeded Custom Properties reference them by
     * name, so they run on both the registration and the account forms.
     */
    private function registerValidators(): void
    {
        Validator::extend('cpf', fn ($attribute, $value) => $value === null || $value === '' || Documents::isValidCpf($value));
        Validator::extend('cnpj', fn ($attribute, $value) => $value === null || $value === '' || Documents::isValidCnpj($value));

        // Issue #38: which documents are mandatory depends on two other answers on the same
        // form — the country, and whether this is a Pessoa Física or a Pessoa Jurídica.
        // `required_if` cannot express that: it takes one field and a literal value, and the
        // literal here is an accented label that is free to be reworded. These read the
        // sibling answers out of the validator's own data instead, so the rule survives a
        // relabelling and applies to whatever form carries the fields.
        //
        // Registered as implicit rules: a plain extension is skipped when the value is
        // empty, which is exactly the case they exist to catch.
        $personType = fn ($validator) => (string) data_get($validator->getData(), 'properties.person_type', '');
        $country = fn ($validator) => (string) data_get($validator->getData(), 'properties.country', '');

        Validator::extendImplicit(
            'brazil_person_type',
            fn ($attribute, $value, $parameters, $validator) => !Documents::isBrazil($country($validator))
                || trim((string) $value) !== '',
        );

        Validator::extendImplicit(
            'cpf_required',
            fn ($attribute, $value, $parameters, $validator) => !Documents::isIndividual($personType($validator))
                || trim((string) $value) !== '',
        );

        Validator::extendImplicit(
            'cnpj_required',
            fn ($attribute, $value, $parameters, $validator) => !Documents::isCompany($personType($validator))
                || trim((string) $value) !== '',
        );

        // Not mandatory, but not simply omittable either: a company either states its
        // Inscrição Estadual or declares itself exempt from one.
        Validator::extendImplicit(
            'ie_or_exempt',
            fn ($attribute, $value, $parameters, $validator) => !Documents::isCompany($personType($validator))
                || trim((string) $value) !== ''
                || filter_var(
                    data_get($validator->getData(), 'properties.state_registration_exempt'),
                    FILTER_VALIDATE_BOOL,
                ),
        );

        $messages = [
            'en' => [
                'validation.cpf' => 'The :attribute is not a valid CPF.',
                'validation.cnpj' => 'The :attribute is not a valid CNPJ.',
                'validation.brazil_person_type' => 'Choose whether this is a Pessoa Física or a Pessoa Jurídica.',
                'validation.cpf_required' => 'A Pessoa Física is registered by CPF, so the CPF is required.',
                'validation.cnpj_required' => 'A Pessoa Jurídica is registered by CNPJ, so the CNPJ is required.',
                'validation.ie_or_exempt' => 'Give the Inscrição Estadual, or tick Isento if the company is exempt.',
            ],
            'pt_BR' => [
                'validation.cpf' => 'O :attribute informado não é um CPF válido.',
                'validation.cnpj' => 'O :attribute informado não é um CNPJ válido.',
                'validation.brazil_person_type' => 'Selecione se o cadastro é de Pessoa Física ou Pessoa Jurídica.',
                'validation.cpf_required' => 'O CPF é obrigatório para Pessoa Física.',
                'validation.cnpj_required' => 'O CNPJ é obrigatório para Pessoa Jurídica.',
                'validation.ie_or_exempt' => 'Informe a Inscrição Estadual ou marque Isento.',
            ],
        ];

        $translator = app('translator');

        foreach ($messages as $locale => $lines) {
            // Load `validation` from disk FIRST. addLines() writes straight into the
            // translator's loaded-group cache and marks the group as loaded, so calling
            // it on a group that has not been read yet permanently shadows the real
            // lang/<locale>/validation.php — every other message in that file
            // ("required", "max", …) then renders as the raw key app-wide.
            $translator->load('*', 'validation', $locale);

            Lang::addLines($lines, $locale);
        }
    }

    /**
     * Transparently encrypt sensitive document values at rest. We attach model
     * events to the core Property model (no core edit): values are encrypted on
     * save and decrypted on read, so forms and validation keep working with
     * plaintext while the database only ever holds ciphertext.
     */
    private function registerEncryptionAtRest(): void
    {
        Property::saving(function (Property $property) {
            if (!in_array($property->key, self::SENSITIVE_KEYS, true)) {
                return;
            }
            if ($property->value === null || $property->value === '') {
                return;
            }
            // Only encrypt if it isn't already ciphertext (idempotent).
            if (!$this->isEncrypted($property->value)) {
                $property->value = Crypt::encryptString($property->value);
            }
        });

        Property::retrieved(function (Property $property) {
            if (!in_array($property->key, self::SENSITIVE_KEYS, true)) {
                return;
            }
            if ($property->value === null || $property->value === '') {
                return;
            }
            try {
                $property->value = Crypt::decryptString($property->value);
            } catch (\Throwable $e) {
                // Legacy plaintext (pre-encryption) — leave as-is.
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Inject the input-mask script through the theme `footer` render hook. */
    private function registerInputMasks(): void
    {
        Event::listen('footer', fn () => ['view' => view('brazilianregistration::masks')]);
    }

    /** Expose admin permissions for viewing sensitive documents. */
    private function registerPermissions(): void
    {
        Event::listen('permissions', fn () => [
            'admin.brazilian.view_documents' => 'View sensitive Brazilian documents (CPF/CNPJ)',
        ]);
    }
}
