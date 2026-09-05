{{--
    Downloads, to the reference screenshot: the two tabs, the breadcrumb, the grey
    Categories band and the level's listing.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-dl">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $tab === 'category' ? 'ao-on' : '' }}" wire:click="open('category')">Add Category</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'download' ? 'ao-on' : '' }}" wire:click="open('download')">Add Download</button>
        </div>

        @if ($tab === 'category')
            {{-- The reference's Add Category form: Category Name with Check to Hide on the
                 same striped row, Description beneath, the blue button centred. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="addCategory">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-dl-cat">Category Name</label>
                        <span class="ao-of-inline">
                            <input id="ao-dl-cat" class="ao-of-lg" type="text" wire:model="newCategory"
                                placeholder="e.g. Proxy setup guides" required>
                            <label class="ao-of-check">
                                <input type="checkbox" wire:model="newCategoryHidden"> Check to Hide
                            </label>
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-dl-catdesc">Description</label>
                        <span><input id="ao-dl-catdesc" class="ao-of-lg" type="text"
                            wire:model="newCategoryDescription" placeholder="Shown under the category name"></span>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Add Category</button>
                </div>
            </form>
        @elseif ($tab === 'download')
            {{-- The reference's Add Download form, row for row: Type, Title, Description,
                 the two Upload File sources, and the three checkboxes. The Manual FTP
                 option is real here too: it registers a file already placed in the
                 downloads folder by hand. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="addDownload">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-dl-type">Type</label>
                        <span><select id="ao-dl-type" class="ao-of-md" wire:model="fileType">
                            @foreach (\Paymenter\Extensions\Others\AdminOps\Admin\Pages\DownloadsAdmin::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-dl-title">Title</label>
                        <span><input id="ao-dl-title" class="ao-of-lg" type="text" wire:model="fileTitle"
                            placeholder="e.g. Proxy client for Windows" required></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-dl-desc">Description</label>
                        <span><textarea id="ao-dl-desc" rows="3" wire:model="fileDescription"
                            placeholder="Shown under the title"></textarea></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Upload File</span>
                        <span class="ao-of-stack">
                            <label class="ao-of-check">
                                <input type="radio" value="manual" wire:model.live="source">
                                Manual FTP Upload to Downloads Folder
                            </label>
                            <span class="ao-of-inline">
                                Enter Filename:
                                <input class="ao-of-lg" type="text" wire:model="manualFilename"
                                    placeholder="file already in the downloads folder"
                                    @disabled($source !== 'manual')>
                            </span>
                            <label class="ao-of-check">
                                <input type="radio" value="upload" wire:model.live="source">
                                Upload File
                            </label>
                            <input type="file" wire:model="upload" @disabled($source !== 'upload')>
                            <span class="ao-of-note">
                                Server Max File Upload Size: <b>{{ \Paymenter\Extensions\Others\AdminOps\Admin\Pages\DownloadsAdmin::uploadLimit() }}</b>
                                - To increase this limit you need to modify your servers php.ini file
                            </span>
                        </span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Clients Only</span>
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="clientsOnly">
                            Check to only allow logged in clients permission to download it
                        </label>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Product Download</span>
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="productDownload">
                            Check if this download should only be available after a product or addon purchase
                        </label>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <span class="ao-of-label">Hidden</span>
                        <label class="ao-of-check">
                            <input type="checkbox" wire:model="fileHidden">
                            Check to hide from client area
                        </label>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go" wire:loading.attr="disabled">Add Download</button>
                    <button type="button" class="ao-of-go" wire:click="cancelChanges">Cancel Changes</button>
                </div>
            </form>
        @endif

        @if ($errors->any())
            <ul class="ao-anc-errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <p class="ao-pr-crumb">
            You are here:
            @if (!$current)
                <span class="ao-pr-here">Download Home</span>
            @else
                <button type="button" class="ao-cp-link" wire:click="$set('category', null)">Download Home</button> &raquo; {{ $current->name }}
            @endif
        </p>

        {{-- Issue #23: the reference's empty Download Home shows nothing below the
             crumb — the band only appears once there is something to put under it. --}}
        @if ($categories->isNotEmpty())
            <div class="ao-dl-band">Categories</div>
        @endif

        @forelse ($categories as $row)
            <div class="ao-pr-row">
                <span>
                    <button type="button" class="ao-cp-link ao-pr-name" wire:click="$set('category', {{ $row->id }})">
                        <b>{{ $row->name }}</b> ({{ $row->files_count }})
                    </button>
                    @if ($row->description)
                        <br><span class="ao-dl-desc">{{ $row->description }}</span>
                    @endif
                </span>
                <button type="button" class="ao-mo-delete" title="Delete category" wire:click="deleteCategory({{ $row->id }})"
                    wire:confirm="Delete this category? Its files move to Download Home.">
                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                </button>
            </div>
        @empty
        @endforelse

        @if ($files->isNotEmpty())
            <div class="ao-dl-band">Files</div>
            @foreach ($files as $file)
                <div class="ao-pr-row">
                    <span>
                        <b>{{ $file->title }}</b>
                        <span class="ao-dl-desc">({{ $file->filename }}, {{ number_format($file->filesize / 1024, 1) }} KB)</span>
                        @if ($file->description)
                            <br><span class="ao-dl-desc">{{ $file->description }}</span>
                        @endif
                    </span>
                    <button type="button" class="ao-mo-delete" title="Delete download" wire:click="deleteFile({{ $file->id }})"
                        wire:confirm="Delete this download? The stored file is removed too.">
                        <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                    </button>
                </div>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
