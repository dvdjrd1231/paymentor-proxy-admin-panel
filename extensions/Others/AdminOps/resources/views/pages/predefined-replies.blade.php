{{--
    Predefined Replies, to the reference screenshot: the three tabs, the framed form under
    the open one, "You are here", and the listing — categories first, then the level's
    replies.
--}}
<x-filament-panels::page>
    <div class="ao-mu ao-pr">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $tab === 'category' ? 'ao-on' : '' }}" wire:click="open('category')">Add Category</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'reply' ? 'ao-on' : '' }}" wire:click="open('reply')">Add Predefined Reply</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'search' ? 'ao-on' : '' }}" wire:click="open('search')">Search/Filter</button>
        </div>

        @if ($tab === 'category')
            <form class="ao-anc-card" wire:submit.prevent="addCategory">
                <label class="ao-anc-row">
                    <span>Category Name</span>
                    <input type="text" wire:model="newCategory" placeholder="e.g. Billing answers" required>
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Category</button></div>
            </form>
        @elseif ($tab === 'reply')
            <form class="ao-anc-card" wire:submit.prevent="addReply">
                <label class="ao-anc-row">
                    <span>Reply Name</span>
                    <input type="text" wire:model="replyTitle" placeholder="e.g. Password reset steps" required>
                </label>
                <label class="ao-anc-row">
                    <span>Reply Text</span>
                    <textarea rows="6" wire:model="replyBody" placeholder="The reply staff will insert into tickets" required></textarea>
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Predefined Reply</button></div>
            </form>
        @elseif ($tab === 'search')
            <form class="ao-find" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-grow">
                        <span>Search Term</span>
                        <input @nofill type="search" wire:model="q" placeholder="Title or reply text">
                    </label>
                </div>
                <button type="submit" class="ao-find-go">Search</button>
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
            @if ($category === '')
                Top Level
            @else
                <button type="button" class="ao-cp-link" wire:click="$set('category', '')">Top Level</button> &raquo; {{ $category }}
            @endif
        </p>

        @if ($categories->isNotEmpty())
            @foreach ($categories as $row)
                <div class="ao-pr-row">
                    <button type="button" class="ao-cp-link ao-pr-name" wire:click="$set('category', '{{ addslashes($row->name) }}')">
                        &#128193; {{ $row->name }}
                    </button>
                    <button type="button" class="ao-mo-delete" title="Delete category" wire:click="deleteCategory({{ $row->id }})">
                        <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                    </button>
                </div>
            @endforeach
        @elseif ($category === '' && $q === '' && $replies->isEmpty())
            <p class="ao-pr-none"><b>No Categories Found</b></p>
        @endif

        @foreach ($replies as $reply)
            <div class="ao-pr-row">
                <details class="ao-pr-reply">
                    <summary>{{ $reply->title }}</summary>
                    <p>{{ $reply->body }}</p>
                </details>
                <button type="button" class="ao-mo-delete" title="Delete reply" wire:click="deleteReply({{ $reply->id }})">
                    <x-filament::icon icon="ri-indeterminate-circle-fill" class="ao-mu-cell-icon" />
                </button>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
