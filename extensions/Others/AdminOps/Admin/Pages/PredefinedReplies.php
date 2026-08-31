<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\TicketResource;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Models\PredefinedReplyCategory;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\TicketTools\Models\CannedResponse;

/**
 * WHMCS's Predefined Replies, to its screenshot: the three tabs — Add Category, Add
 * Predefined Reply, Search/Filter — the "You are here" breadcrumb, and the category
 * listing. The replies are TicketTools' canned responses (their `department` column holds
 * the category name), so everything added here is immediately usable from Open New Ticket's
 * "Insert Predefined Reply" and from core's ticket screen.
 */
class PredefinedReplies extends Page
{
    protected string $view = 'adminops::pages.predefined-replies';

    protected static ?string $slug = 'predefined-replies';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** Which tab's form is open: category | reply | search | null. */
    public ?string $tab = null;

    #[Url]
    public string $category = '';

    #[Url]
    public string $q = '';

    public string $newCategory = '';

    public string $replyTitle = '';

    public string $replyBody = '';

    public static function canAccess(): bool
    {
        return TicketResource::canViewAny() && class_exists(CannedResponse::class);
    }

    public function getTitle(): string
    {
        return 'Predefined Replies';
    }

    public function open(string $tab): void
    {
        $this->tab = $this->tab === $tab ? null : $tab;
    }

    public function addCategory(): void
    {
        $this->validate(['newCategory' => 'required|string|max:255|unique:ext_predefined_reply_categories,name'],
            attributes: ['newCategory' => 'category name']);

        PredefinedReplyCategory::create(['name' => $this->newCategory]);
        $this->newCategory = '';
        $this->tab = null;
        Notification::make()->title('Category added')->success()->send();
    }

    public function addReply(): void
    {
        $this->validate([
            'replyTitle' => 'required|string|max:255',
            'replyBody' => 'required|string',
        ], attributes: ['replyTitle' => 'reply name', 'replyBody' => 'reply text']);

        CannedResponse::create([
            'title' => $this->replyTitle,
            'body' => $this->replyBody,
            'department' => $this->category ?: null,
            'active' => true,
        ]);

        $this->replyTitle = '';
        $this->replyBody = '';
        $this->tab = null;
        Notification::make()->title('Predefined reply added')->success()->send();
    }

    public function deleteCategory(int $id): void
    {
        $category = PredefinedReplyCategory::findOrFail($id);
        CannedResponse::where('department', $category->name)->update(['department' => null]);
        $category->delete();
        Notification::make()->title('Category deleted — its replies moved to Top Level')->success()->send();
    }

    public function deleteReply(int $id): void
    {
        CannedResponse::findOrFail($id)->delete();
        Notification::make()->title('Reply deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        $replies = CannedResponse::query()
            ->when($this->q !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%' . $this->q . '%')
                ->orWhere('body', 'like', '%' . $this->q . '%')))
            ->when($this->q === '', fn ($q) => $this->category === ''
                ? $q->whereNull('department')
                : $q->where('department', $this->category))
            ->orderBy('title')
            ->get();

        return [
            'categories' => $this->category === '' && $this->q === ''
                ? PredefinedReplyCategory::orderBy('name')->get()
                : collect(),
            'replies' => $replies,
        ];
    }
}
