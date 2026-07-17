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
 * Adds Brazilian tax fields to the customer registration + account forms using
 * Paymenter's native Custom Properties system (so the fields render and persist
 * with no core edits), and layers on:
 *
 *   - Server-side CPF / CNPJ **checksum validation** (registered as `cpf` and
 *     `cnpj` Laravel validation rules, referenced by the seeded field defs).
 *   - **Encryption at rest** for the sensitive documents (CPF, RG, CNPJ) via
 *     transparent Eloquent model events on the core Property model — the form
 *     still sees plaintext; the database stores ciphertext.
 *   - **Input masks** injected via the theme's `footer` render hook.
 *   - **Access-control permissions** for viewing sensitive documents in admin.
 *
 * Fields seeded (model = User):
 *   Individual: CPF, RG
 *   Business:   Company Name (Razão Social), Trade Name (Nome Fantasia), CNPJ,
 *               State Registration (Inscrição Estadual), SR-Exempt (IE Isento)
 *   Plus a Person Type selector.
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
     * Register `cpf` and `cnpj` validation rules + human messages. The seeded
     * Custom Properties reference these by name in their `validation` column,
     * so they run on both the registration and the account forms.
     */
    private function registerValidators(): void
    {
        Validator::extend('cpf', fn ($attribute, $value) => $value === null || $value === '' || Documents::isValidCpf($value));
        Validator::extend('cnpj', fn ($attribute, $value) => $value === null || $value === '' || Documents::isValidCnpj($value));

        Lang::addLines(['validation.cpf' => 'The :attribute is not a valid CPF.'], 'en');
        Lang::addLines(['validation.cnpj' => 'The :attribute is not a valid CNPJ.'], 'en');
        Lang::addLines(['validation.cpf' => 'O :attribute informado não é um CPF válido.'], 'pt_BR');
        Lang::addLines(['validation.cnpj' => 'O :attribute informado não é um CNPJ válido.'], 'pt_BR');
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
