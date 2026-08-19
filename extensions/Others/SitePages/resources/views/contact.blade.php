{{-- Contact Us — routes people to the ticket system rather than an unmonitored inbox,
     so every request is tracked and answerable. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('sitepages.contact_us') }}</h1>
        <span>{{ __('sitepages.contact_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('sitepages.contact_us') }}
    </div>

    <div class="wf-form-narrow">
        <div class="wf-panel">
            <div class="wf-panel-body">
                <p>{{ __('sitepages.contact_intro') }}</p>

                @if (config('settings.company_email'))
                    <p class="wf-list-sub" style="margin-top:.75rem">
                        {{ config('settings.company_email') }}
                    </p>
                @endif

                <div class="wf-actions wf-actions--center" style="margin-top:1.25rem">
                    <a class="wf-btn" href="{{ route('tickets.create') }}" wire:navigate>
                        {{ __('sitepages.open_ticket') }}
                    </a>
                    @if (Route::has('knowledgebase.index'))
                        <a class="wf-btn wf-btn--ghost" href="{{ route('knowledgebase.index') }}" wire:navigate>
                            {{ __('sitepages.browse_kb') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
