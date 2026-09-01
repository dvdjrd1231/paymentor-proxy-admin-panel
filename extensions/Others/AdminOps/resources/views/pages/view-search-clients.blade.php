{{--
    View/Search Clients, to the reference screenshot: search band, records line with Jump to
    Page and the Hide Inactive toggle, navy grid, With Selected underneath, page buttons.

    Send Message is real, not scenery: it collects the ticked rows' addresses into a mailto:
    so the admin's own mail client opens addressed — the one way to "send a message" that
    needs no backend nobody has built.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        {{-- autocomplete="off": these are search filters, not a form anyone signs in with.
             Left on, Chrome reads Name/Email/Phone as an address form and password managers
             read it as a login, and both then plant their own icon inside the first field —
             which is how this band came out misaligned on one machine and right on every
             other. The rest of the opt-out is stamped per field by the skin's script. --}}
        <form class="ao-find" autocomplete="off" wire:submit.prevent="search">
            <span class="ao-find-glass" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" width="18" height="18">
                    <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                </svg>
            </span>

            <div class="ao-find-fields">
                <label class="ao-find-field ao-find-wide">
                    <span>Client/Company Name</span>
                    <input @nofill type="text" wire:model="name" placeholder="Name or company">
                </label>
                <label class="ao-find-field ao-find-wide">
                    <span>Email Address</span>
                    <input @nofill type="text" wire:model="email" placeholder="user@example.com">
                </label>
                <label class="ao-find-field">
                    <span>Phone Number</span>
                    {{-- The reference puts a dialling-code picker in front of the number.
                         Ours is real: the code and the number are searched together, so
                         picking +55 finds the Brazilian numbers and nothing else. --}}
                    <span class="ao-find-phone">
                        <select @nofill wire:model="dialCode">
                            <option value="">+1</option>
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients::DIAL_CODES as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input @nofill type="text" wire:model="phone" placeholder="201-555-0123">
                    </span>
                </label>
                <label class="ao-find-field">
                    {{-- Paymenter has no client groups; the reference's control is kept so
                         the band reads the same, with the one honest answer it has. --}}
                    <span>Client Group</span>
                    <select @nofill>
                        <option>Any</option>
                    </select>
                </label>
                <label class="ao-find-field">
                    <span>Status</span>
                    <select @nofill wire:model="status">
                        <option value="">Any</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>

                @if ($this->advanced)
                    <label class="ao-find-field">
                        <span>Client ID</span>
                        <input @nofill type="text" wire:model="cid" inputmode="numeric" placeholder="e.g. 813">
                    </label>
                    <label class="ao-find-field">
                        <span>Signed Up From</span>
                        <input @nofill type="date" wire:model="from">
                    </label>
                    <label class="ao-find-field">
                        <span>Signed Up To</span>
                        <input @nofill type="date" wire:model="to">
                    </label>
                @endif
            </div>

            <button type="button" class="ao-find-adv" wire:click="toggleAdvanced">
                {{ $this->advanced ? '− Advanced' : '+ Advanced' }}
            </button>

            <button type="submit" class="ao-find-go">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" width="13" height="13" aria-hidden="true">
                    <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                </svg>
                Search
            </button>
        </form>

        <div class="ao-mu-line">
            <span>
                {{ number_format($clients->total()) }} Records Found{{ $clients->total() > 0 ? ', Showing ' . number_format($clients->firstItem()) . ' to ' . number_format($clients->lastItem()) : '' }}
            </span>
            <span class="ao-mu-line-right">
                <button type="button" class="ao-mu-toggle {{ $hideInactive ? 'ao-on' : '' }}"
                    wire:click="toggleInactive">
                    <i>{{ $hideInactive ? 'ON' : 'OFF' }}</i>
                    Hide Inactive Clients ({{ number_format($hiddenCount) }})
                </button>
                <label class="ao-mu-jump">
                    Jump to Page:
                    <select wire:change="jump($event.target.value)">
                        @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients::pages($clients) as $number)
                            <option value="{{ $number }}" @selected($number === $clients->currentPage())>{{ $number }}</option>
                        @endforeach
                    </select>
                </label>
            </span>
        </div>

        <table class="ao-mu-grid">
            <thead>
                <tr>
                    <th class="ao-mu-check"><input type="checkbox" data-ao-check-all></th>
                    <th>ID &#9662;</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Company Name</th>
                    <th>Email Address</th>
                    <th>Services</th>
                    <th>Created</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clients as $client)
                    @php
                        $summary = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ClientSummary::getUrl(['record' => $client->id]);
                        $company = $client->properties->first()?->value;
                        $active = \Paymenter\Extensions\Others\AdminOps\Admin\Pages\ViewSearchClients::isActive($client);
                    @endphp
                    <tr>
                        <td class="ao-mu-check"><input type="checkbox" data-ao-check value="{{ $client->email }}"></td>
                        <td>{{ $client->id }}</td>
                        <td><a href="{{ $summary }}">{{ $client->first_name ?: '—' }}</a></td>
                        <td><a href="{{ $summary }}">{{ $client->last_name ?: '—' }}</a></td>
                        <td>{{ $company ?: '' }}</td>
                        <td><a href="{{ $summary }}">{{ $client->email }}</a></td>
                        <td>{{ $client->services_count }} ({{ $client->services_all_count }})</td>
                        <td>{{ $client->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <span class="ao-mu-status {{ $active ? 'ao-mu-active' : 'ao-mu-inactive' }}">
                                {{ $active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ao-mu-none">No Records Found</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ao-mu-selected">
            With Selected:
            <button type="button" data-ao-send-message>Send Message</button>
        </div>

        <nav class="ao-mu-pages">
            <button type="button" wire:click="jump({{ $clients->currentPage() - 1 }})"
                @disabled($clients->onFirstPage())>&laquo; Previous Page</button>
            <span class="ao-mu-page-now">{{ $clients->currentPage() }}</span>
            <button type="button" wire:click="jump({{ $clients->currentPage() + 1 }})"
                @disabled(!$clients->hasMorePages())>Next Page &raquo;</button>
        </nav>
    </div>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;

            root.addEventListener('change', (event) => {
                if (!event.target.matches('[data-ao-check-all]')) return;
                for (const box of root.querySelectorAll('[data-ao-check]')) box.checked = event.target.checked;
            });

            root.addEventListener('click', (event) => {
                const button = event.target.closest('[data-ao-send-message]');
                if (!button) return;

                const picked = [...root.querySelectorAll('[data-ao-check]:checked')].map((box) => box.value);

                if (!picked.length) {
                    alert('Tick at least one client first.');
                    return;
                }

                window.location.href = 'mailto:' + encodeURIComponent(picked.join(','));
            });
        })();
    </script>
</x-filament-panels::page>
