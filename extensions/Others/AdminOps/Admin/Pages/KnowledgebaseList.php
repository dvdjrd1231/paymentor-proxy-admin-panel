<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbCategory;

/**
 * Issue #24 — WHMCS's Knowledgebase admin as the navy list: categories, then the level's
 * articles with views and status. Writing articles stays on the Knowledgebase extension's
 * own resource — the Add New buttons link there; publish/unpublish toggles here.
 */
class KnowledgebaseList extends Page
{
    protected string $view = 'adminops::pages.knowledgebase-list';

    protected static ?string $slug = 'kb-overview';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?int $category = null;

    /** Which tab's framed form is open: '', 'category', 'article' or 'search'. */
    public string $tab = '';

    #[Url]
    public string $q = '';

    public string $newCategory = '';

    public string $newCategoryDescription = '';

    public bool $newCategoryHidden = false;

    public string $articleTitle = '';

    public string $articleContent = '';

    public function open(string $tab): void
    {
        $this->tab = $this->tab === $tab ? '' : $tab;
    }

    public function addCategory(): void
    {
        $this->validate(['newCategory' => 'required|string|max:255']);

        KbCategory::create([
            'name' => $this->newCategory,
            'slug' => str($this->newCategory)->slug() . '-' . dechex(crc32($this->newCategory . microtime())),
            'description' => $this->newCategoryDescription,
            'is_active' => !$this->newCategoryHidden,
        ]);

        $this->reset(['newCategory', 'newCategoryDescription', 'newCategoryHidden', 'tab']);
        Notification::make()->title('Category added')->success()->send();
    }

    public function addArticle(): void
    {
        // The reference refuses this at top level with its exact wording; the form is
        // only offered inside a category, so this guard should never fire — belt only.
        if (!$this->category) {
            return;
        }

        $this->validate(['articleTitle' => 'required|string|max:255', 'articleContent' => 'required|string']);

        KbArticle::create([
            'category_id' => $this->category,
            'title' => $this->articleTitle,
            'slug' => str($this->articleTitle)->slug() . '-' . dechex(crc32($this->articleTitle . microtime())),
            'content' => $this->articleContent,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->reset(['articleTitle', 'articleContent', 'tab']);
        Notification::make()->title('Article added')->success()->send();
    }

    public static function canAccess(): bool
    {
        return class_exists(KbArticle::class)
            && (bool) Auth::user()?->hasPermission('admin.tickets.viewAny');
    }

    public function getTitle(): string
    {
        return 'Knowledgebase';
    }

    public function togglePublished(int $id): void
    {
        $article = KbArticle::findOrFail($id);
        $article->update(['is_active' => !$article->is_active]);
        Notification::make()->title($article->is_active ? 'Article published' : 'Article unpublished')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            // A search clears the category walk — searching "the whole knowledgebase" and
            // being handed back only the one category you happened to be in would read as
            // the search having silently failed.
            'categories' => ($this->category && trim($this->q) === '') ? collect() : KbCategory::withCount('articles')->orderBy('name')->get(),
            'current' => ($this->category && trim($this->q) === '') ? KbCategory::find($this->category) : null,
            'articles' => KbArticle::with('category')
                ->when($this->category && trim($this->q) === '', fn ($q) => $q->where('category_id', $this->category))
                ->when(trim($this->q) !== '', fn ($q) => $q->where('title', 'like', '%' . trim($this->q) . '%'))
                ->orderBy('title')->limit(200)->get(),
        ];
    }
}
