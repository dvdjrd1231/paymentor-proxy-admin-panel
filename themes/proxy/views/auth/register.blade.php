{{--
    WHMCS-style registration form (Proxy theme).

    Overrides the default theme's register view. Instead of dumping every custom
    property through <x-form.properties>, each field is placed explicitly so we get
    WHMCS's sectioned layout — and so the Brazilian tax fields can be shown ONLY
    when the selected country is Brazil.

    Binding notes:
      * Core fields are public props on App\Livewire\Auth\Register.
      * Everything else lives in the $properties array (Paymenter "custom properties"),
        seeded by CustomPropertySeeder (phone, company_name, address, address2, city,
        state, zip, country) and by the BrazilianRegistration extension (cpf, rg, cnpj,
        trade_name, state_registration, state_registration_exempt).
      * The country <select> uses wire:model.live so changing it re-renders and
        toggles the Brazil block. Server-side validation still comes from each
        custom property's own rules, so hiding a field can't bypass anything.
--}}
@php
    $props = collect($custom_properties ?? []);
    $countryProp = $props->firstWhere('key', 'country');
    $countryOptions = (array) ($countryProp->allowed_values ?? []);
    // allowed_values may be a flat list of names or an associative code => name map.
    $countryIsAssoc = $countryOptions && array_keys($countryOptions) !== range(0, count($countryOptions) - 1);

    $selectedCountry = $properties['country'] ?? '';
    // Match however the country happens to be stored (name or ISO code).
    $isBrazil = in_array($selectedCountry, ['BR', 'Brazil', 'Brasil'], true);

    // Only render Brazilian fields the extension actually installed.
    $has = fn (string $key) => $props->contains(fn ($p) => $p->key === $key);
@endphp

<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('auth.sign_up_title') }}</h1>
        <span>Create an account with us&hellip;</span>
    </div>
    <p class="wf-crumbs">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a><span>/</span>{{ __('auth.sign_up_title') }}
    </p>
    <hr class="wf-title-rule">

    <div class="wf-form-narrow">
        <form wire:submit.prevent="submit" id="register">
        {{-- ─────────────── Personal Information ─────────────── --}}
        <div class="wf-section">{{ __('theme.personal_information') }}</div>
        <div class="wf-grid">
            <div class="wf-field">
                <label for="first_name">{{ __('general.input.first_name') }}<span class="wf-req">*</span></label>
                <input id="first_name" type="text" class="wf-input" wire:model="first_name"
                    placeholder="{{ __('general.input.first_name_placeholder') }}" required>
                @error('first_name') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field">
                <label for="last_name">{{ __('general.input.last_name') }}<span class="wf-req">*</span></label>
                <input id="last_name" type="text" class="wf-input" wire:model="last_name"
                    placeholder="{{ __('general.input.last_name_placeholder') }}" required>
                @error('last_name') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field">
                <label for="email">{{ __('general.input.email') }}<span class="wf-req">*</span></label>
                <input id="email" type="email" class="wf-input" wire:model="email"
                    placeholder="{{ __('general.input.email_placeholder') }}" required>
                @error('email') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            @if ($has('phone'))
                <div class="wf-field">
                    <label for="phone">{{ __('theme.phone_number') }}<span class="wf-req">*</span></label>
                    <input id="phone" type="text" class="wf-input" wire:model="properties.phone" placeholder="Phone Number">
                    @error('properties.phone') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        {{-- ─────────────── Billing Address ─────────────── --}}
        <div class="wf-section">{{ __('theme.billing_address') }}</div>
        <div class="wf-grid">
            @if ($has('company_name'))
                <div class="wf-field wf-col-2">
                    <label for="company_name">{{ __('theme.company_name') }} <span class="wf-section-note">{{ __('theme.optional') }}</span></label>
                    <input id="company_name" type="text" class="wf-input" wire:model="properties.company_name"
                        placeholder="Company Name (Optional)">
                    @error('properties.company_name') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            @if ($has('address'))
                <div class="wf-field wf-col-2">
                    <label for="address">{{ __('theme.street_address') }}<span class="wf-req">*</span></label>
                    <input id="address" type="text" class="wf-input" wire:model="properties.address" placeholder="Street Address">
                    @error('properties.address') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            @if ($has('address2'))
                <div class="wf-field wf-col-2">
                    <label for="address2">{{ __('theme.street_address_2') }}</label>
                    <input id="address2" type="text" class="wf-input" wire:model="properties.address2" placeholder="Street Address 2">
                    @error('properties.address2') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            @if ($has('city'))
                <div class="wf-field">
                    <label for="city">{{ __('theme.city') }}<span class="wf-req">*</span></label>
                    <input id="city" type="text" class="wf-input" wire:model="properties.city" placeholder="City">
                    @error('properties.city') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            @if ($has('state'))
                <div class="wf-field">
                    <label for="state">{{ __('theme.state_region') }}<span class="wf-req">*</span></label>
                    <input id="state" type="text" class="wf-input" wire:model="properties.state" placeholder="State/Region">
                    @error('properties.state') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            @if ($has('zip'))
                <div class="wf-field">
                    <label for="zip">{{ __('theme.postcode') }}<span class="wf-req">*</span></label>
                    <input id="zip" type="text" class="wf-input" wire:model="properties.zip" placeholder="Postcode">
                    @error('properties.zip') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif

            @if ($countryProp)
                <div class="wf-field">
                    <label for="country">{{ __('theme.country') }}<span class="wf-req">*</span></label>
                    {{-- .live so the Brazil block appears/disappears as soon as this changes --}}
                    <select id="country" class="wf-select" wire:model.live="properties.country">
                        <option value="">{{ __('theme.select_country') }}</option>
                        @foreach ($countryOptions as $key => $label)
                            <option value="{{ $countryIsAssoc ? $key : $label }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('properties.country') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        {{-- ─────────────── Additional Information (Brazil only) ─────────────── --}}
        @if ($isBrazil)
            <div class="wf-section">
                Additional Information
                <span class="wf-section-note">(Brazil)</span>
            </div>

            <div class="wf-br">
                <div class="wf-br-head">
                    <span class="wf-br-flag">🇧🇷</span>
                    <span>Dados fiscais &mdash; Brazilian tax details</span>
                </div>

                <div class="wf-grid">
                    @if ($has('cpf'))
                        <div class="wf-field">
                            <label for="cpf">CPF</label>
                            <input id="cpf" type="text" class="wf-input" wire:model="properties.cpf"
                                placeholder="000.000.000-00" inputmode="numeric" maxlength="14">
                            @error('properties.cpf') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('rg'))
                        <div class="wf-field">
                            <label for="rg">RG</label>
                            <input id="rg" type="text" class="wf-input" wire:model="properties.rg" placeholder="RG">
                            @error('properties.rg') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('cnpj'))
                        <div class="wf-field">
                            <label for="cnpj">{{ __('theme.cnpj') }}</label>
                            <input id="cnpj" type="text" class="wf-input" wire:model="properties.cnpj"
                                placeholder="00.000.000/0000-00" inputmode="numeric" maxlength="18">
                            @error('properties.cnpj') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('trade_name'))
                        <div class="wf-field">
                            <label for="trade_name">{{ __('theme.trade_name') }} <span class="wf-section-note">{{ __('theme.trade_name_hint') }}</span></label>
                            <input id="trade_name" type="text" class="wf-input" wire:model="properties.trade_name"
                                placeholder="Nome Fantasia">
                            @error('properties.trade_name') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('state_registration'))
                        <div class="wf-field">
                            <label for="state_registration">Inscrição Estadual</label>
                            <input id="state_registration" type="text" class="wf-input"
                                wire:model="properties.state_registration" placeholder="Inscrição Estadual">
                            @error('properties.state_registration') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('state_registration_exempt'))
                        <div class="wf-field" style="justify-content:flex-end">
                            <label class="wf-check">
                                <input type="checkbox" wire:model="properties.state_registration_exempt">
                                <span>Isento de Inscrição Estadual</span>
                            </label>
                            @error('properties.state_registration_exempt') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ─────────────── Account Security ─────────────── --}}
        <div class="wf-section">{{ __('theme.account_security') }}</div>
        <div class="wf-grid">
            <div class="wf-field">
                <label for="password">{{ __('general.input.password') }}<span class="wf-req">*</span></label>
                <input id="password" type="password" class="wf-input" wire:model="password"
                    placeholder="{{ __('general.input.password_placeholder') }}" required>
                @error('password') <span class="wf-error">{{ $message }}</span> @enderror
            </div>

            <div class="wf-field">
                <label for="password_confirmation">{{ __('general.input.password_confirmation') }}<span class="wf-req">*</span></label>
                <input id="password_confirmation" type="password" class="wf-input" wire:model="password_confirmation"
                    placeholder="{{ __('general.input.password_confirmation_placeholder') }}" required>
            </div>
        </div>

        @if (config('settings.tos'))
            <div style="margin-top:1.25rem">
                <label class="wf-check">
                    <input type="checkbox" wire:model="tos" required>
                    <span>
                        {{ __('product.tos') }}
                        <a href="{{ config('settings.tos') }}" target="_blank" style="color:var(--brand)">
                            {{ __('product.tos_link') }}
                        </a>
                    </span>
                </label>
                @error('tos') <span class="wf-error">{{ $message }}</span> @enderror
            </div>
        @endif

        <div style="margin-top:1.25rem">
            <x-captcha :form="'register'" />
        </div>

        <div class="wf-actions">
            <button type="submit" class="wf-btn">{{ __('auth.sign_up') }}</button>
        </div>

            <div class="wf-alt">
                {{ __('auth.already_have_account') }}
                <a href="{{ route('login') }}" wire:navigate>{{ __('auth.sign_in') }}</a>
            </div>
        </form>
    </div>
</div>
