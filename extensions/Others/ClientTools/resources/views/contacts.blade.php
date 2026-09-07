{{--
    Contacts, to the reference portal (Leandro, 2026-09-07, screenshot of
    my.noxproxy.com/account/contacts): a Choose Contact band over one always-visible
    two-column form, then Email Preferences, closed by Save Changes / Cancel.

    It used to be a list of contact cards with a form that appeared on demand. That is a
    different interaction — the reference never shows a list, it shows one contact at a
    time and switches with the selector.
--}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.contacts') }}</h1>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
        <span>/</span><a href="{{ route('account') }}" wire:navigate>{{ __('theme.account_details') }}</a>
        <span>/</span>{{ __('clienttools.contacts') }}
    </div>

    <div class="wf-layout">
        <x-account-rail active="contacts" />
        <div>
            {{-- The reference's tinted Choose Contact band. Go is a real submit rather
                 than a live select: the reference has the button, and loading a contact
                 the moment the select changes would discard anything half-typed without
                 asking. --}}
            <form class="wf-choose" wire:submit.prevent="choose">
                <label for="ct_choose">{{ __('clienttools.choose_contact') }}</label>
                <select id="ct_choose" class="wf-select" wire:model="chosen">
                    <option value="">{{ __('clienttools.contact_new') }}</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="wf-btn">{{ __('clienttools.go') }}</button>
            </form>

            <form wire:submit.prevent="save">
                <div class="wf-contact-grid">
                    <div class="wf-field">
                        <label for="c_first">{{ __('clienttools.contact_first_name') }}</label>
                        <input id="c_first" type="text" class="wf-input" wire:model="form.first_name">
                        @error('form.first_name') <span class="wf-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="wf-field">
                        <label for="c_addr1">{{ __('clienttools.address_1') }}</label>
                        <input id="c_addr1" type="text" class="wf-input" wire:model="form.address">
                    </div>

                    <div class="wf-field">
                        <label for="c_last">{{ __('clienttools.contact_last_name') }}</label>
                        <input id="c_last" type="text" class="wf-input" wire:model="form.last_name">
                        @error('form.last_name') <span class="wf-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="wf-field">
                        <label for="c_addr2">{{ __('clienttools.address_2') }}</label>
                        <input id="c_addr2" type="text" class="wf-input" wire:model="form.address2">
                    </div>

                    <div class="wf-field">
                        <label for="c_company">{{ __('clienttools.contact_company_name') }}</label>
                        <input id="c_company" type="text" class="wf-input" wire:model="form.company_name">
                    </div>
                    <div class="wf-field">
                        <label for="c_city">{{ __('clienttools.contact_city') }}</label>
                        <input id="c_city" type="text" class="wf-input" wire:model="form.city">
                    </div>

                    <div class="wf-field">
                        <label for="c_email">{{ __('clienttools.contact_email') }}</label>
                        <input id="c_email" type="email" class="wf-input" wire:model="form.email">
                        @error('form.email') <span class="wf-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="wf-field">
                        <label for="c_state">{{ __('clienttools.contact_state') }}</label>
                        <input id="c_state" type="text" class="wf-input" wire:model="form.state">
                    </div>

                    <div class="wf-field">
                        <label for="c_phone">{{ __('clienttools.contact_phone') }}</label>
                        <input id="c_phone" type="text" class="wf-input" wire:model="form.phone"
                               placeholder="+1 201-555-0123">
                    </div>
                    <div class="wf-field">
                        <label for="c_zip">{{ __('clienttools.contact_zip') }}</label>
                        <input id="c_zip" type="text" class="wf-input" wire:model="form.zip">
                    </div>

                    {{-- Country sits alone in the right column, as the reference has it:
                         the left column has one field fewer. --}}
                    <div class="wf-field wf-contact-spacer"></div>
                    {{-- A real list rather than a free-text box, as the reference has it.
                         The stored value is the country's name, which is what rows written
                         before this screen existed already hold; an unrecognised legacy
                         value is kept as its own option so editing a contact never
                         silently blanks their country. --}}
                    <div class="wf-field">
                        <label for="c_country">{{ __('clienttools.contact_country') }}</label>
                        <select id="c_country" class="wf-select" wire:model="form.country">
                            <option value="">{{ __('clienttools.contact_country_none') }}</option>
                            @if ($form['country'] !== '' && !in_array($form['country'], $countries, true))
                                <option value="{{ $form['country'] }}">{{ $form['country'] }}</option>
                            @endif
                            @foreach ($countries as $country)
                                <option value="{{ $country }}">{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <h2 class="wf-contact-heading">{{ __('clienttools.email_preferences') }}</h2>
                <div class="wf-contact-prefs">
                    @foreach ($emailPreferenceKeys as $key)
                        <label class="wf-check">
                            <input type="checkbox" value="{{ $key }}" wire:model="form.email_preferences">
                            <span>{{ __('clienttools.email_pref_' . $key) }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- Sub-account access is ours, not the reference's — it is what puts a
                     contact on the User Management page, so it stays, below the fold of
                     the fields the reference does show. --}}
                <h2 class="wf-contact-heading">{{ __('clienttools.contact_access') }}</h2>
                <div class="wf-contact-prefs">
                    <label class="wf-check">
                        <input type="checkbox" wire:model.live="form.is_sub_account">
                        <span>{{ __('clienttools.contact_is_sub_account') }}</span>
                    </label>

                    @if ($form['is_sub_account'])
                        @foreach ($permissionKeys as $key)
                            <label class="wf-check">
                                <input type="checkbox" value="{{ $key }}" wire:model="form.permissions">
                                <span>{{ __('clienttools.perm_' . $key) }}</span>
                            </label>
                        @endforeach
                    @endif
                </div>

                <div class="wf-contact-actions">
                    <button type="submit" class="wf-btn">{{ __('clienttools.contact_save') }}</button>
                    <button type="button" class="wf-btn wf-btn--ghost" wire:click="cancel">
                        {{ __('clienttools.cancel') }}
                    </button>
                    @if ($editing)
                        <button type="button" class="wf-btn wf-btn--danger"
                                wire:click="delete({{ $editing }})"
                                wire:confirm="{{ __('clienttools.contact_delete_confirm') }}">
                            {{ __('clienttools.delete') }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
