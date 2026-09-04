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
            {{-- The reference's Add Category: one striped row, the blue button centred. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="addCategory">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-pr-cat">Category Name</label>
                        <span><input id="ao-pr-cat" class="ao-of-xl" type="text" wire:model="newCategory" required></span>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Add Category</button>
                </div>
            </form>
        @elseif ($tab === 'reply')
            @if ($category === '')
                {{-- The reference's own refusal, word for word. --}}
                <div class="ao-pr-notice">You cannot add a reply to the top level category</div>
            @else
                <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="addReply">
                    <div class="ao-of-rows">
                        <div class="ao-of-row ao-of-row-single">
                            <label class="ao-of-label" for="ao-pr-title">Reply Name</label>
                            <span><input id="ao-pr-title" class="ao-of-xl" type="text" wire:model="replyTitle" required></span>
                        </div>
                        <div class="ao-of-row ao-of-row-single">
                            <label class="ao-of-label" for="ao-pr-body">Message</label>
                            <span><textarea id="ao-pr-body" rows="6" wire:model="replyBody" required></textarea></span>
                        </div>
                    </div>
                    <div class="ao-of-buttons">
                        <button type="submit" class="ao-find-go">Add Predefined Reply</button>
                    </div>
                </form>
            @endif
        @elseif ($tab === 'search')
            {{-- The reference's Search/Filter: Reply Name and Message, its own two rows. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-pr-q">Reply Name</label>
                        <span><input @nofill id="ao-pr-q" class="ao-of-xl" type="text" wire:model="q"></span>
                    </div>
                    <div class="ao-of-row ao-of-row-single">
                        <label class="ao-of-label" for="ao-pr-qbody">Message</label>
                        <span><input @nofill id="ao-pr-qbody" class="ao-of-xl" type="text" wire:model="qBody"></span>
                    </div>
                </div>
                <div class="ao-of-buttons">
                    <button type="submit" class="ao-find-go">Search/Filter</button>
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
            @if ($category === '')
                <span class="ao-pr-here">Top Level</span>
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
            {{-- Plain text, as the reference renders its own empty state. --}}
            <p class="ao-pr-none">No Categories Found</p>
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
