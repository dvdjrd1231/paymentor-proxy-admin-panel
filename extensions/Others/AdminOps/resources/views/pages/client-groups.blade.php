{{--
    Client Groups, to the reference's screenshot: the five-column grid and the Add Client
    Group form beneath it. Two of the three settings are stored but not yet enforced and
    say so — see the page class.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Group Colour</th>
                    <th>% Discount</th>
                    <th>Suspend/Terminate Exempt</th>
                    <th>Separate Invoices</th>
                    <th>Clients</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td class="ao-mu-left">
                            <a href="#" wire:click.prevent="edit({{ $group->id }})">{{ $group->name }}</a>
                        </td>
                        <td><span class="ao-cg-swatch" style="background: {{ $group->colour }}"></span> {{ $group->colour }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $group->discount_percent, 2), '0'), '.') ?: '0' }}%</td>
                        <td>{{ $group->suspend_exempt ? 'Yes' : 'No' }}</td>
                        <td>{{ $group->separate_invoices ? 'Yes' : 'No' }}</td>
                        <td>{{ $counts[$group->id] ?? 0 }}</td>
                        <td class="ao-mu-actions">
                            <a href="#" title="Edit group" wire:click.prevent="edit({{ $group->id }})">
                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                            </a>
                            <button type="button" class="ao-mo-delete" title="Delete group"
                                wire:click="$set('confirming', {{ $group->id }})">
                                <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon ao-mu-icon-red" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <h4 class="ao-ano-heading">{{ $editing ? 'Edit Client Group' : 'Add Client Group' }}</h4>
        <form class="ao-anc-card" wire:submit.prevent="save">
            <label class="ao-anc-row">
                <span>Group Name</span>
                <input type="text" class="ao-w-40" wire:model="name" required>
            </label>
            <label class="ao-anc-row">
                <span>Group Colour</span>
                <span class="ao-anc-field">
                    <input type="text" class="ao-w-25" wire:model="colour" placeholder="#ffffff">
                    <input type="color" wire:model="colour" aria-label="Pick a colour">
                </span>
            </label>
            <label class="ao-anc-row">
                <span>Group Discount %</span>
                <span class="ao-anc-field">
                    <input type="text" class="ao-w-25" wire:model="discount" inputmode="decimal" placeholder="0">
                    <i>Stored, but not applied yet — pricing has no hook for it here. It will
                        show on the group and change nothing until that exists.</i>
                </span>
            </label>
            <label class="ao-anc-row">
                <span>Exempt from Suspend &amp; Terminate</span>
                <span class="ao-anc-field">
                    <input type="checkbox" wire:model="suspendExempt">
                    <i>Enforced: the hourly overdue sweep skips services belonging to this group.</i>
                </span>
            </label>
            <label class="ao-anc-row">
                <span>Separate Invoices for Services</span>
                <span class="ao-anc-field">
                    <input type="checkbox" wire:model="separateInvoices">
                    <i>Stored, but not applied yet — invoice generation has no hook for it here.</i>
                </span>
            </label>
            <div class="ao-pr-center">
                <button type="submit" class="ao-find-go">Save Changes</button>
                @if ($editing)
                    <button type="button" class="ao-pg-btn" wire:click="cancel">Cancel</button>
                @endif
            </div>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Delete this client group?</p>
                        <p>Clients in it become ungrouped; nothing else about them changes.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="runDelete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
