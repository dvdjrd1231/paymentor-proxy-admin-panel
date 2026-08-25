{{--
    The WHMCS Overview panel: measures down the side, periods across the top.

    Income is a pre-formatted string rather than a number because a store selling in more
    than one currency has no single total — see Money::formatTotals().
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
    <x-filament::section
        icon="heroicon-o-presentation-chart-line"
        heading="At a glance"
        :description="$activeServices . ' active ' . str('service')->plural($activeServices) . ' · ' . $outstanding . ' outstanding'"
    >
        <div class="ao-panel">
            <table class="ao-glance">
                <thead>
                    <tr>
                        <th></th>
                        @foreach ($periods as $period)
                            <th>{{ $period }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr @class(['ao-glance-income' => $row['money']])>
                            <th scope="row">{{ $row['label'] }}</th>
                            @foreach ($periods as $period)
                                @php($value = $row['values'][$period])
                                <td>
                                    <span @class([
                                        'ao-glance-value',
                                        'ao-glance-zero' => !$row['money'] && $value === 0,
                                    ])>{{ $row['money'] ? $value : number_format($value) }}</span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
