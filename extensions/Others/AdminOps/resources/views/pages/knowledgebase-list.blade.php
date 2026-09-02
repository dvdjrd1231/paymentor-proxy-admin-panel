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
        </div>

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
