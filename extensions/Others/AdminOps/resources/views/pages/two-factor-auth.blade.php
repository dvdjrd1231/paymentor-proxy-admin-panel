{{-- The reference's Two-Factor Authentication page, reduced to the part with work behind it. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        <p class="ao-ax-note">
            {{ $onCount }} of {{ $staff->count() }} {{ str('administrator')->plural($staff->count()) }}
            {{ $onCount === 1 ? 'has' : 'have' }} two-factor authentication switched on.
        </p>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Administrator</th>
                    <th>Status</th>
                    <th>Last Sign-In</th>
                    <th>Known Addresses</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $person)
                    @php $seen = $lastSeen[$person->id] ?? null; @endphp
                    <tr>
                        <td class="ao-mu-left">
                            {{ trim($person->first_name . ' ' . $person->last_name) ?: $person->email }}
                            @if ((int) $person->id === $selfId)
                                <span class="ao-tag ao-cu-owner">YOU</span>
                            @endif
                            <br><span class="ao-cpg-muted">{{ $person->email }}</span>
                        </td>
                        <td>
                            @if ($person->tfa_secret)
                                <span class="ao-tag ao-tag-success">On</span>
                            @else
                                <span class="ao-tag ao-tag-warning" title="This account signs in with a password alone">Off</span>
                            @endif
                        </td>
                        <td>{{ $seen?->seen ? \Carbon\Carbon::parse($seen->seen)->format('j M Y H:i') : 'Never' }}</td>
                        <td>
                            {{ $seen?->places ?? 0 }}
                            @if ($seen?->places)
                                <span class="ao-cpg-muted" title="Distinct addresses this account has signed in from">
                                    {{ str('address')->plural($seen->places) }}
                                </span>
                            @endif
                        </td>
                        <td class="ao-mu-actions">
                            @if ($person->tfa_secret)
                                <button type="button" class="ao-pg-btn"
                                    wire:click="$set('confirming', {{ $person->id }})">Reset</button>
                            @else
                                <span class="ao-cpg-muted" title="Nothing to reset — this account has no second factor set">Reset</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <p class="ao-cp-note">
            The reference also lists the 2FA methods to offer and can require them of every
            administrator. Paymenter has one method, a one-time code app, and no setting that
            forces it — each person switches it on from their own Security page. Rather than
            draw a switch that would not hold, this page shows who has done so, so you can ask
            the ones who have not.
        </p>
    </div>

    @if ($confirming)
        <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
            <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                <div class="ao-mud-head">
                    Are you sure?
                    <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                </div>
                <div class="ao-mud-text">
                    <p>Reset two-factor authentication for this administrator?</p>
                    <p>They will be able to sign in with their password alone until they set it
                        up again, so do this only when they have genuinely lost their device —
                        and tell them to turn it back on.</p>
                </div>
                <div class="ao-mud-foot ao-mud-foot-only-right">
                    <span class="ao-mud-foot-right">
                        <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                        <button type="button" class="ao-mud-delete" wire:click="resetTfa">OK</button>
                    </span>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
