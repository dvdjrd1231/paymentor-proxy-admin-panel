{{-- Contacts — the people listed on this account, and the form to add or edit one. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.contacts') }}</h1>
        <span>{{ __('clienttools.contacts_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.contacts') }}
    </div>

    @if ($showForm)
        <div class="wf-panel">
            <div class="wf-panel-heading">
                <span>{{ $editing ? __('clienttools.contact_edit') : __('clienttools.contact_new') }}</span>
            </div>
            <div class="wf-panel-body">
                <form wire:submit.prevent="save">
                    <div class="wf-section">{{ __('theme.personal_information') }}</div>
                    <div class="wf-grid">
                        <div class="wf-field">
                            <label for="c_first">{{ __('general.input.first_name') }}<span class="wf-req">*</span></label>
                            <input id="c_first" type="text" class="wf-input" wire:model="form.first_name">
                            @error('form.first_name') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="wf-field">
                            <label for="c_last">{{ __('general.input.last_name') }}<span class="wf-req">*</span></label>
                            <input id="c_last" type="text" class="wf-input" wire:model="form.last_name">
                            @error('form.last_name') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="wf-field">
                            <label for="c_email">{{ __('general.input.email') }}<span class="wf-req">*</span></label>
                            <input id="c_email" type="email" class="wf-input" wire:model="form.email">
                            @error('form.email') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="wf-field">
                            <label for="c_phone">{{ __('theme.phone_number') }}</label>
                            <input id="c_phone" type="text" class="wf-input" wire:model="form.phone">
                            @error('form.phone') <span class="wf-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="wf-section">{{ __('theme.billing_address') }}</div>
                    <div class="wf-grid">
                        <div class="wf-field wf-col-2">
                            <label for="c_company">{{ __('theme.company_name') }}</label>
                            <input id="c_company" type="text" class="wf-input" wire:model="form.company_name">
                        </div>
                        <div class="wf-field wf-col-2">
                            <label for="c_address">{{ __('theme.street_address') }}</label>
                            <input id="c_address" type="text" class="wf-input" wire:model="form.address">
                        </div>
                        <div class="wf-field">
                            <label for="c_city">{{ __('theme.city') }}</label>
                            <input id="c_city" type="text" class="wf-input" wire:model="form.city">
                        </div>
                        <div class="wf-field">
                            <label for="c_state">{{ __('theme.state_region') }}</label>
                            <input id="c_state" type="text" class="wf-input" wire:model="form.state">
                        </div>
                        <div class="wf-field">
                            <label for="c_zip">{{ __('theme.postcode') }}</label>
                            <input id="c_zip" type="text" class="wf-input" wire:model="form.zip">
                        </div>
                        <div class="wf-field">
                            <label for="c_country">{{ __('theme.country') }}</label>
                            <input id="c_country" type="text" class="wf-input" wire:model="form.country">
                        </div>
                    </div>

                    {{-- Promoting a contact to a sub-account is what puts them on the User
                         Management page, so the permission list is revealed with the toggle. --}}
                    <div class="wf-section">{{ __('clienttools.contact_access') }}</div>
                    <div class="wf-panel-body" style="padding-left:0">
                        <label class="wf-check">
                            <input type="checkbox" wire:model.live="form.is_sub_account">
                            <span>{{ __('clienttools.contact_is_sub_account') }}</span>
                        </label>

                        @if ($form['is_sub_account'])
                            <div style="margin-top:.75rem">
                                @foreach ($permissionKeys as $key)
                                    <label class="wf-check" style="display:block">
                                        <input type="checkbox" value="{{ $key }}" wire:model="form.permissions">
                                        <span>{{ __('clienttools.perm_' . $key) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="wf-actions">
                        <button type="submit" class="wf-btn">{{ __('clienttools.contact_save') }}</button>
                        <button type="button" class="wf-btn wf-btn--ghost" wire:click="cancel">
                            {{ __('clienttools.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-contacts-book-2-fill /></span>{{ __('clienttools.contacts') }}</span>
            <button type="button" class="wf-btn wf-btn--sm" wire:click="newContact">
                + {{ __('clienttools.contact_new') }}
            </button>
        </div>

        @forelse ($contacts as $contact)
            <div class="wf-list-row">
                <div class="wf-row-main">
                    <div class="wf-list-title">
                        {{ $contact->name }}
                        @if ($contact->is_sub_account)
                            <span class="wf-label wf-label--success">{{ __('clienttools.contact_sub_account') }}</span>
                        @endif
                    </div>
                    <span class="wf-list-sub">
                        {{ $contact->email }}@if ($contact->phone) &middot; {{ $contact->phone }}@endif
                    </span>
                </div>
                <div class="wf-actions">
                    <button type="button" class="wf-btn wf-btn--sm" wire:click="edit({{ $contact->id }})">
                        {{ __('clienttools.edit') }}
                    </button>
                    <button type="button" class="wf-btn wf-btn--sm wf-btn--danger"
                            wire:click="delete({{ $contact->id }})"
                            wire:confirm="{{ __('clienttools.contact_delete_confirm') }}">
                        {{ __('clienttools.delete') }}
                    </button>
                </div>
            </div>
        @empty
            <div class="wf-empty">{{ __('clienttools.contacts_empty') }}</div>
        @endforelse
    </div>
</div>
