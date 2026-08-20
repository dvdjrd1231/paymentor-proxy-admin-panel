<?php

namespace Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbCategory;

class Index extends Component
{
    use WithPagination;

    /**
     * Bound to the search box; `live` on the input keeps results updating as you type.
     *
     * Mirrored to `?q=` so the dashboard's knowledgebase search box can hand a question
     * straight to this page as a plain GET, and so a result list stays shareable.
     */
    #[Url(except: '')]
    public string $q = '';

    public ?string $category = null;

    public function mount(?string $category = null): void
    {
        $this->category = $category;
    }

    /** Reset paging when the query changes, or page 3 of the old search shows nothing. */
    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $articles = KbArticle::published()->with('category');

        if ($this->category) {
            $articles->whereHas('category', fn ($c) => $c->where('slug', $this->category));
        }

        if (trim($this->q) !== '') {
            $term = '%' . trim($this->q) . '%';
            $articles->where(fn ($w) => $w->where('title', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('content', 'like', $term));
        }

        return view('knowledgebase::index', [
            'articles' => $articles->orderByDesc('views')->orderByDesc('published_at')->paginate(10),
            'categories' => KbCategory::where('is_active', true)
                ->withCount(['publishedArticles as articles_count'])
                ->orderBy('sort')->orderBy('name')->get(),
            'activeCategory' => $this->category,
        ]);
    }
}
