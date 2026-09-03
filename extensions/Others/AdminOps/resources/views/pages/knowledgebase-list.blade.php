{{--
    Knowledgebase, to issue #24's reference screenshots: Add Category and Add Article tabs
    with their framed forms (Add Article refuses the top level with the reference's own
    wording), "You are here", the level's categories and articles, and the Browse by Tag
    band. Search/Filter stays — Leandro's standing ask is that every list carries one.
--}}
<x-filament-panels::page>
    <div class="ao-mu">
        <div class="ao-tx-tabs">
            <button type="button" class="ao-mu-tab {{ $tab === 'category' ? 'ao-on' : '' }}" wire:click="open('category')">Add Category</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'article' ? 'ao-on' : '' }}" wire:click="open('article')">Add Article</button>
            <button type="button" class="ao-mu-tab {{ $tab === 'search' ? 'ao-on' : '' }}" wire:click="open('search')">Search/Filter</button>
        </div>

        @if ($tab === 'category')
            <form class="ao-anc-card" wire:submit.prevent="addCategory">
                <label class="ao-anc-row">
                    <span>Category Name</span>
                    <span class="ao-anc-field">
                        <input type="text" wire:model="newCategory" required>
                        <label class="ao-kb-hide"><input type="checkbox" wire:model="newCategoryHidden"> Check to Hide</label>
                    </span>
                </label>
                <label class="ao-anc-row">
                    <span>Description</span>
                    <input type="text" wire:model="newCategoryDescription">
                </label>
                <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Category</button></div>
            </form>
        @elseif ($tab === 'article')
            @if (!$current)
                {{-- The reference's own refusal, word for word. --}}
                <div class="ao-anc-card">You cannot add an article to the top level category</div>
            @else
                <form class="ao-anc-card" wire:submit.prevent="addArticle">
                    <label class="ao-anc-row">
                        <span>Title</span>
                        <input type="text" wire:model="articleTitle" required>
                    </label>
                    <label class="ao-anc-row">
                        <span>Article Content</span>
                        <textarea rows="8" wire:model="articleContent" required></textarea>
                    </label>
                    <div class="ao-pr-center"><button type="submit" class="ao-find-go">Add Article</button></div>
                </form>
            @endif
        @elseif ($tab === 'search')
            {{-- The reference's Search/Filter panel — the same striped rows as Manage Orders'. --}}
            <form class="ao-find ao-of" autocomplete="off" wire:submit.prevent="$refresh">
                <div class="ao-of-rows">
                    <div class="ao-of-row">
                        <label class="ao-of-label" for="ao-kb-q">Article Title</label>
                        <span><input @nofill id="ao-kb-q" class="ao-of-lg" type="text"
                            wire:model.live.debounce.500ms="q" placeholder="Search articles"></span>
                    </div>
                </div>
                <button type="submit" class="ao-of-go">Search</button>
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
                <span class="ao-pr-here">Knowledgebase Home</span>
            @else
                <button type="button" class="ao-cp-link" wire:click="$set('category', null)">Knowledgebase Home</button>
                &raquo; {{ $current->name }}
            @endif
        </p>

        @if ($categories->isNotEmpty())
            <div class="ao-dl-band">Categories</div>
            @foreach ($categories as $row)
                <div class="ao-pr-row">
                    <button type="button" class="ao-cp-link ao-pr-name" wire:click="$set('category', {{ $row->id }})">
                        &#128193; {{ $row->name }} ({{ $row->articles_count }})
                    </button>
                </div>
            @endforeach
        @endif

        @if ($current || trim($q) !== '')
            <div class="ao-dl-band">Articles</div>
            <table class="ao-mu-grid">
                <thead>
                    <tr><th>Title</th><th>Category</th><th>Views</th><th>Published</th><th>Updated</th></tr>
                </thead>
                <tbody>
                    @forelse ($articles as $article)
                        <tr>
                            <td class="ao-mu-left">{{ $article->title }}</td>
                            <td>{{ $article->category?->name ?? '—' }}</td>
                            <td>{{ number_format((int) $article->views) }}</td>
                            <td>
                                <button type="button" class="ao-cp-link" wire:click="togglePublished({{ $article->id }})"
                                    title="Click to {{ $article->is_active ? 'unpublish' : 'publish' }}">
                                    {{ $article->is_active ? 'Yes' : 'No' }}
                                </button>
                            </td>
                            <td>{{ $article->updated_at?->format('m/d/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ao-mu-none">No Records Found</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif

        {{-- The reference's Browse by Tag band. The Knowledgebase extension has no tag
             concept, so the honest state is the reference's own empty one. --}}
        <div class="ao-dl-band" title="Articles have no tags — the Knowledgebase extension does not support tagging">Browse by Tag</div>
        <p class="ao-kb-notags">No Tags Found</p>
    </div>
</x-filament-panels::page>
