{{-- PHP Info: the settings that matter, then the loaded extensions. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-phi">
        <table class="ao-mu-grid ao-phi-facts">
            <tbody>
                @foreach ($facts as $label => $value)
                    <tr>
                        <td class="ao-mu-left">{{ $label }}</td>
                        <td class="ao-mu-left">{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h4 class="ao-ano-heading">Loaded Extensions ({{ count($extensions) }})</h4>
        <p class="ao-phi-ext">{{ implode(', ', $extensions) }}</p>
    </div>
</x-filament-panels::page>
