<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase;

use Livewire\Component;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle;

class Show extends Component
{
    public KbArticle $article;

    public function mount(KbArticle $article): void
    {
        // Route-model binding resolves by slug regardless of state, so an unpublished
        // article would otherwise be readable by anyone who guessed the URL.
        abort_unless(
            $article->is_active && $article->published_at && $article->published_at <= now(),
            404
        );

        $this->article = $article;

        // increment() writes straight to the database without touching updated_at, so
        // reading an article does not make it look freshly edited.
        $article->increment('views');
    }

    public function render()
    {
        return view('knowledgebase::show', [
            'related' => KbArticle::published()
                ->where('category_id', $this->article->category_id)
                ->where('id', '!=', $this->article->id)
                ->orderByDesc('views')->limit(5)->get(),
        ]);
    }
}
