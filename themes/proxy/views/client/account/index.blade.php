{{--
    Account Details, in the reference portal's shape: the Account rail on the left, and the
    form in two columns — contact details on one side, the billing address on the other.

    The grid is wf-* rather than Tailwind's `grid md:grid-cols-2`, which is what was here
    before: this theme does not load the Tailwind bundle, so those class names carried no
    styling at all and every field stacked full width.

    The Paymenter form components and <x-form.properties> (which renders the address custom
    properties, the Brazilian tax fields and the optional Telegram Chat ID) are kept exactly
    as they were, so bindings and validation are untouched.
--}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('theme.account_details') }}</h1>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('dashboard') }}" wire:navigate>{{ __('theme.client_area') }}</a>
        <span>/</span>{{ __('theme.account_details') }}
    </div>

    <div class="wf-layout">
        <x-account-rail active="details" />

        <div>
            <div class="wf-panel">
                <div class="wf-panel-body">
                    <div class="wf-grid">
                        <x-form.input name="first_name" type="text" :label="__('general.input.first_name')"
                            :placeholder="__('general.input.first_name_placeholder')" wire:model="first_name" required dirty />
                        <x-form.input name="last_name" type="text" :label="__('general.input.last_name')"
                            :placeholder="__('general.input.last_name_placeholder')" wire:model="last_name" required dirty />

                        <x-form.input name="email" type="email" :label="__('general.input.email')"
                            :placeholder="__('general.input.email_placeholder')" required wire:model="email" dirty />

                        {{-- Company, phone and the full billing address all arrive here as
                             custom properties, so they flow into the same two columns
                             instead of being listed separately. --}}
                        <x-form.properties :custom_properties="$custom_properties" :properties="$properties" dirty />
                    </div>

                    <div class="wf-actions">
                        <button type="button" wire:click="submit" class="wf-btn" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submit">{{ __('general.update') }}</span>
                            <span wire:loading wire:target="submit">…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
