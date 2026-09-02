{{--
    Payment Gateways, to issue #45: the navy list with the quick-access buttons —
    Enable, Disable, Edit — on every row. Credentials stay on the core edit form.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <table class="ao-mu-grid">
            <thead>
                <tr><th>Name</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($gateways as $gateway)
                    @php $edit = $canEdit($gateway); @endphp
                    <tr>
                        <td class="ao-mu-left"><b>{{ $gateway->name }}</b></td>
                        <td>
                            <span class="ao-mu-status {{ $gateway->enabled ? 'ao-mu-st-active' : 'ao-mu-st-cancelled' }}">
                                {{ $gateway->enabled ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="ao-mu-actions">
                            @if ($edit)
                                @if ($gateway->enabled)
                                    <button type="button" class="ao-pg-btn" wire:click="confirm({{ $gateway->id }}, false)">Disable</button>
                                @else
                                    <button type="button" class="ao-pg-btn" wire:click="confirm({{ $gateway->id }}, true)">Enable</button>
                                @endif
                                <a href="{{ $edit }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        @if ($confirmEnable)
                            <p>Enable this payment gateway?</p>
                            <p>It will be offered to customers at checkout again.</p>
                        @else
                            <p>Disable this payment gateway?</p>
                            <p>It stops being offered at checkout. Payments already made are untouched.</p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="run">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
