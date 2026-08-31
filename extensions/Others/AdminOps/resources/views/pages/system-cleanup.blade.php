{{-- System Cleanup: the caches, and the logs that grow forever. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-sc">
        <h4 class="ao-ano-heading">Caches</h4>
        <div class="ao-st-bulk">
            <button type="button" wire:click="clearViews">Clear Compiled Views</button>
            <button type="button" wire:click="clearCache">Clear Application Cache</button>
        </div>

        <h4 class="ao-ano-heading">Log Pruning</h4>
        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Log</th>
                    <th>Rows</th>
                    <th>Older Than</th>
                    <th>Prunable</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="ao-mu-left">{{ $log['table'] }}</td>
                        <td>{{ number_format($log['total']) }}</td>
                        <td>{{ $log['days'] }} days</td>
                        <td>{{ number_format($log['stale']) }}</td>
                        <td class="ao-mu-actions">
                            <button type="button" class="ao-cq-addline" wire:click="pruneLog('{{ $log['table'] }}')"
                                wire:confirm="Delete {{ number_format($log['stale']) }} rows older than {{ $log['days'] }} days from {{ $log['table'] }}?"
                                @disabled(!$log['stale'])>Prune</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ao-mu-none">No prunable logs on this install</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
