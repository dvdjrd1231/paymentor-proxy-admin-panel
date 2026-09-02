{{--
    System Settings, to issue #34 and #40: the reference's landing grid over Paymenter's
    real screens — a card per area, not a bare pill, each with an icon and the one line
    that says what is actually behind it.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-rp">
        <p class="ao-rp-intro">
            Every settings area of the store, organised the reference's way — each card opens
            the screen that owns it.
        </p>

        @foreach ($sections as $category => $tiles)
            <h3 class="ao-rp-cat">{{ $category }}</h3>
            <div class="ao-ss-grid">
                @foreach ($tiles as [$label, $href, $icon, $description])
                    <a class="ao-ss-card" href="{{ $href }}">
                        <span class="ao-ss-card-ic"><x-filament::icon :icon="$icon" /></span>
                        <span class="ao-ss-card-body">
                            <span class="ao-ss-card-title">{{ $label }}</span>
                            <span class="ao-ss-card-desc">{{ $description }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
