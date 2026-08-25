{{--
    Staff on the desk, and whether anyone is using the store. Two short lists side by side;
    on a narrow panel they stack.
--}}
@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    <x-filament::section icon="heroicon-o-users" heading="Who is around">
        <div class="ao-panel ao-around">
            <div>
                <p class="ao-field-label">Staff online</p>
                @forelse ($staff as $member)
                    <p class="ao-field-value">
                        {{ $member->name }}
                        @if ($member->last_activity)
                            <span class="ao-queue-note">{{ $member->last_activity->diffForHumans() }}</span>
                        @endif
                    </p>
                @empty
                    {{-- You are reading this page, so you are online: an empty list means
                         nobody *else* is, which is what it should say. --}}
                    <p class="ao-field-value ao-glance-zero">Nobody else is signed in</p>
                @endforelse
            </div>

            <div>
                <p class="ao-field-label">Client activity</p>
                <p class="ao-field-value">
                    {{ number_format($activeClients) }} active
                    <span class="ao-queue-note">customers with a running service</span>
                </p>
                <p class="ao-field-value">
                    {{ number_format($clientsOnline) }} online
                    <span class="ao-queue-note">signed in within the last hour</span>
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
