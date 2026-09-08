{{--
    Create a New Group / Edit Group, to the reference's screenshot: Group Name, Description,
    the tall Assigned Products list box, and Save Changes beside Back to Groups List.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <form wire:submit.prevent="save">
            <div class="ao-anc-card">
                <label class="ao-anc-row">
                    <span>Group Name</span>
                    <input type="text" wire:model="form.name" maxlength="255" required>
                </label>

                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" wire:model="form.description" maxlength="255">
                </label>

                {{-- A Paymenter option carries its own control type, which the reference
                     asks per value rather than per group. It is here because a group saved
                     without one renders as nothing at checkout. --}}
                <label class="ao-anc-row">
                    <span>
                        Option Type
                        <i>How the choice is presented at checkout.</i>
                    </span>
                    <select wire:model="form.type">
                        @foreach ([
                            'select' => 'Drop Down',
                            'radio' => 'Radio Buttons',
                            'checkbox' => 'Tick Box',
                            'slider' => 'Slider',
                            'text' => 'Text Box',
                            'number' => 'Number',
                        ] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="ao-anc-row ao-cog-products">
                    <span>Assigned Products</span>
                    <span class="ao-anc-field">
                        <select class="ao-ep-list ao-cog-list" multiple size="12" wire:model="productIds">
                            @foreach ($products as $product)
                                <option value="{{ $product['id'] }}">{{ $product['label'] }}</option>
                            @endforeach
                        </select>
                        <i>{{ $products->isEmpty()
                            ? 'There are no products to assign this group to yet.'
                            : 'Ctrl-click or Shift-click to choose more than one.' }}</i>
                    </span>
                </div>

                <label class="ao-anc-row">
                    <span>
                        Hidden
                        <i>Kept off the order form — set by an admin only.</i>
                    </span>
                    <span class="ao-anc-field">
                        <label class="ao-check">
                            <input type="checkbox" wire:model="form.hidden">
                            <span>Check to hide this group from clients</span>
                        </label>
                    </span>
                </label>
            </div>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif

            <div class="ao-pr-center ao-cpg-actions">
                <button type="submit" class="ao-find-go">Save Changes</button>
                <a class="ao-pg-btn" href="{{ $listUrl }}">Back to Groups List</a>
            </div>
        </form>

        {{-- Only once the group exists: values belong to a group, and a table of them
             before there is one to belong to would be an empty promise. --}}
        @if ($group)
            <h3 class="ao-sub">Group Options</h3>

            <table class="ao-mu-grid">
                <thead>
                    <tr>
                        <th>Option Name</th>
                        <th>Variable</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group->children->sortBy('sort') as $value)
                        <tr>
                            <td class="ao-mu-left">{{ $value->name }}</td>
                            <td>{{ $value->env_variable ?: '—' }}</td>
                            <td>{{ (int) $value->sort }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($valuesUrl)
                <p class="ao-cp-note">
                    Each value carries its own price per currency and billing cycle, so they are
                    added and priced on
                    <a class="ao-link" href="{{ $valuesUrl }}">the group's options form</a>
                    rather than in this table.
                </p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
