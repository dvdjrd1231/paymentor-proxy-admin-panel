<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;
use Paymenter\Extensions\Others\Announcements\Models\Announcement;

/**
 * WHMCS's Announcements admin, to its screenshots: the Add Announcement form — Date,
 * Title, body, Published? — Save Changes, and the listing with Date / Title / Published
 * and the edit and delete icons. Stored in the Announcements extension's own table, so
 * everything published here appears in the client portal exactly as before.
 */
class AnnouncementsAdmin extends Page
{
    protected string $view = 'adminops::pages.announcements-admin';

    // Not 'announcements': the Announcements extension's resource already owns that path,
    // and two claimants means this page silently loses the route.
    protected static ?string $slug = 'support-announcements';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public const PER_PAGE = 50;

    /** list | form */
    #[Url]
    public string $mode = 'list';

    #[Url]
    public ?int $editing = null;

    #[Url]
    public int $page = 1;

    public string $date = '';

    public string $headline = '';   // $title is Filament's

    public string $body = '';

    public bool $published = false;

    public static function canAccess(): bool
    {
        return class_exists(Announcement::class)
            && (bool) Auth::user()?->hasPermission('admin.tickets.viewAny');
    }

    public function getTitle(): string
    {
        return 'Announcements';
    }

    public function mount(): void
    {
        if ($this->mode === 'form') {
            $this->openForm($this->editing);
        }
    }

    public function openForm(?int $id = null): void
    {
        $this->mode = 'form';
        $this->editing = $id;

        $row = $id ? Announcement::findOrFail($id) : null;
        $this->date = ($row?->published_at ?? now())->format('Y-m-d\TH:i');
        $this->headline = $row->title ?? '';
        $this->body = $row->content ?? '';
        $this->published = (bool) ($row->is_active ?? false);
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->editing = null;
    }

    public function save(): void
    {
        $this->validate([
            'date' => 'required|date',
            'headline' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $values = [
            'title' => $this->headline,
            'content' => $this->body,
            'description' => Str::limit(strip_tags($this->body), 180),
            'published_at' => $this->date,
            'is_active' => $this->published,
        ];

        if ($this->editing) {
            Announcement::findOrFail($this->editing)->update($values);
        } else {
            Announcement::create($values + ['slug' => Str::slug($this->headline) . '-' . Str::lower(Str::random(6))]);
        }

        Notification::make()->title('Changes Saved Successfully!')->success()->send();
        $this->backToList();
    }

    public function delete(int $id): void
    {
        Announcement::findOrFail($id)->delete();
        Notification::make()->title('Announcement deleted')->success()->send();
    }

    public function togglePublished(int $id): void
    {
        $row = Announcement::findOrFail($id);
        $row->update(['is_active' => !$row->is_active]);
    }

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
    }

    protected function getViewData(): array
    {
        return [
            'rows' => Announcement::orderByDesc('published_at')->orderByDesc('id')
                ->paginate(self::PER_PAGE, page: $this->page),
        ];
    }
}
