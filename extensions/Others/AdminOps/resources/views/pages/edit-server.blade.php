{{-- WHMCS's Add/Edit Server. The reference has a simple view and an advanced one behind a
     "Go to Advanced Mode" button; both are here, on one page, as a toggle. --}}
<x-filament-panels::page>
    <div class="ao-mu ao-es">
        <div class="ao-es-mode">
            <button type="button" class="ao-pg-btn" wire:click="$toggle('advanced')">
                {{ $advanced ? 'Go to Simple Mode' : 'Go to Advanced Mode' }}
            </button>
        </div>

        <form wire:submit.prevent="save">
            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Module</span>
                    <span class="ao-anc-field">
                        @if ($server)
                            <input type="text" value="{{ $extension }}" disabled
                                title="A server keeps the module it was created with — its saved settings belong to that module's shape">
                            <i class="ao-anc-hint">Fixed after creation: the saved settings belong to this
                                module. Add a new server to use a different one.</i>
                        @else
                            <select wire:model.live="extension">
                                @foreach ($modules as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <i class="ao-anc-hint">Which provisioning module this server talks through.</i>
                        @endif
                    </span>
                </label>

                <label class="ao-anc-row">
                    <span>Name</span>
                    <span class="ao-anc-field">
                        <input type="text" wire:model="name" maxlength="255" placeholder="How this server is listed">
                    </span>
                </label>

                <div class="ao-anc-row">
                    <span>Enable/Disable</span>
                    <span class="ao-anc-field">
                        <label class="ao-check">
                            <input type="checkbox" wire:model="enabled">
                            <span>Check to allow this server to provision new services</span>
                        </label>
                        <i class="ao-anc-hint">Turning it off leaves running services alone; only new provisioning stops.</i>
                    </span>
                </div>
            </div>

            {{-- The reference's Server Details panel: the module's own credentials, drawn
                 from what the module says it needs rather than a fixed cPanel field list. --}}
            <h3 class="ao-sub">Server Details</h3>

            <div class="ao-anc-card">
                @forelse ($fields as $option)
                    @php
                        $key = $option['name'];
                        $type = $option['type'] ?? 'text';
                    @endphp
                    <div class="ao-anc-row" wire:key="srv-{{ $extension }}-{{ $key }}">
                        <span>{{ $option['label'] ?? $key }}</span>
                        <span class="ao-anc-field">
                            @if ($type === 'checkbox')
                                <label class="ao-check">
                                    <input type="checkbox" wire:model="settings.{{ $key }}">
                                    <span>Enabled</span>
                                </label>
                            @elseif ($type === 'select')
                                <select wire:model="settings.{{ $key }}">
                                    @foreach (($option['options'] ?? []) as $ov => $ol)
                                        <option value="{{ is_array($ol) ? ($ol['value'] ?? $ov) : $ov }}">
                                            {{ is_array($ol) ? ($ol['label'] ?? $ov) : $ol }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($type === 'textarea')
                                <textarea rows="3" wire:model="settings.{{ $key }}"></textarea>
                            @else
                                <input type="{{ !empty($option['encrypted']) ? 'password' : 'text' }}"
                                    wire:model="settings.{{ $key }}"
                                    autocomplete="off">
                            @endif

                            {{-- Under the field, not inside the label: these run to several
                                 sentences, and a 9.5rem label column turned one of them into
                                 twelve ragged lines that pushed its own input off the fold. --}}
                            @if (!empty($option['description']))
                                <i class="ao-anc-hint">{{ $option['description'] }}</i>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="ao-gs-empty">This module takes no settings.</p>
                @endforelse

                @if ($canTest)
                    <div class="ao-anc-row">
                        <span>Connection</span>
                        <span class="ao-anc-field">
                            <button type="button" class="ao-find-adv" wire:click="test" wire:loading.attr="disabled">
                                Test Connection &raquo;
                            </button>
                            <i class="ao-anc-hint" wire:loading wire:target="test">Asking the module…</i>
                            <i class="ao-anc-hint" wire:loading.remove wire:target="test">Tests what is on screen, before saving it.</i>
                        </span>
                    </div>
                @endif
            </div>

            @if ($advanced)
                {{-- The reference's advanced panels. None of these has anywhere to live here:
                     a server is a module instance, not a box, so there is no host of our own
                     to name, no accounts to cap and no zone to serve. Each says so. --}}
                <h3 class="ao-sub">Server Properties</h3>

                <div class="ao-anc-card">
                    @foreach ([
                        'Hostname' => 'The address lives in the module settings above — it is the module that knows where to connect.',
                        'IP Address' => 'Same: this platform never connects to a server by address of its own, only through the module.',
                        'Assigned IP Addresses' => 'Addresses are handed out by the panel when a service is provisioned, not reserved here.',
                        'Monthly Cost' => 'Nothing reads a server cost — margin is not reported per server.',
                        'Datacenter/NOC' => 'Free-text on the reference; nothing here would group or filter by it.',
                        'Maximum No. of Accounts' => 'The panel decides what it can take; this platform does not cap provisioning per server.',
                        'Server Status Address' => 'There is no server status page for customers on this deployment.',
                    ] as $label => $why)
                        <div class="ao-anc-row">
                            <span>{{ $label }}</span>
                            <span class="ao-anc-field ao-gs-off">
                                <input type="text" disabled title="{{ $why }}">
                                <i class="ao-anc-hint">{{ $why }}</i>
                            </span>
                        </div>
                    @endforeach
                </div>

                <h3 class="ao-sub">Nameservers</h3>

                <div class="ao-anc-card">
                    <p class="ao-gs-empty">
                        Not available: nameservers are for a server that hosts domains, and domains are
                        switched off on this deployment.
                    </p>
                </div>

                <h3 class="ao-sub">SSO Access Control</h3>

                <div class="ao-anc-card">
                    <p class="ao-gs-empty">
                        Not available: single sign-on into the provisioning panel from here is not
                        implemented, so there is no access to restrict.
                    </p>
                </div>
            @endif

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn" href="{{ $listUrl }}">Cancel Changes</a>
            </div>
        </form>
    </div>
</x-filament-panels::page>
