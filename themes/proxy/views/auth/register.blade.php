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
      * The country <select> toggles the Brazil block in Alpine rather than with
        wire:model.live. A live binding round-trips to the server, and the core
        Captchable trait verifies the CAPTCHA on *every* property update — so
        picking a country before solving the CAPTCHA raised "The CAPTCHA is
        required." halfway through the form. Toggling client-side keeps the
        binding deferred, so the only commit is the submit. Server-side validation
        still comes from each custom property's own rules, so hiding a field
        can't bypass anything.
--}}
@php
    $props = collect($custom_properties ?? []);
    $countryProp = $props->firstWhere('key', 'country');
    $countryOptions = (array) ($countryProp->allowed_values ?? []);
    // allowed_values may be a flat list of names or an associative code => name map.
    $countryIsAssoc = $countryOptions && array_keys($countryOptions) !== range(0, count($countryOptions) - 1);

    $selectedCountry = $properties['country'] ?? '';

    // Only render Brazilian fields the extension actually installed.
    $has = fn (string $key) => $props->contains(fn ($p) => $p->key === $key);
@endphp

{{-- The reference portal pulls the heading over the form column, leaving the
     "Already Registered?" rail alongside it rather than below it, so heading,
     breadcrumb and form all live in the right-hand column. --}}
{{-- `personType` drives which half of the Brazilian block is shown; the two getters keep
     the matching in one place and match loosely, because the stored value is the label a
     tax document has to read and is free to be reworded. `setExempt` writes ISENTO into the
     field it replaces, so the form shows what the invoice will say rather than leaving a
     disabled box looking empty — and takes it back out again if the tick is removed. --}}
<div class="wf-page" x-data="{
        country: @js($selectedCountry),
        personType: @js($properties['person_type'] ?? ''),
        exempt: @js((bool) ($properties['state_registration_exempt'] ?? false)),
        get isCompany() {
            const v = (this.personType || '').toLowerCase();
            return v.includes('jur') || v.includes('company') || v === 'pj';
        },
        get isIndividual() { return !!this.personType && !this.isCompany; },
        setExempt(on) {
            this.exempt = on;
            const ie = document.getElementById('state_registration');
            if (!ie) return;
            if (on && ie.value.trim() === '') { ie.value = 'ISENTO'; }
            else if (!on && ie.value.trim() === 'ISENTO') { ie.value = ''; }
            ie.dispatchEvent(new Event('input', { bubbles: true }));
        },
     }">
    <div class="wf-authgrid">
        {{-- ── "Already Registered?" rail ─────────────────────────────────
             The reference portal puts login and password recovery beside the form
             rather than only at the bottom, so someone who already has an account
             leaves the page at the top instead of filling it in first. --}}
        <div>
            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span class="wf-head-icon"><x-ri-user-line /></span>{{ __('theme.already_registered') }}
                    <span class="wf-chevron">▲</span>
                </div>
                <p class="wf-rail-note">{{ __('theme.already_registered_help') }}</p>
                <ul class="wf-list">
                    <li>
                        <a href="{{ route('login') }}" wire:navigate>
                            <span>{{ __('auth.sign_in') }}</span>
                            <span class="wf-head-icon"><x-ri-user-line /></span>
                        </a>
                    </li>
                    @if (Route::has('password.request'))
                        <li>
                            <a href="{{ route('password.request') }}" wire:navigate>
                                <span>{{ __('theme.lost_password') }}</span>
                                <span class="wf-head-icon"><x-ri-asterisk /></span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        <div>
        <div class="wf-title">
            <h1>{{ __('auth.sign_up_title') }}</h1>
            <span>Create an account with us&hellip;</span>
        </div>
        <p class="wf-crumbs">
            <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a><span>/</span>{{ __('auth.sign_up_title') }}
        </p>

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
                    <select id="country" class="wf-select" wire:model="properties.country"
                            x-on:change="country = $event.target.value">
                        <option value="">{{ __('theme.select_country') }}</option>
                        @foreach ($countryOptions as $key => $label)
                            <option value="{{ $countryIsAssoc ? $key : $label }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('properties.country') <span class="wf-error">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        {{-- ─────────────── Additional Information (Brazil only) ───────────────
             Rendered always, revealed by Alpine, so choosing Brazil costs no
             round-trip. The country may be stored as an ISO code or as a name. --}}
        <div x-show="['BR', 'Brazil', 'Brasil'].includes(country)" x-cloak>
            <div class="wf-section">
                Additional Information
                <span class="wf-section-note">(Brazil)</span>
            </div>

            <div class="wf-br">
                <div class="wf-br-head">
                    <span class="wf-br-flag">🇧🇷</span>
                    <span>Dados fiscais &mdash; Brazilian tax details</span>
                </div>

                {{-- Issue #38: the two naturezas need different documents, so the kind of
                     person is asked first and the rest follows from it. Pessoa Física is a
                     citizen (RG, CPF); Pessoa Jurídica is a registered company (CNPJ, and
                     the state and municipal registrations). Toggled in Alpine for the same
                     reason the country is — a live binding re-verifies the CAPTCHA on every
                     property update. What is actually *required* is decided server-side by
                     each field's own rules, so hiding one cannot bypass anything. --}}
                @if ($has('person_type'))
                    @php $personTypeProp = $props->firstWhere('key', 'person_type'); @endphp
                    <div class="wf-grid">
                        <div class="wf-field">
                            <label for="person_type">Tipo de Pessoa <span class="wf-req">*</span></label>
                            <select id="person_type" class="wf-select" wire:model="properties.person_type"
                                    x-on:change="personType = $event.target.value">
                                <option value="">Selecione &mdash; Pessoa Física ou Jurídica</option>
                                @foreach ((array) ($personTypeProp->allowed_values ?? []) as $value)
                                    <option value="{{ $value }}">{{ $value }}</option>
                                @endforeach
                            </select>
                            @error('properties.person_type') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif

                {{-- Pessoa Física --}}
                <div class="wf-grid" x-show="isIndividual" x-cloak>
                    @if ($has('cpf'))
                        <div class="wf-field">
                            <label for="cpf">CPF <span class="wf-req">*</span></label>
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
                </div>

                {{-- Pessoa Jurídica --}}
                <div class="wf-grid" x-show="isCompany" x-cloak>
                    @if ($has('cnpj'))
                        <div class="wf-field">
                            <label for="cnpj">{{ __('theme.cnpj') }} <span class="wf-req">*</span></label>
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
                            {{-- Not mandatory, but not simply blank either: a company either
                                 states its IE or declares itself exempt. Ticking Isento
                                 writes the word the invoice has to carry. --}}
                            <input id="state_registration" type="text" class="wf-input"
                                wire:model="properties.state_registration" placeholder="Inscrição Estadual"
                                x-bind:disabled="exempt">
                            @error('properties.state_registration') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('state_registration_exempt'))
                        <div class="wf-field" style="justify-content:flex-end">
                            <label class="wf-check">
                                <input type="checkbox" wire:model="properties.state_registration_exempt"
                                    x-on:change="setExempt($event.target.checked)">
                                <span>Isento de Inscrição Estadual</span>
                            </label>
                            @error('properties.state_registration_exempt') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if ($has('municipal_registration'))
                        <div class="wf-field">
                            <label for="municipal_registration">Inscrição Municipal</label>
                            <input id="municipal_registration" type="text" class="wf-input"
                                wire:model="properties.municipal_registration" placeholder="Inscrição Municipal">
                            @error('properties.municipal_registration') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ─────────────── Account Security ───────────────
             The reference portal offers a generator and a live strength bar here. Both run
             entirely in Alpine: the score is only a hint for the person filling the form,
             and the rules that actually decide whether a password is accepted stay
             server-side in core's validation, where they cannot be bypassed.

             Generated characters come from crypto.getRandomValues rather than Math.random,
             and after writing to the inputs an `input` event is dispatched so Livewire's
             binding sees the new value — assigning `.value` alone would leave the component
             holding an empty password and the form would fail validation. --}}
        <div class="wf-section">{{ __('theme.account_security') }}</div>
        <div class="wf-grid" x-data="{
            score: 0,
            reveal: false,
            get label() {
                return [
                    @js(__('theme.password_strength_enter')), @js(__('theme.password_weak')),
                    @js(__('theme.password_moderate')), @js(__('theme.password_moderate')),
                    @js(__('theme.password_strong')),
                ][this.score];
            },
            get colour() {
                return ['#c9302c', '#c9302c', '#ec971f', '#5bc0de', '#3c9763'][this.score];
            },
            rate(value) {
                if (!value) return this.score = 0;
                let s = 0;
                if (value.length >= 8) s++;
                if (value.length >= 12) s++;
                if (/[a-z]/.test(value) && /[A-Z]/.test(value)) s++;
                if (/[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value)) s++;
                this.score = Math.min(s, 4);
            },
            generate() {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
                const bytes = new Uint32Array(16);
                crypto.getRandomValues(bytes);
                const pw = Array.from(bytes, b => chars[b % chars.length]).join('');

                for (const ref of [$refs.pw, $refs.pw2]) {
                    ref.value = pw;
                    ref.dispatchEvent(new Event('input', { bubbles: true }));
                }

                this.reveal = true;
                this.rate(pw);
            },
        }">
            <div class="wf-field">
                <label for="password">{{ __('general.input.password') }}<span class="wf-req">*</span></label>
                <input id="password" x-ref="pw" :type="reveal ? 'text' : 'password'" class="wf-input"
                    wire:model="password" x-on:input="rate($event.target.value)"
                    placeholder="{{ __('general.input.password_placeholder') }}" required>
                @error('password') <span class="wf-error">{{ $message }}</span> @enderror

                <div class="wf-pw">
                    <div class="wf-pw-track">
                        <div class="wf-pw-fill" :style="`width:${score * 25}%;background:${colour}`"></div>
                    </div>
                    <span class="wf-pw-label">{{ __('theme.password_strength') }}: <span x-text="label"></span></span>
                </div>
            </div>

            <div class="wf-field">
                <label for="password_confirmation">{{ __('general.input.password_confirmation') }}<span class="wf-req">*</span></label>
                <input id="password_confirmation" x-ref="pw2" :type="reveal ? 'text' : 'password'" class="wf-input"
                    wire:model="password_confirmation"
                    placeholder="{{ __('general.input.password_confirmation_placeholder') }}" required>

                <div class="wf-actions" style="margin-top:.5rem">
                    <button type="button" class="wf-btn wf-btn--sm wf-btn--ghost" x-on:click="generate()">
                        {{ __('theme.generate_password') }}
                    </button>
                    <button type="button" class="wf-btn wf-btn--sm wf-btn--ghost" x-on:click="reveal = !reveal">
                        <span x-text="reveal ? @js(__('theme.password_hide')) : @js(__('theme.password_show'))"></span>
                    </button>
                </div>
            </div>
        </div>

        <div style="margin-top:1.25rem">
            <x-captcha :form="'register'" />
        </div>

        {{-- Terms sit in their own outlined panel, as on the reference, so the one
             checkbox that blocks submission is not lost among the fields. --}}
        @if (config('settings.tos'))
            <div class="wf-panel wf-panel--tos">
                <div class="wf-panel-heading">
                    <span>&#9888; {{ __('theme.terms_of_service') }}</span>
                </div>
                <div class="wf-panel-body">
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
            </div>
        @endif

        <div class="wf-actions wf-actions--center">
            <button type="submit" class="wf-btn wf-btn--lg">{{ __('auth.sign_up') }}</button>
        </div>

            <div class="wf-alt">
                {{ __('auth.already_have_account') }}
                <a href="{{ route('login') }}" wire:navigate>{{ __('auth.sign_in') }}</a>
            </div>
        </form>
        </div>
    </div>
</div>
