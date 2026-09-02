{{--
    Payment Gateways, to issue #45's newest reference: the info banner, then each
    activated gateway as its own bordered row — name at the left, the quick actions
    (Enable/Disable and the edit icon) at the right. The reference's drag handle is not
    reproduced: Paymenter's extensions table has no sort column, so a handle would drag
    rows nowhere.
--}}
<x-filament-panels::page>
    {{-- Scoped here rather than in styles.blade.php deliberately: that sheet is being
         reworked in a parallel branch right now, and these rules belong to this page. --}}
    <style>
        .ao-pgw-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 1.1rem;
            border: 1px solid var(--wa-panel-border, #ddd);
            border-radius: var(--wa-radius, 6px);
            background: #fff;
            margin-bottom: 0.7rem;
        }
        .ao-pgw-name { font-weight: 600; font-size: 1.05rem; flex: 1 1 auto; }
        .ao-pgw-actions { display: inline-flex; align-items: center; gap: 0.5rem; }
        .ao-pgw-banner-btn { margin-left: auto; white-space: nowrap; }
        .ao-mu .ao-ec-banner { justify-content: space-between; margin-bottom: 1rem; }
    </style>
    <div class="ao-mu">
        @if ($extensionsUrl)
            <div class="ao-ec-banner">
                <span>Looking to activate a new payment gateway? Visit <b>Extensions</b> for the full list of gateway modules.</span>
                <a class="ao-pg-btn ao-pgw-banner-btn" href="{{ $extensionsUrl }}">&rarr; Visit Extensions</a>
            </div>
        @endif

        @forelse ($gateways as $gateway)
            @php $edit = $canEdit($gateway); @endphp
            <div class="ao-pgw-row">
                <span class="ao-pgw-name">{{ $gateway->name }}</span>
                <span class="ao-mu-status {{ $gateway->enabled ? 'ao-mu-st-active' : 'ao-mu-st-cancelled' }}">
                    {{ $gateway->enabled ? 'Active' : 'Disabled' }}
                </span>
                <span class="ao-pgw-actions">
                    @if ($edit)
                        @if ($gateway->enabled)
                            <button type="button" class="ao-pg-btn" wire:click="confirm({{ $gateway->id }}, false)">Disable</button>
                        @else
                            <button type="button" class="ao-pg-btn" wire:click="confirm({{ $gateway->id }}, true)">Enable</button>
                        @endif
                        <a href="{{ $edit }}" title="Edit gateway">
                            <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                        </a>
                    @endif
                </span>
            </div>
        @empty
            <p class="ao-gs-empty">No gateway modules are installed.</p>
        @endforelse

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
