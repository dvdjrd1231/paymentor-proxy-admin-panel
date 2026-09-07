{{--
    The reference's role editor: Name over the three-column permission matrix, with
    Check All / Uncheck All and the centred Save / Cancel pair. Create and edit are the
    same form, as they are in the reference.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <a class="ao-mu-tab"
                href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\AdminRoles::getUrl() }}">&laquo; Back to Role Groups</a>
        </div>

        <form class="ao-rg" wire:submit.prevent="save">
            <div class="ao-rg-name">
                <label class="ao-of-label" for="ao-rg-name">Name</label>
                <input id="ao-rg-name" type="text" wire:model="name" required
                    placeholder="e.g. Support Operator">
            </div>

            {{-- The reference's "All Permissions" is one master switch, not a checkbox
                 among the rest: core stores it as `*`, which outranks every other key. --}}
            <label class="ao-rg-all">
                <input type="checkbox" wire:model.live="all">
                <strong>All Permissions</strong>
                <i>Full administrator access. Individual permissions below are ignored while this is on.</i>
            </label>

            <div class="ao-rg-grid" @if ($this->all) aria-disabled="true" @endif>
                @foreach ($this->columns() as $column)
                    <div class="ao-rg-col">
                        @foreach ($column as $group => $items)
                            <h5 class="ao-rg-group">{{ ucwords(str_replace('_', ' ', $group)) }}</h5>
                            @foreach ($items as $key => $label)
                                <label class="ao-rg-perm">
                                    <input type="checkbox" value="{{ $key }}" wire:model="permissions"
                                        @disabled($this->all)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="ao-rg-bulk">
                <button type="button" wire:click="checkAll" @disabled($this->all)>Check All</button>
                <span>|</span>
                <button type="button" wire:click="uncheckAll" @disabled($this->all)>Uncheck All</button>
            </div>

            {{-- The reference's last two rows. Paymenter has neither a per-role report
                 restriction nor per-role email routing — reports read the same data for
                 every admin, and email goes by template, not by role — so these are shown
                 as the reference has them and disabled with the reason on them, rather
                 than as live controls that would save nothing. --}}
            <div class="ao-rg-tail">
                <div class="ao-rg-tail-row">
                    <span class="ao-of-label">Reports Access Controls</span>
                    <span class="ao-rg-tail-fields"
                        title="Paymenter's reports read the same data for every admin — there is no per-role restriction to store">
                        <label><input type="radio" checked disabled> Unrestricted</label>
                        <label><input type="radio" disabled> Restrict Access</label>
                    </span>
                </div>
                <div class="ao-rg-tail-row">
                    <span class="ao-of-label">Email Messages</span>
                    <span class="ao-rg-tail-fields ao-rg-tail-stack"
                        title="Paymenter routes email by template and recipient, not by admin role">
                        <label><input type="checkbox" checked disabled> System Emails (eg. Cron Notifications, Invalid Login Attempts, etc...)</label>
                        <label><input type="checkbox" checked disabled> Account Emails (eg. Order Confirmations, Details Changes, Automatic Setup Notifications, etc...)</label>
                        <label><input type="checkbox" checked disabled> Support Emails (eg. New Ticket &amp; Ticket Reply Notifications)</label>
                    </span>
                </div>
            </div>

            <div class="ao-of-buttons">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn"
                    href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\AdminRoles::getUrl() }}">Cancel Changes</a>
            </div>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
