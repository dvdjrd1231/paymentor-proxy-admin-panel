{{-- System Settings, to issue #34: the reference's landing grid over Paymenter's real screens. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-rp">
        <p class="ao-rp-intro">
            Every settings area of the store, organised the reference's way — each tile opens
            the screen that owns it.
        </p>

        @foreach ($sections as $category => $tiles)
            <h3 class="ao-rp-cat">{{ $category }}</h3>
            <div class="ao-rp-pills">
                @foreach ($tiles as [$label, $href])
                    <a class="ao-rp-pill" href="{{ $href }}">{{ $label }}</a>
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
