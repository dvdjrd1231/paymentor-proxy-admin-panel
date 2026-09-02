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
        // "Manage Articles" used to be built here too — core's own KbArticleResource list,
        // a second, un-styled copy of this very page. Categories is kept: a genuinely
        // different screen, with no view of its own in this page.
        $urls = ['category' => null];
        try {
            $urls['category'] = \Paymenter\Extensions\Others\Knowledgebase\Admin\Resources\KbCategoryResource::getUrl('index');
        } catch (\Throwable $e) {
        }

        return [
            'categories' => $this->category ? collect() : KbCategory::withCount('articles')->orderBy('name')->get(),
            'current' => $this->category ? KbCategory::find($this->category) : null,
            'articles' => KbArticle::with('category')
                ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
                ->orderBy('title')->limit(200)->get(),
            'urls' => $urls,
        ];
    }
}
