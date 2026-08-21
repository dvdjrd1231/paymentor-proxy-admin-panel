{{--
    Notification preferences in the reference portal's panel/table styling. The push
    subscription Alpine component and every wire: binding are the core component's.
--}}
<div class="wf-page">
    <div class="wf-layout">
        <x-profile-rail active="profile" />
        <div>
            <div class="wf-pagehead">
                <h1>{{ __('navigation.notifications') }}</h1>
            </div>

    @if ($this->supportsPush())
        <div class="wf-panel" x-data="pushNotifications">
            <div class="wf-panel-heading">{{ __('account.push_notifications') }}</div>
            <div class="wf-panel-body">
                <p class="wf-muted">{{ __('account.push_notifications_description') }}</p>
                <x-button.primary type="button" @click="subscribe"
                    x-bind:disabled="subscriptionStatus !== 'not_subscribed'">
                    {{ __('account.enable_push_notifications') }}
                </x-button.primary>
                <div x-show="subscriptionStatus !== 'unknown'">
                    <template x-if="subscriptionStatus === 'not_supported'">
                        <p class="wf-error">{{ __('account.push_status.not_supported') }}</p>
                    </template>
                    <template x-if="subscriptionStatus === 'denied'">
                        <p class="wf-error">{{ __('account.push_status.denied') }}</p>
                    </template>
                    <template x-if="subscriptionStatus === 'subscribed'">
                        <p class="wf-muted">{{ __('account.push_status.subscribed') }}</p>
                    </template>
                </div>
            </div>
        </div>
        @script
            <script>
                Alpine.data('pushNotifications', () => ({
                    subscriptionStatus: 'unknown',

                    init() {
                        if ('serviceWorker' in navigator && 'PushManager' in window) {
                            navigator.serviceWorker.ready.then((registration) => {
                                registration.pushManager.getSubscription().then((subscription) => {
                                    if (subscription) {
                                        this.subscriptionStatus = 'subscribed';
                                    } else {
                                        this.subscriptionStatus = Notification.permission === 'denied' ? 'denied' : 'not_subscribed';
                                    }
                                });
                            });
                        } else {
                            this.subscriptionStatus = 'not_subscribed';
                        }
                    },

                    subscribe() {
                        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                            this.subscriptionStatus = 'not_supported';
                            return;
                        }

                        navigator.serviceWorker.ready.then((registration) => {
                            registration.pushManager.getSubscription().then((subscription) => {
                                if (subscription) {
                                    @this.call('storePushSubscription', JSON.stringify(subscription));
                                    this.subscriptionStatus = 'subscribed';
                                    return;
                                }

                                registration.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: urlBase64ToUint8Array('{{ config('settings.vapid_public_key') }}')
                                }).then((newSubscription) => {
                                    @this.call('storePushSubscription', JSON.stringify(newSubscription));
                                    this.subscriptionStatus = 'subscribed';
                                }).catch((e) => {
                                    if (Notification.permission === 'denied') {
                                        this.subscriptionStatus = 'denied';
                                    } else {
                                        console.error('Failed to subscribe the user: ', e);
                                        this.subscriptionStatus = 'not_subscribed';
                                    }
                                });
                            });
                        });
                    }
                }));

                function urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);
                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray;
                }
            </script>
        @endscript
    @endif

    <div class="wf-panel">
        <div class="wf-panel-heading">{{ __('account.notification') }}</div>
        <div class="wf-panel-body">
            <p class="wf-muted">{{ __('account.notifications_description') }}</p>
        </div>
        <div class="wf-table-wrap">
            <table class="wf-table">
                <thead>
                    <tr>
                        <th>{{ __('account.notification') }}</th>
                        <th style="text-align:center">{{ __('account.email_notifications') }}</th>
                        <th style="text-align:center">{{ __('account.in_app_notifications') }}</th>
                    </tr>
                </thead>
                <tbody x-data="{ preferences: $wire.entangle('preferences') }">
                    @foreach ($this->notifications as $notification)
                        <tr>
                            <td>{{ $notification->name }}</td>
                            <td style="text-align:center">
                                <x-form.toggle :disabled="!$notification->mail_controllable"
                                    wire:model.defer="preferences.{{ $notification->key }}.mail_enabled" />
                            </td>
                            <td style="text-align:center">
                                <x-form.toggle :disabled="!$notification->in_app_controllable"
                                    wire:model.defer="preferences.{{ $notification->key }}.in_app_enabled" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="wf-panel-footer">
            <x-button.primary wire:click="savePreferences" wire:loading.attr="disabled">
                {{ __('general.save') }}
            </x-button.primary>
        </div>
    </div>
        </div>
    </div>
</div>
