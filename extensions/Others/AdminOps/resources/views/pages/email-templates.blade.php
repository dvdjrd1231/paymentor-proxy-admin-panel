{{--
    Email Templates, to issue #48's reference: the message categories as two columns of
    navy mini-grids — Status, Template Name, the edit icon — over Paymenter's real
    notification templates.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            @if ($canCreate)
                <button type="button" class="ao-mu-tab" wire:click="openModal('create')">&#10010; Create New Email Template</button>
            @endif
            <button type="button" class="ao-mu-tab" wire:click="openModal('languages')">&#127760; Manage Languages</button>
        </div>

        <div class="ao-et-cols">
            @foreach ($sections as $title => $rows)
                <section class="ao-et-section">
                    <h4 class="ao-ano-heading">{{ $title }}</h4>
                    <table class="ao-mu-grid">
                        <thead>
                            <tr><th class="ao-et-status">Status</th><th>Template Name</th><th class="ao-et-icon"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $template)
                                @php $editUrl = $edit($template); @endphp
                                <tr>
                                    <td class="ao-et-status">
                                        <span class="ao-et-dot {{ $template->enabled ? 'ao-on' : 'ao-off' }}"
                                            title="{{ $template->enabled ? 'Enabled' : 'Disabled' }}">{{ $template->enabled ? '✔' : '✖' }}</span>
                                    </td>
                                    <td class="ao-mu-left">
                                        @if ($editUrl)
                                            <a href="{{ $editUrl }}">{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::label($template) }}</a>
                                        @else
                                            {{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::label($template) }}
                                        @endif
                                    </td>
                                    <td class="ao-et-icon">
                                        @if ($editUrl)
                                            <a href="{{ $editUrl }}" title="Edit template">
                                                <x-filament::icon icon="ri-edit-box-line" class="ao-mu-cell-icon" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>

        {{-- The reference's Create New Email Template dialog: a type and a unique name,
             then straight into the editor. --}}
        @if ($modal === 'create')
            <div class="ao-mud-overlay" wire:click.self="$set('modal', null)">
                <form class="ao-mud" wire:submit.prevent="createTemplate">
                    <div class="ao-mud-head">
                        Create New Email Template
                        <button type="button" wire:click="$set('modal', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <label class="ao-mud-field">
                            <span>Email Type</span>
                            <select wire:model="newType">
                                @foreach ($types as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="ao-mud-field">
                            <span>Unique Name</span>
                            <input type="text" wire:model="newName" maxlength="255" autofocus>
                        </label>
                        @error('newName') <p class="ao-anc-errors">{{ $message }}</p> @enderror
                        <p class="ao-cp-note">
                            The name becomes the key the system sends this template by, so it cannot
                            match an existing one.
                        </p>
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('modal', null)">Cancel</button>
                            <button type="submit" class="ao-find-go">Create</button>
                        </span>
                    </div>
                </form>
            </div>
        @endif

        {{-- Manage Languages. The dialog is the reference's; what it reports is this
             install's own answer, which is that a template has one version. --}}
        @if ($modal === 'languages')
            <div class="ao-mud-overlay" wire:click.self="$set('modal', null)">
                <div class="ao-mud" role="dialog" aria-modal="true">
                    <div class="ao-mud-head">
                        Manage Languages
                        <button type="button" wire:click="$set('modal', null)" aria-label="Close">&times;</button>
                    </div>
                    <div class="ao-mud-text">
                        <p>To localise email templates into other languages, activate the language here.</p>
                        <p class="ao-ml-info">
                            The default version of an email template is used for any language for which no
                            localised template is available.
                        </p>

                        <p class="ao-ml-head">Currently Active Languages</p>
                        @forelse ($activeLocales as $code => $label)
                            <p class="ao-ml-row">
                                <span>{{ $label }}</span>
                                <button type="button" class="ao-cp-link" wire:click="deactivateLocale(@js($code))"
                                    title="Stop sending this language's versions. The wording is kept.">Deactivate</button>
                            </p>
                        @empty
                            <p>None</p>
                        @endforelse

                        <p class="ao-ml-head">Choose language to add</p>
                        @if ($addableLocales)
                            <select wire:model="newLocale">
                                <option value="">&mdash;</option>
                                @foreach ($addableLocales as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('newLocale') <span class="ao-of-note">{{ $message }}</span> @enderror
                        @else
                            <p class="ao-cp-note">
                                Every installed language is already active. More become available by adding
                                their translation files under <code>lang/</code>.
                            </p>
                        @endif
                    </div>
                    <div class="ao-mud-foot ao-mud-foot-only-right">
                        <span class="ao-mud-foot-right">
                            <button type="button" class="ao-mud-close" wire:click="$set('modal', null)">Cancel</button>
                            @if ($addableLocales)
                                <button type="button" class="ao-find-go" wire:click="activateLocale">Activate</button>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
