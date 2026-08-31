<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Client Activity</x-slot>
        <div class="ao-wg-cols">
            <div class="ao-wg-icostat">
                <span class="ao-wg-ic" style="color: #f0ad4e">&#128100;</span>
                <span>Active Clients<br><b class="ao-wg-orange">{{ number_format($active) }}</b> Active</span>
            </div>
            <div class="ao-wg-icostat">
                <span class="ao-wg-ic" style="color: #5cb85c">&#9786;</span>
                <span>Users Online<br><b class="ao-wg-green">{{ number_format($online) }}</b> Last Hour</span>
            </div>
        </div>

        <ul class="ao-wg-tickets">
            @forelse ($recent as $session)
                <li>
                    <a href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $session->user_id]) }}">
                        {{ trim(($session->user->first_name ?? '') . ' ' . ($session->user->last_name ?? '')) ?: ($session->user->email ?? '—') }}
                    </a>
                    <i>{{ \Carbon\Carbon::parse($session->last_activity)->diffForHumans(short: true) }}</i>
                    <span class="ao-wg-ip">{{ $session->ip_address }}</span>
                </li>
            @empty
                <li class="ao-wg-empty">No client sign-ins yet</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
