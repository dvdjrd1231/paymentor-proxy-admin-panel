{{--
    Domain Registrations, to the reference screenshot: Search/Filter panel, records line
    with Jump to Page, the navy grid with the reference's nine columns, With Selected:
    Send Message, and the pager. This store registers no domains, so the grid honestly
    shows No Records Found — the reference's own empty state.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->filter ? 'ao-on' : '' }}" wire:click="toggleFilter">
                Search/Filter
            </button>
        </div>

        @if ($this->filter)
            {{-- The reference's Search/Filter panel — the same two-column striped rows as
                 Manage Orders'. Registrar offers only Any: none are configured, and a
                 choice that exists nowhere else would be an invented one. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="search">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-dr-domain">Domain</label>
                        <span><input @nofill id="ao-dr-domain" class="ao-of-lg" type="text"
                            wire:model="domain" placeholder="example.com"></span>
                        <label class="ao-of-label" for="ao-dr-status">Status</label>
                        <span><select @nofill id="ao-dr-status" class="ao-of-md" wire:model="status">
                            <option value="">Any</option>
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\DomainRegistrations::STATUSES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-dr-registrar">Registrar</label>
                        <span><select @nofill id="ao-dr-registrar" class="ao-of-sm" wire:model="registrar">
                            <option value="">Any</option>
                        </select></span>
                        <label class="ao-of-label" for="ao-dr-client">Client Name</label>
                        <span><input @nofill id="ao-dr-client" class="ao-of-lg" type="text"
                            wire:model="client" placeholder="Client name or email"></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search</button>
            </form>
        @endif

        <div class="ao-mu-line">
            <span>0 Records Found</span>
            <span class="ao-mu-line-right">
                <button type="button" class="ao-mu-toggle {{ $hideInactive ? 'ao-on' : '' }}" wire:click="toggleInactive">
                    <i>{{ $hideInactive ? 'ON' : 'OFF' }}</i>
                    Hide Inactive Clients (0)
                </button>
                <label class="ao-mu-jump">
                    Jump to Page:
                    <select>
                        <option selected>1</option>
                    </select>
                </label>
            </span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox"></th>
                    <th>ID</th>
                    <th>Domain</th>
                    <th>Client Name</th>
                    <th>Reg Period</th>
                    <th>Registrar</th>
                    <th>Price</th>
                    <th>Next Due Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="11" class="ao-mu-none">No Records Found</td></tr>
            </tbody>
        </table>

        <div class="ao-mu-selected">
            With Selected:
            <button type="button" onclick="alert('Tick at least one domain first.')">Send Message</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" disabled>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">1</span>
            <button type="button" disabled>Next Page &raquo;</button>
        </nav>
    </div>
</x-filament-panels::page>
