<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Paymenter\Extensions\Others\AdminOps\Models\DashboardLayout;

/**
 * The reference's dashboard chrome: every panel draggable, collapsible, refreshable and
 * hideable, with a settings menu to bring the hidden ones back.
 *
 * Leandro's note — *"All items on the WHMCS admin home screen can be moved using
 * drag-and-drop"* — is the whole of this. The reference does it with Packery plus
 * Draggabilly, dragging by the panel title, saving the order to `/widget/order` a second
 * after the drop, keeping hidden widgets server-side per admin and collapsed ones in
 * `localStorage`. Same four behaviours, same division between what is remembered where.
 *
 * ## Why a widget and not a page
 *
 * The obvious implementation is to replace the dashboard page, order the widgets in PHP and
 * be done. That means telling the panel which class its dashboard is, which is a
 * construction-time call — a core edit, and a twelfth touchpoint on the one screen every
 * admin lands on. This is a widget instead: it renders no panel of its own, it sorts before
 * everything else, and it carries the script and the two methods the script calls. Disable
 * AdminOps and the dashboard is stock Filament again, in Filament's own order.
 *
 * The cost of that choice is honest and small: the saved order is applied by JavaScript
 * after the widgets have painted, so a reordered dashboard settles into place rather than
 * arriving in place. Nothing is hidden while that happens — a dashboard that stays blank
 * because a script failed is far worse than one that reshuffles once.
 *
 * ## What identifies a widget
 *
 * Its Livewire component name. Filament renders each widget as its own Livewire component,
 * and the name is derived from the class, so it is the same string on every page load and
 * on every machine — which the DOM id, the render key and the position are not.
 *
 * ## Which panels do not move, and why that is decided in the browser
 *
 * The tile row and this widget are stamped, as the reference stamps its static panel. That
 * used to be resolved here, by asking Livewire's registry for each class's name — and it
 * was wrong: `Livewire\Mechanisms\ComponentRegistry` is Livewire **3**, this is Livewire 4,
 * and the container threw `Target class does not exist` while rendering the dashboard.
 *
 * There is no stable class-to-name API in Livewire 4 to replace it with, so the question is
 * now answered structurally in the browser instead: a panel is static if it *is* this
 * widget, or if it contains the tile row's own markup. That needs no Livewire internals at
 * all, and it cannot break again on the next major.
 */
class DashboardTools extends Widget
{
    protected string $view = 'adminops::widgets.dashboard-tools';

    /**
     * Before every other widget, including the tiles.
     *
     * It has to render before the panels it decorates so its script is in the document when
     * they are, and being first also puts the settings menu where the reference has it —
     * top right, above the grid.
     */
    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    /**
     * Rendered with the page, not fetched afterwards — and that is the whole feature.
     *
     * Filament widgets are lazy by default: the first response carries a placeholder, and the
     * real markup arrives later and is *morphed* into the document. Livewire does not execute
     * a plain `<script>` that arrives that way. This widget is almost entirely a script, so
     * lazy meant it silently never ran — the dashboard rendered exactly as stock, with no
     * error anywhere to say why.
     *
     * It has nothing to defer in any case: it queries no tables of its own beyond one small
     * row of saved layout, so there is no slow work that lazy loading would be buying time for.
     */
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
     * The list is not validated against the widgets that exist: it is a display preference
     * for the person who sent it, about their own screen, and a name that no longer matches
     * a widget is ignored at render. There is nothing here to forge — the worst a bad list
     * can do is disarrange the sender's own dashboard, which they can do with the mouse.
     *
     * @param  array<int, string>  $order
     */
    public function saveOrder(array $order): void
    {
        $this->store(['order' => $this->clean($order)]);
    }

    /**
     * Put a widget away, or bring it back — the reference's × and its settings checkboxes,
     * which are two ways to the same toggle.
     */
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
