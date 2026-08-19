<?php

namespace Paymenter\Extensions\Others\Knowledgebase;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase\Index;
use Paymenter\Extensions\Others\Knowledgebase\Livewire\Knowledgebase\Show;
use Paymenter\Extensions\Others\Knowledgebase\Models\KbArticle;

/**
 * A searchable knowledgebase, one of the client-area sections the reference portal has and
 * Paymenter does not.
 *
 * Categories hold articles; an article is visible only when it is active *and* has a
 * `published_at` in the past, so staff can write ahead of time. Views are counted so the
 * list can lead with what customers actually read.
 *
 * The menu entry only appears once an article is published — an empty section in the
 * navigation is worse than no section, and it is the same rule Announcements uses.
 *
 * @link docs/modules/knowledgebase.md
 */
#[ExtensionMeta(
    name: 'Knowledgebase',
    description: 'Searchable help articles grouped into categories, shown in the client area.',
    version: '1.0.0',
    author: 'Paymenter Proxy Platform',
)]
class Knowledgebase extends Extension
{
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString(
                    'Adds a <b>Knowledgebase</b> to the client area at <code>/knowledgebase</code>. '
                    . 'Manage categories and articles from the admin navigation once enabled. '
                    . 'The menu entry appears as soon as one article is published.'
                ),
            ],
        ];
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/Knowledgebase/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/Knowledgebase/database/migrations');
    }

    public function boot()
    {
        require __DIR__ . '/routes/web.php';

        View::addNamespace('knowledgebase', __DIR__ . '/resources/views');

        // Filament discovers resources under an extension's Admin/Resources directory, the
        // same way Announcements and TicketTools expose theirs.

        Livewire::component('knowledgebase.index', Index::class);
        Livewire::component('knowledgebase.show', Show::class);

        Event::listen('navigation', function () {
            // Nothing published yet — leave the menu alone rather than link to an empty page.
            if (!$this->hasPublishedArticles()) {
                return;
            }

            return [
                'name' => __('knowledgebase.title'),
                'route' => 'knowledgebase.index',
                'icon' => 'ri-question-line',
            ];
        });
    }

    /**
     * Guarded because `boot()` runs on every request, including during `migrate` before the
     * table exists and while the extension is being installed — an exception there would
     * take down the whole application rather than just this menu entry.
     */
    private function hasPublishedArticles(): bool
    {
        try {
            return KbArticle::published()->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
