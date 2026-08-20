{{-- Knowledgebase — category rail beside a searchable article list, matching the
     reference portal's layout. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ __('knowledgebase.title') }}</h1>
        <p>{{ __('knowledgebase.subtitle') }}</p>
    </div>

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span>{{ __('knowledgebase.title') }}
    </div>

    <div class="wf-layout">
        <div>
            <div class="wf-panel wf-panel--brand">
                <div class="wf-panel-heading">
                    <span><span class="wf-head-icon"><x-ri-links-fill /></span>{{ __('theme.categories') }}</span>
                    <span class="wf-chevron">&#9650;</span>
                </div>
                <ul class="wf-list">
                    <li>
                        <a href="{{ route('knowledgebase.index') }}" wire:navigate
                           class="{{ $activeCategory ? '' : 'is-active' }}">
                            <span>{{ __('knowledgebase.all_articles') }}</span>
                        </a>
                    </li>
                    @foreach ($categories as $c)
                        <li>
                            <a href="{{ route('knowledgebase.category', $c->slug) }}" wire:navigate
                               class="{{ $activeCategory === $c->slug ? 'is-active' : '' }}">
                                <span>{{ $c->name }}</span>
                                <span class="wf-label">{{ $c->articles_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <x-support-rail active="knowledgebase" />
        </div>

        <div>
            <div class="wf-panel">
                <div class="wf-panel-body">
                    <input type="search" class="wf-input" wire:model.live.debounce.300ms="q"
                           placeholder="{{ __('knowledgebase.search_placeholder') }}"
                           aria-label="{{ __('knowledgebase.search_placeholder') }}">
                </div>
            </div>

            <div class="wf-panel">
                <div class="wf-panel-heading">{{ __('knowledgebase.articles') }}</div>
                <ul class="wf-list">
                    @forelse ($articles as $article)
                        <li>
                            <a href="{{ route('knowledgebase.show', $article->slug) }}" wire:navigate>
                                <span style="min-width:0">
                                    <span class="wf-list-title">{{ $article->title }}</span>
                                    <span class="wf-list-sub">
                                        {{ $article->category?->name }}
                                        @if ($article->description) &middot; {{ $article->description }} @endif
                                    </span>
                                </span>
                                <span class="wf-label">{{ trans_choice('knowledgebase.views', $article->views, ['count' => $article->views]) }}</span>
                            </a>
                        </li>
                    @empty
                        <li><div class="wf-alert wf-alert--notice">{{ __('knowledgebase.no_articles') }}</div></li>
                    @endforelse
                </ul>
            </div>

            {{ $articles->links() }}
        </div>
    </div>
</div>
