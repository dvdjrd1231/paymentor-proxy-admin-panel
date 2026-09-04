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

    /** category | download | null — which tab's form is open. URL-bound so the Add
     *  Download form is linkable, like every other panel toggle. */
    #[Url]
    public ?string $tab = null;

    #[Url]
    public ?int $category = null;

    public string $newCategory = '';

    public string $newCategoryDescription = '';

    /** The reference's "Check to Hide" beside the category name. */
    public bool $newCategoryHidden = false;

    /** The reference's Type select — a display grouping, stored as chosen. */
    public string $fileType = 'zip';

    public string $fileTitle = '';

    public string $fileDescription = '';

    /** upload | manual — the reference's two Upload File radios; Manual FTP is the one
     *  it ships selected (Leandro's screenshot, 2026-09-04). */
    public string $source = 'manual';

    /** Manual FTP upload: the name of a file already placed in `downloads/`. */
    public string $manualFilename = '';

    /** Ticked by default, as the reference ships it. */
    public bool $clientsOnly = true;

    public bool $productDownload = false;

    public bool $fileHidden = false;

    public $upload = null;

    /** The reference's Type options, as WHMCS lists them. */
    public const TYPES = [
        'zip' => 'ZIP File',
        'msi' => 'MSI File',
        'exe' => 'EXE File',
        'pdf' => 'PDF File',
        'word' => 'Word Document',
        'excel' => 'Excel Spreadsheet',
        'image' => 'Image File',
        'video' => 'Video File',
        'audio' => 'Audio File',
        'other' => 'Other',
    ];

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

        DownloadCategory::create([
            'name' => $this->newCategory,
            'description' => $this->newCategoryDescription ?: null,
            'hidden' => $this->newCategoryHidden,
        ]);
        $this->reset(['newCategory', 'newCategoryDescription', 'newCategoryHidden']);
        $this->tab = null;
        Notification::make()->title('Category added')->success()->send();
    }

    public function addDownload(): void
    {
        $this->validate([
            'fileTitle' => 'required|string|max:255',
            'fileType' => 'required|in:' . implode(',', array_keys(self::TYPES)),
        ], attributes: ['fileTitle' => 'title']);

        // The reference's two sources: a browser upload, or a file already placed in the
        // downloads folder by hand (its "Manual FTP Upload") — registered here by name.
        if ($this->source === 'manual') {
            $this->validate(['manualFilename' => 'required|string|max:255'], attributes: ['manualFilename' => 'filename']);
            $name = basename(trim($this->manualFilename));

            if (!\Illuminate\Support\Facades\Storage::exists('downloads/' . $name)) {
                $this->addError('manualFilename', 'No file named "' . $name . '" exists in the downloads folder — upload it there first, or use Upload File.');

                return;
            }

            $path = 'downloads/' . $name;
            $original = $name;
            $size = \Illuminate\Support\Facades\Storage::size($path);
            $mime = \Illuminate\Support\Facades\Storage::mimeType($path) ?: null;
        } else {
            $this->validate(['upload' => 'required|file|max:102400'], attributes: ['upload' => 'file']);

            $name = \Illuminate\Support\Str::ulid() . '.' . $this->upload->getClientOriginalExtension();
            $this->upload->storeAs('downloads', $name);
            $path = 'downloads/' . $name;
            $original = $this->upload->getClientOriginalName();
            $size = $this->upload->getSize();
            $mime = (string) $this->upload->getMimeType();
        }

        DownloadFile::create([
            'category_id' => $this->category,
            'type' => $this->fileType,
            'title' => $this->fileTitle,
            'description' => $this->fileDescription ?: null,
            'filename' => $original,
            'path' => $path,
            'filesize' => $size,
            'mime_type' => $mime,
            'clients_only' => $this->clientsOnly,
            'product_download' => $this->productDownload,
            'hidden' => $this->fileHidden,
        ]);

        $this->resetDownloadForm();
        $this->tab = null;
        Notification::make()->title('Download added')->success()->send();
    }

    /** The reference's Cancel Changes: clear the form and close the tab. */
    public function cancelChanges(): void
    {
        $this->resetDownloadForm();
        $this->reset(['newCategory', 'newCategoryDescription', 'newCategoryHidden']);
        $this->tab = null;
    }

    private function resetDownloadForm(): void
    {
        $this->reset(['fileType', 'fileTitle', 'fileDescription', 'source', 'manualFilename',
            'clientsOnly', 'productDownload', 'fileHidden', 'upload']);
    }

    /**
     * The reference's red note states the server's real ceiling. Ours is whichever is
     * smallest of PHP's two ini limits and the validator's own 100 MB cap.
     */
    public static function uploadLimit(): string
    {
        $bytes = fn (string $v): int => match (strtoupper(substr(trim($v), -1))) {
            'G' => (int) $v * 1024 ** 3,
            'M' => (int) $v * 1024 ** 2,
            'K' => (int) $v * 1024,
            default => (int) $v,
        };

        $limit = min(
            $bytes((string) ini_get('upload_max_filesize')),
            $bytes((string) ini_get('post_max_size')),
            100 * 1024 ** 2,
        );

        return $limit >= 1024 ** 3
            ? round($limit / 1024 ** 3, 1) . 'G'
            : round($limit / 1024 ** 2) . 'MB';
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
