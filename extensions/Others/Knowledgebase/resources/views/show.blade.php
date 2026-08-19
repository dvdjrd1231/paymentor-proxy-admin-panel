{{-- A single knowledgebase article, with the rest of its category alongside. --}}
<div class="wf-page">
    <div class="wf-pagehead">
        <h1>{{ $article->title }}</h1>
        @if ($article->description)<p>{{ $article->description }}</p>@endif
    </div>

    <div class="wf-crumb">
        <a href="{{ route('home') }}" wire:navigate>{{ __('theme.portal_home') }}</a>
        <span>/</span><a href="{{ route('knowledgebase.index') }}" wire:navigate>{{ __('knowledgebase.title') }}</a>
        @if ($article->category)
            <span>/</span><a href="{{ route('knowledgebase.category', $article->category->slug) }}" wire:navigate>{{ $article->category->name }}</a>
        @endif
    </div>

    <div class="wf-layout wf-layout--reverse">
        <div>
            <div class="wf-panel">
                <div class="wf-panel-body">
                    {{-- Article bodies are written by staff in the admin, so HTML is intended. --}}
                    <article class="prose dark:prose-invert">{!! $article->content !!}</article>
                </div>
                <div class="wf-panel-foot">
                    {{ trans_choice('knowledgebase.views', $article->views, ['count' => $article->views]) }}
                </div>
            </div>
        </div>

        <div>
            @if ($related->count())
                <div class="wf-panel">
                    <div class="wf-panel-heading">{{ __('knowledgebase.related') }}</div>
                    <ul class="wf-list">
                        @foreach ($related as $r)
                            <li>
                                <a href="{{ route('knowledgebase.show', $r->slug) }}" wire:navigate>
                                    <span>{{ $r->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
