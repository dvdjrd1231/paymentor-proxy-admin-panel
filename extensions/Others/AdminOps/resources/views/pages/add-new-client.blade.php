{{--
    Add New Client, to the reference screenshot: the two-column zebra form, labels on the
    right edge of their column, the store's custom properties underneath, Add Client at the
    end. Property fields come from `custom_properties`, so the CPF/CNPJ rows of the
    screenshot are here because the store defines them — not because they are hard-coded.
--}}
<x-filament-panels::page>
    <form class="ao-mu ao-anc" wire:submit.prevent="create">
        <div class="ao-anc-cols">
            <div class="ao-anc-col">
                <label class="ao-anc-row">
                    <span>First Name</span>
                    <input type="text" wire:model="first_name" required>
                </label>
                <label class="ao-anc-row">
                    <span>Last Name</span>
                    <input type="text" wire:model="last_name" required>
                </label>
                @isset($fixed['company_name'])
                    <label class="ao-anc-row">
                        <span>Company Name</span>
                        <span class="ao-anc-field">
                            <input type="text" wire:model="props.company_name">
                            <i>(Optional)</i>
                        </span>
                    </label>
                @endisset
                <label class="ao-anc-row">
                    <span>Email Address</span>
                    <input type="email" wire:model="email" required>
                </label>
                <label class="ao-anc-row">
                    <span>Password</span>
                    <span class="ao-anc-field">
                        <input type="text" wire:model="password" data-ao-password required>
                        <button type="button" class="ao-anc-generate" data-ao-generate>Generate Password</button>
                    </span>
                </label>
                <label class="ao-anc-row">
                    <span>Language</span>
                    <select><option>Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Status</span>
                    <select><option>Active</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Client Group</span>
                    <select><option>None</option></select>
                </label>
            </div>

            <div class="ao-anc-col">
                @foreach ([['address', 'Address 1'], ['address2', 'Address 2'], ['city', 'City'], ['state', 'State/Region'], ['zip', 'Postcode']] as [$key, $label])
                    @isset($fixed[$key])
                        <label class="ao-anc-row">
                            <span>{{ $label }}</span>
                            <span class="ao-anc-field">
                                <input type="text" wire:model="props.{{ $key }}" @if ($fixed[$key]->required) required @endif>
                                @unless ($fixed[$key]->required)
                                    <i>(Optional)</i>
                                @endunless
                            </span>
                        </label>
                    @endisset
                @endforeach

                @isset($fixed['country'])
                    <label class="ao-anc-row">
                        <span>Country</span>
                        <select wire:model="props.country" required>
                            <option value="">—</option>
                            @foreach ($fixed['country']->allowed_values ?? [] as $value => $label)
                                <option value="{{ is_int($value) ? $label : $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endisset

                @isset($fixed['phone'])
                    <label class="ao-anc-row">
                        <span>Phone Number</span>
                        <input type="text" wire:model="props.phone" placeholder="+1 201-555-0123" required>
                    </label>
                @endisset

                <label class="ao-anc-row">
                    <span>Payment Method</span>
                    <select><option>Select to Change Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Billing Contact</span>
                    <select><option>Default</option></select>
                </label>
                <label class="ao-anc-row">
                    <span>Currency</span>
                    <select wire:model="currency">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        @if ($extras->isNotEmpty())
            <div class="ao-anc-cols">
                <div class="ao-anc-col">
                    @foreach ($extras->values()->filter(fn ($p, $i) => $i % 2 === 0) as $property)
                        <label class="ao-anc-row">
                            <span>{{ $property->name }}</span>
                            @if ($property->type === 'checkbox')
                                <span class="ao-anc-field"><input type="checkbox" wire:model="props.{{ $property->key }}" value="1"></span>
                            @else
                                <input type="text" wire:model="props.{{ $property->key }}">
                            @endif
                        </label>
                    @endforeach
                </div>
                <div class="ao-anc-col">
                    @foreach ($extras->values()->filter(fn ($p, $i) => $i % 2 === 1) as $property)
                        <label class="ao-anc-row">
                            <span>{{ $property->name }}</span>
                            @if ($property->type === 'checkbox')
                                <span class="ao-anc-field"><input type="checkbox" wire:model="props.{{ $property->key }}" value="1"></span>
                            @else
                                <input type="text" wire:model="props.{{ $property->key }}">
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="ao-anc-submit">
            <button type="submit" class="ao-find-go">Add Client</button>
        </div>
    </form>

    <script>
        (() => {
            const root = document.currentScript.closest('.fi-page') ?? document;

            root.addEventListener('click', (event) => {
                if (!event.target.closest('[data-ao-generate]')) return;

                const field = root.querySelector('[data-ao-password]');
                const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!#%+';
                const bytes = crypto.getRandomValues(new Uint8Array(14));
                field.value = [...bytes].map((b) => alphabet[b % alphabet.length]).join('');
                field.dispatchEvent(new Event('input', { bubbles: true }));
            });
        })();
    </script>
</x-filament-panels::page>
