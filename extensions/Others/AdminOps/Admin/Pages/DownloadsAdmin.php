<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Paymenter\Extensions\Others\AdminOps\Models\DownloadCategory;
use Paymenter\Extensions\Others\AdminOps\Models\DownloadFile;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Downloads admin, to its screenshot: Add Category and Add Download tabs, the
 * "You are here" breadcrumb, the Categories band and the level's files. Files are stored
 * on the local disk under `downloads/`; staff can fetch them back through the admin-only
 * route the quote PDFs use as their pattern.
 */
class DownloadsAdmin extends Page
{
    use WithFileUploads;

    protected string $view = 'adminops::pages.downloads-admin';

    protected static ?string $slug = 'downloads';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    /** category | download | null — which tab's form is open. */
    public ?string $tab = null;

    #[Url]
    public ?int $category = null;

    public string $newCategory = '';

    public string $newCategoryDescription = '';

    public string $fileTitle = '';

    public string $fileDescription = '';

    public $upload = null;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.tickets.viewAny');
    }

    public function getTitle(): string
    {
        return 'Downloads';
    }

    public function open(string $tab): void
    {
        $this->tab = $this->tab === $tab ? null : $tab;
    }

    public function addCategory(): void
    {
        $this->validate(['newCategory' => 'required|string|max:255'], attributes: ['newCategory' => 'category name']);

        DownloadCategory::create(['name' => $this->newCategory, 'description' => $this->newCategoryDescription ?: null]);
        $this->newCategory = '';
        $this->newCategoryDescription = '';
        $this->tab = null;
        Notification::make()->title('Category added')->success()->send();
    }

    public function addDownload(): void
    {
        $this->validate([
            'fileTitle' => 'required|string|max:255',
            'upload' => 'required|file|max:102400',
        ], attributes: ['fileTitle' => 'title', 'upload' => 'file']);

        $name = \Illuminate\Support\Str::ulid() . '.' . $this->upload->getClientOriginalExtension();
        $this->upload->storeAs('downloads', $name);

        DownloadFile::create([
            'category_id' => $this->category,
            'title' => $this->fileTitle,
            'description' => $this->fileDescription ?: null,
            'filename' => $this->upload->getClientOriginalName(),
            'path' => 'downloads/' . $name,
            'filesize' => $this->upload->getSize(),
            'mime_type' => (string) $this->upload->getMimeType(),
        ]);

        $this->fileTitle = '';
        $this->fileDescription = '';
        $this->upload = null;
        $this->tab = null;
        Notification::make()->title('Download added')->success()->send();
    }

    public function deleteCategory(int $id): void
    {
        $category = DownloadCategory::findOrFail($id);
        $category->files()->update(['category_id' => null]);
        $category->delete();
        Notification::make()->title('Category deleted — its files moved to Download Home')->success()->send();
    }

    public function deleteFile(int $id): void
    {
        $file = DownloadFile::findOrFail($id);
        try {
            \Illuminate\Support\Facades\Storage::delete($file->path);
        } catch (\Throwable $e) {
        }
        $file->delete();
        Notification::make()->title('Download deleted')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'current' => $this->category ? DownloadCategory::find($this->category) : null,
            'categories' => $this->category ? collect() : DownloadCategory::withCount('files')->orderBy('name')->get(),
            'files' => DownloadFile::query()
                ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
                ->when(!$this->category, fn ($q) => $q->whereNull('category_id'))
                ->orderBy('title')->get(),
        ];
    }
}
