{{-- Database Status, to the reference screenshot: the two buttons, then every table in
     two columns — Name, Rows, Size. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-db">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab" wire:click="optimise"
                wire:confirm="Optimise every table? Busy tables are rebuilt — run this off-peak."
                wire:loading.attr="disabled">Optimise Tables</button>
            <span class="ao-mu-tab ao-tx-tab-dead"
                title="Backups run on the host via scripts/backup on a schedule — a PHP request would time out half-written">
                Download Database Backup
            </span>
        </div>

        <p class="ao-db-line">{{ number_format($count) }} tables,
            {{ number_format($totalSize / 1024 / 1024, 1) }} MB in total.</p>

        <div class="ao-db-cols">
            @foreach ($columns as $tables)
                <table class="ao-mu-grid">
                    <thead>
                        <tr><th>Name</th><th>Rows</th><th>Size</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr>
                                <td class="ao-mu-left">{{ $table->name }}</td>
                                <td>{{ number_format((int) $table->row_count) }}</td>
                                <td>{{ number_format(max(16, (int) $table->size / 1024)) }} KB</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
