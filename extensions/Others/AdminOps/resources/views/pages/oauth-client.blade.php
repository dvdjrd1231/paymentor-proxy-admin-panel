{{--
    The reference's OpenID Connect credential form: Name and Description over the boxed
    Client API Credentials, then Logo URL and the repeatable Authorized Redirect URIs,
    closed by Save / Cancel / Delete Credential Set. One form for create and manage, as
    the reference has it.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <a class="ao-mu-tab"
                href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\OauthClients::getUrl() }}">&laquo; Back to OpenID Connect</a>
        </div>

        @if ($freshSecret)
            <div class="ao-oc-fresh">
                <strong>Copy the client secret now — it is shown once.</strong>
                <code>{{ $freshSecret }}</code>
            </div>
        @endif

        <form class="ao-find ao-of ao-of-even" autocomplete="off" wire:submit.prevent="save">
            <div class="ao-of-rows">
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-oc-name">Name</label>
                    <span><input id="ao-oc-name" type="text" wire:model="name" required></span>
                </div>
                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-oc-desc">Description</label>
                    <span><input id="ao-oc-desc" type="text" wire:model="description"></span>
                </div>

                @unless ($client)
                    {{-- The reference shows this row on the create form too, so the pair
                         is accounted for before it exists rather than appearing from
                         nowhere on save. --}}
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Client API Credentials</span>
                        <span class="ao-oc-pending">
                            &#9888; Client API Credentials will be generated upon first save.
                        </span>
                    </div>
                @endunless

                @if ($client)
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Client API Credentials</span>
                        <span class="ao-oc-box">
                            <label class="ao-oc-row">
                                <span>Client ID</span>
                                <input type="text" value="{{ $client->id }}" readonly>
                            </label>
                            <label class="ao-oc-row">
                                <span>Client Secret</span>
                                <span class="ao-oc-secret">
                                    <input type="text" readonly
                                        value="{{ $freshSecret ?? '' }}"
                                        placeholder="Not recoverable — reset it to get a new one"
                                        title="The secret is written once when the credential set is created. If it was not copied then, reset it.">
                                    <button type="button" class="ao-pg-btn" wire:click="resetSecret"
                                        wire:confirm="Reset the client secret? Anything using the old one stops authenticating immediately.">Reset Client Secret</button>
                                </span>
                            </label>
                            <label class="ao-oc-row">
                                <span>Creation Date</span>
                                <input type="text" readonly
                                    value="{{ $client->created_at?->format('l, F jS, Y g:i:sA') }}">
                            </label>
                        </span>
                    </div>
                @endif

                <div class="ao-of-row ao-of-row-single">
                    <label class="ao-of-label" for="ao-oc-logo">Logo URL</label>
                    <span class="ao-of-stack">
                        <i class="ao-oc-hint">URL or path relative to the client area directory to a logo image
                            file for this application.</i>
                        <input id="ao-oc-logo" type="text" wire:model="logoUrl" placeholder="eg. /path/to/logo.png">
                    </span>
                </div>

                <div class="ao-of-row ao-of-row-single">
                    <span class="ao-of-label">Authorized Redirect URIs</span>
                    <span class="ao-of-stack">
                        <i class="ao-oc-hint">Must have a protocol. Cannot contain URL fragments or relative
                            paths.</i>
                        @foreach ($redirects as $index => $redirect)
                            <span class="ao-oc-uri" wire:key="uri-{{ $index }}">
                                <input type="text" wire:model="redirects.{{ $index }}"
                                    placeholder="http://www.example.com/oauth2callback">
                                <button type="button" class="ao-oc-remove" wire:click="removeRedirect({{ $index }})">&times; Remove</button>
                            </span>
                        @endforeach
                        <span><button type="button" class="ao-oc-add" wire:click="addRedirect">&#10010; Add Another</button></span>
                    </span>
                </div>
            </div>

            {{-- One centred pair, as the reference has it, and its wording: a form that
                 has not saved yet says Generate Credentials, not Save Changes. --}}
            <div class="ao-oc-actions">
                <button type="submit" class="ao-find-go">{{ $client ? 'Save Changes' : 'Generate Credentials' }}</button>
                <a class="ao-oc-cancel"
                    href="{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\OauthClients::getUrl() }}">Cancel Changes</a>
                @if ($client)
                    <button type="button" class="ao-eo-delete" wire:click="$set('confirmingDelete', true)">Delete Credential Set</button>
                @endif
            </div>
        </form>

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        @endif

        @if ($confirmingDelete)
            <div class="ao-mud-overlay" wire:click.self="$set('confirmingDelete', false)">
                <div class="ao-mud ao-mud-sm" role="alertdialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Are you sure?
                        <button type="button" wire:click="$set('confirmingDelete', false)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>Delete this credential set?</p>
                        <p>Any application authenticating with it stops working immediately.</p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('confirmingDelete', false)">Cancel</button>
                            <button type="button" class="ao-mud-delete" wire:click="delete">OK</button>
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
