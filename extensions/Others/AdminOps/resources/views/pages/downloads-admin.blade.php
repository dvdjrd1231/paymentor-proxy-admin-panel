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
            <form class="ao-anc-card" wire:submit.prevent="addCategory">
                <label class="ao-anc-row">
                    <span>Category Name</span>
                    <input type="text" wire:model="newCategory" placeholder="e.g. Proxy setup guides" required>
                </label>
                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" wire:model="newCategoryDescription" placeholder="Shown under the category name">
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Category</button></div>
            </form>
        @elseif ($tab === 'download')
            <form class="ao-anc-card" wire:submit.prevent="addDownload">
                <label class="ao-anc-row">
                    <span>Title</span>
                    <input type="text" wire:model="fileTitle" placeholder="e.g. Proxy client for Windows" required>
                </label>
                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" wire:model="fileDescription" placeholder="Shown under the title">
                </label>
                <label class="ao-anc-row">
                    <span>File<br><i>Max 100MB</i></span>
                    <span class="ao-anc-field"><input type="file" wire:model="upload" required></span>
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go" wire:loading.attr="disabled">Add Download</button></div>
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
                Download Home
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
