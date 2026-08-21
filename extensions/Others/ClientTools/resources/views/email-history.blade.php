{{-- Email History — every message the system has sent this customer, from the core
     `email_logs` table. Clicking a row expands the message body in place. --}}
<div class="wf-page">
    <div class="wf-title">
        <h1>{{ __('clienttools.email_history') }}</h1>
        <span>{{ __('clienttools.email_history_subtitle') }}</span>
    </div>
    <hr class="wf-title-rule">

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('clienttools.email_history') }}
    </div>

    <div class="wf-panel">
        <div class="wf-panel-heading">
            <span><span class="wf-head-icon"><x-ri-mail-line /></span>{{ __('clienttools.messages_sent') }}</span>
        </div>

        @if ($emails->isEmpty())
            <div class="wf-empty">{{ __('clienttools.email_history_empty') }}</div>
        @else
            <div class="wf-table-wrap">
                <table class="wf-table">
                    <thead>
                        <tr>
                            <th>{{ __('clienttools.subject') }}</th>
                            <th>{{ __('clienttools.sent_to') }}</th>
                            <th>{{ __('clienttools.status') }}</th>
                            <th style="text-align:end">{{ __('clienttools.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($emails as $email)
                            <tr wire:key="email-{{ $email->id }}">
                                <td>
                                    <button type="button" class="wf-row-link" wire:click="toggle({{ $email->id }})">
                                        {{ $email->subject }}
                                    </button>
                                </td>
                                <td>{{ $email->to }}</td>
                                <td>
                                    <span @class([
                                        'wf-label',
                                        'wf-label--success' => $email->status === 'sent',
                                        'wf-label--danger' => $email->status === 'failed',
                                        'wf-label--warning' => !in_array($email->status, ['sent', 'failed']),
                                    ])>{{ ucfirst($email->status) }}</span>
                                </td>
                                <td style="text-align:end">
                                    {{ \Carbon\Carbon::parse($email->sent_at ?? $email->created_at)->format('d/m/Y H:i') }}
                                </td>
                            </tr>

                            @if ($openEmail && $openEmail->id === $email->id)
                                <tr wire:key="email-body-{{ $email->id }}">
                                    <td colspan="4">
                                        {{-- The stored body is HTML and can carry text a
                                             customer supplied (a ticket reply quoted into a
                                             notification), so it is never interpolated into
                                             this page. `sandbox` with no tokens blocks
                                             scripts, forms and same-origin access, and Blade
                                             escapes the attribute, so the mail renders with
                                             its formatting but cannot execute anything. --}}
                                        <iframe sandbox="" title="{{ $openEmail->subject }}"
                                                srcdoc="{{ $openEmail->body }}"
                                                style="width:100%;min-height:22rem;border:1px solid var(--line,#e5e7eb);border-radius:.375rem;background:#fff"></iframe>
                                        @if ($openEmail->error)
                                            <div class="wf-alert wf-alert--notice" style="margin-top:.75rem">
                                                {{ $openEmail->error }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="wf-panel-foot">{{ $emails->links() }}</div>
        @endif
    </div>
</div>
