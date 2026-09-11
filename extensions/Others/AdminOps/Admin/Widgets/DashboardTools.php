<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Renderless;
use Paymenter\Extensions\Others\AdminOps\Models\DashboardLayout;

/**
 * The reference's dashboard chrome: every panel draggable, collapsible, refreshable and
 * hideable, with a settings menu to bring the hidden ones back.
 */
class DashboardTools extends Widget
{
    protected string $view = 'adminops::widgets.dashboard-tools';

    /** Before every other widget, including the tiles. */
    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    /** Rendered with the page, not fetched afterwards — and that is the whole feature. */
    protected static bool $isLazy = false;

    /**
     * The migration ships with this extension, so a panel that has AdminOps enabled but has
     * not re-run `installed()` would fatal on every dashboard load without this check —
     * which is the one screen you cannot navigate away from. Without the table the
     * dashboard is simply the stock one.
     */
    public static function canView(): bool
    {
        return Auth::check() && Schema::hasTable('ext_adminops_dashboard_layouts');
    }

    /**
     * @return array{order: array<int, string>, hidden: array<int, string>}
     */
    public function getLayout(): array
    {
        $layout = DashboardLayout::forUser(Auth::id());

        return [
            'order' => $layout->order ?? [],
            'hidden' => $layout->hidden ?? [],
        ];
    }

    /**
     * Called after a drop. Takes the whole order, as the reference does.
     *
     * @param  array<int, string>  $order
     */
    #[Renderless]
    public function saveOrder(array $order): void
    {
        $this->store(['order' => $this->clean($order)]);
    }

    /**
     * Put a widget away, or bring it back — the reference's × and its settings checkboxes,
     * which are two ways to the same toggle.
     */
    #[Renderless]
    public function toggleHidden(string $widget): void
    {
        $hidden = DashboardLayout::forUser(Auth::id())->hidden ?? [];

        $hidden = in_array($widget, $hidden, true)
            ? array_values(array_diff($hidden, [$widget]))
            : [...$hidden, $widget];

        $this->store(['hidden' => $this->clean($hidden)]);
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    private function store(array $attributes): void
    {
        DashboardLayout::updateOrCreate(['user_id' => Auth::id()], $attributes);
    }

    /**
     * Livewire hands over whatever the browser sent. Strings only, no duplicates, and a
     * length that cannot be used to grow the row without bound.
     *
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    private function clean(array $keys): array
    {
        return collect($keys)
            ->filter(fn ($key): bool => is_string($key) && $key !== '' && strlen($key) <= 255)
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }
}
