{{--
    Available Extensions on the house standard: the tab strip, the marketplace grid with
    its search and type filter, the Ready to Install grid, and the Upload Extension form.
    Core's own screen headed itself "Extension" and drew a second sidebar inside the
    content; this replaces it and core's URL redirects here.
--}}
<x-filament-panels::page>
    {{-- These tabs stay server-side on purpose, unlike the rest of the panel's. Browse
         Marketplace calls out to the Paymenter registry, so rendering both panels at once
         would fetch the marketplace every time somebody opened Ready to Install. Here the
         round trip is the cheaper of the two. --}}
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $this->tab === 'marketplace' ? 'ao-on' : '' }}"
                wire:click="$set('tab', 'marketplace')">Browse Marketplace</button>
            <button type="button" class="ao-mu-tab {{ $this->tab === 'installable' ? 'ao-on' : '' }}"
                wire:click="$set('tab', 'installable')">Ready to Install / Upload</button>
        </div>

        @if ($this->tab === 'marketplace')
            @php $items = $this->marketplace(); @endphp

            {{-- The band's structure is not decorative: .ao-find hides any direct child
                 that is not one of its own four classes, which is how injected browser and
                 password-manager furniture is kept out of the layout. A bare input here
                 was hidden by that rule and the band rendered as a lone icon. --}}
            <form class="ao-find" autocomplete="off" wire:submit.prevent="$refresh">
                <span class="ao-find-glass"><x-filament::icon icon="ri-search-line" /></span>
                <span class="ao-find-fields">
                    <label class="ao-find-field ao-find-wide">
                        <span class="ao-find-label">Search</span>
                        <input @nofill type="text" wire:model.live.debounce.400ms="q"
                            placeholder="Extension name">
                    </label>
                    <label class="ao-find-field">
                        <span class="ao-find-label">Type</span>
                        <select wire:model.live="type">
                            <option value="all">All types</option>
                            <option value="gateway">Gateways</option>
                            <option value="server">Servers</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                </span>
            </form>

            @if ($error)
                <p class="ao-gs-empty">{{ $error }}</p>
            @else
                <div class="ao-ax-grid">
                    @forelse (array_slice($items, 0, $shown) as $item)
                        <div class="ao-ax-card">
                            <h5>{{ $item['name'] ?? 'Unnamed' }}</h5>
                            <span class="ao-ax-type">{{ ucfirst($item['type'] ?? 'other') }}</span>
                            <p>{{ \Illuminate\Support\Str::limit($item['description'] ?? '', 140) }}</p>
                            <div class="ao-ax-foot">
                                <span>{{ $item['author'] ?? '' }}</span>
                                @if (!empty($item['url']))
                                    {{-- Opens the marketplace listing; installing from here
                                         is the upload or the CLI, same as core. --}}
                                    <a class="ao-pg-btn" href="{{ $item['url'] }}" target="_blank" rel="noopener">View</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="ao-gs-empty">No extensions match that search.</p>
                    @endforelse
                </div>

                @if (count($items) > $shown)
                    <div class="ao-pr-center">
                        <button type="button" class="ao-pg-btn" wire:click="loadMore">Load more</button>
                    </div>
                @endif
            @endif
        @else
            @php $ready = $this->installable(); @endphp

            <p class="ao-ax-note">Extensions already on this server that have not been installed yet.</p>

            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Extension Name</th><th>Type</th><th>Description</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($ready as $item)
                        @php $meta = $item['meta'] ?? null; @endphp
                        <tr>
                            <td class="ao-mu-left">
                                {{ $meta?->name ?: $item['name'] }}
                                @if ($meta?->author)
                                    <i class="ao-ax-author">({{ $meta->author }})</i>
                                @endif
                            </td>
                            <td>{{ ucfirst($item['type']) }}</td>
                            <td class="ao-mu-left">{{ $meta?->description ?: '—' }}</td>
                            <td class="ao-mu-actions">
                                <button type="button" class="ao-pg-btn"
                                    wire:click="$set('confirming', '{{ $item['name'] }}')">Install</button>
                            </td>
                        </tr>
                    @empty
                        {{-- Not an error: it means every extension on disk is registered. --}}
                        <tr><td colspan="4" class="ao-mu-none">Nothing waiting — every extension on this server is installed.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h4 class="ao-ano-heading">Upload Extension</h4>
            <form class="ao-anc-card" wire:submit.prevent="uploadExtension">
                <label class="ao-anc-row">
                    <span>Extension File</span>
                    <span class="ao-anc-field">
                        <input type="file" wire:model="upload" accept=".zip">
                        <i>A zip, up to 10 MB. Gateways and servers land on their own setup screens;
                            anything else appears in the list above.</i>
                    </span>
                </label>
                <div class="ao-pr-center">
                    <button type="submit" class="ao-find-go" wire:loading.attr="disabled" wire:target="upload,uploadExtension">
                        <span wire:loading.remove wire:target="upload,uploadExtension">Upload Extension</span>
                        <span wire:loading wire:target="upload,uploadExtension">Uploading…</span>
                    </button>
                </div>
            </form>

            @if ($errors->any())
                <ul class="ao-anc-errors">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            @endif
        @endif

        @if ($confirming)
            <div class="ao-mud-overlay" wire:click.self="$set('confirming', null)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirming', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Install <strong>{{ $confirming }}</strong>?</p>
                        <p>Its tables are created now; you enable and configure it on the next screen.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirming', null)">Cancel</button>
                            <button type="button" class="ao-find-go" wire:click="install">Install</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
