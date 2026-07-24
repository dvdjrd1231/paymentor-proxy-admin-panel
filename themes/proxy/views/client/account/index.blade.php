{{--
    Account → Personal Details, reframed into a Six panel. The form components and
    <x-form.properties> (which now also renders the Brazilian tax fields and the
    optional Telegram Chat ID) are kept exactly, so bindings/validation are intact.
--}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.personal_details') }}</h1>
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('navigation.personal_details') }}</div>
        <div class="wf-panel-body">
            <div class="grid md:grid-cols-2 gap-3">
                <x-form.input name="first_name" type="text" :label="__('general.input.first_name')"
                    :placeholder="__('general.input.first_name_placeholder')" wire:model="first_name" required dirty />
                <x-form.input name="last_name" type="text" :label="__('general.input.last_name')"
                    :placeholder="__('general.input.last_name_placeholder')" wire:model="last_name" required dirty />

                <x-form.input name="email" type="email" :label="__('general.input.email')"
                    :placeholder="__('general.input.email_placeholder')" required wire:model="email" dirty />

                <x-form.properties :custom_properties="$custom_properties" :properties="$properties" dirty />
            </div>

            <button type="button" wire:click="submit" class="wf-btn wf-btn--block" style="margin-top:1rem">
                {{ __('general.update') }}
            </button>
        </div>
    </div>
</div>
