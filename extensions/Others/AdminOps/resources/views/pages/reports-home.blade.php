{{--
    Reports, to the reference screenshot: the intro line, then each category's centred row
    of pills — General cyan, Exports dark, the rest white. Real pills open their report or
    download their CSV; the rest are disabled and say why.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-rp">
        <p class="ao-rp-intro">
            The reports below provide both data analysis and in many cases graphical insights
            into the data held in the system.
        </p>

        @foreach ($categories as $category => $reports)
            <h3 class="ao-rp-cat">{{ $category }}</h3>
            <div class="ao-rp-pills">
                @foreach ($reports as $key => $report)
                    @php [$label, $real] = $report; @endphp
                    @if (!$real)
                        <span class="ao-rp-pill ao-rp-{{ strtolower($category) }} ao-rp-dead" title="{{ $report[2] }}">{{ $label }}</span>
                    @elseif ($category === 'Exports')
                        <button type="button" class="ao-rp-pill ao-rp-exports"
                            wire:click="export('{{ str_replace('export-', '', $key) }}')">{{ $label }}</button>
                    @else
                        <a class="ao-rp-pill ao-rp-{{ strtolower($category) }}"
                            href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ReportView::getUrl(['key' => $key]) }}">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
