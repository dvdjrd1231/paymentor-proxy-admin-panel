{{-- Knowledgebase, to issue #24: categories, then the level's articles. --}}
<x-filament-panels::page>
    <div class="ao-mu">
        {{-- "Manage Articles" used to sit here too, pointed at core's own KbArticleResource
             list — a second, un-styled copy of the page already being looked at. Categories
             stay: a genuinely different screen this page has no view of. --}}
        <div class="ao-tx-tabs">
            @if ($urls['category'])
                <a class="ao-mu-tab" href="{{ $urls['category'] }}">Manage Categories</a>
            @endif
            <button type="button" class="ao-mu-tab {{ $filter ? 'ao-on' : '' }}" wire:click="toggleFilter">Search/Filter</button>
        </div>

        @if ($filter)
            <form class="ao-find" autocomplete="off" wire:submit.prevent="$refresh">
                <span class="ao-find-glass" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" width="18" height="18">
                        <circle cx="9" cy="9" r="5.5" /><path d="M13.5 13.5 17 17" />
                    </svg>
                </span>
                <div class="ao-find-fields">
                    <label class="ao-find-field ao-find-grow">
                        <span class="ao-find-label">Article Title</span>
                        <input @nofill type="search" wire:model.live.debounce.500ms="q" placeholder="Search articles">
                    </label>
                </div>
                <button type="submit" class="ao-find-go">Search</button>
            </form>
        @endif

        <p class="ao-pr-crumb">
            You are here:
            @if (!$current)
                Knowledgebase Home
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
    </div>
</x-filament-panels::page>
