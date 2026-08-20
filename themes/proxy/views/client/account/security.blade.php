{{--
    Account → Security, reframed into Six-style panels. All Livewire bindings
    (logoutSession, changePassword, enableTwoFactor, disableTwoFactor) match the
    core component, including the confirmation store used for disabling 2FA.
--}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('navigation.security') }}</h1>
    </div>

    {{-- ── Sessions ───────────────────────────────────────────────────── --}}
    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('account.sessions') }}</div>
        <div class="wf-panel-body">
            <table class="wf-table">
                <tbody>
                    @foreach (Auth::user()->sessions as $session)
                        <tr>
                            <td>
                                <strong>{{ $session->ip_address }}</strong>
                                <span class="wf-muted"> — {{ $session->last_activity->diffForHumans() }}</span>
                                <div class="wf-muted">{{ $session->formatted_device }}</div>
                            </td>
                            <td style="text-align:right">
                                @if (!$session->is_current_device)
                                    <button type="button" class="wf-btn wf-btn--ghost"
                                        wire:click="logoutSession('{{ $session->id }}')">
                                        {{ __('account.logout_sessions') }}
                                    </button>
                                @else
                                    <em class="wf-muted">{{ __('account.current_device') }}</em>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Change password ────────────────────────────────────────────── --}}
    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('account.change_password') }}</div>
        <div class="wf-panel-body">
            <form wire:submit="changePassword">
                <x-form.input name="current_password" type="password" :label="__('account.input.current_password')"
                    :placeholder="__('account.input.current_password_placeholder')" wire:model="current_password" required />

                <div class="grid md:grid-cols-2 gap-3">
                    <x-form.input name="password" type="password" :label="__('account.input.new_password')"
                        :placeholder="__('account.input.new_password_placeholder')" wire:model="password" required />
                    <x-form.input name="password_confirmation" type="password" :label="__('account.input.confirm_password')"
                        :placeholder="__('account.input.confirm_password_placeholder')" wire:model="password_confirmation" required />
                </div>

                <div class="wf-actions" style="margin-top:1rem">
                    <button type="submit" class="wf-btn">{{ __('account.change_password') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Two-factor authentication ──────────────────────────────────── --}}
    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('account.two_factor_authentication') }}</div>
        <div class="wf-panel-body">
            @if ($twoFactorEnabled)
                <p>{{ __('account.two_factor_authentication_enabled') }}</p>
                <div class="wf-actions" style="margin-top:1rem">
                    <button type="button" class="wf-btn wf-btn--danger" x-on:click="$store.confirmation.confirm({
                            title: '{{ __('account.two_factor_authentication_disable') }}',
                            message: '{{ __('account.two_factor_authentication_disable_description') }}',
                            confirmText: '{{ __('account.confirm') }}',
                            cancelText: '{{ __('account.cancel') }}',
                            callback: () => $wire.disableTwoFactor()
                        })">
                        {{ __('account.two_factor_authentication_disable') }}
                    </button>
                </div>
            @else
                <p>{{ __('account.two_factor_authentication_description') }}</p>
                <div class="wf-actions" style="margin-top:1rem">
                    <button type="button" class="wf-btn" wire:click="enableTwoFactor">
                        {{ __('account.two_factor_authentication_enable') }}
                    </button>
                </div>

                @if ($showEnableTwoFactor)
                    <x-modal :title="__('account.two_factor_authentication_enable')" open="true">
                        <p>{{ __('account.two_factor_authentication_enable_description') }}</p>
                        <div style="display:flex;flex-direction:column;align-items:center;margin-top:1rem">
                            <img src="{{ $twoFactorData['image'] }}" alt="QR code" style="width:16rem;height:16rem" />
                            <p class="wf-muted" style="margin-top:.5rem;text-align:center">
                                {{ __('account.two_factor_authentication_secret') }}<br />{{ $twoFactorData['secret'] }}
                            </p>
                        </div>
                        <form wire:submit.prevent="enableTwoFactor" style="margin-top:1.5rem">
                            <x-form.input name="two_factor_code" type="text" :label="__('account.input.two_factor_code')"
                                :placeholder="__('account.input.two_factor_code_placeholder')" wire:model="twoFactorCode" required />
                            <div class="wf-actions" style="margin-top:1rem">
                                <button type="submit" class="wf-btn">
                                    {{ __('account.two_factor_authentication_enable') }}
                                </button>
                            </div>
                        </form>
                        <x-slot name="closeTrigger">
                            <button @click="document.location.reload()" class="text-primary-100">
                                <x-ri-close-fill class="size-6" />
                            </button>
                        </x-slot>
                    </x-modal>
                @endif
            @endif
        </div>
    </div>
</div>
