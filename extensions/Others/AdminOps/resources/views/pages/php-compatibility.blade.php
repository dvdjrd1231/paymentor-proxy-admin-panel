{{-- PHP Version Compatibility: version and extensions, against what Paymenter requires. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-phc">
        <div class="{{ $allGood ? 'ao-phc-ok' : 'ao-ni-error' }}">
            @if ($allGood)
                <b>&#10004; This PHP is fully compatible with Paymenter.</b>
            @else
                <span><b>Compatibility problems found</b><br>Fix the rows marked below.</span>
            @endif
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr><th>Requirement</th><th>Found</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($checks as [$requirement, $found, $ok])
                    <tr>
                        <td class="ao-mu-left">{{ $requirement }}</td>
                        <td>{{ $found }}</td>
                        <td><span class="{{ $ok ? 'ao-st-open' : 'ao-st-open ao-phc-bad' }}">{{ $ok ? 'OK' : 'Problem' }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
