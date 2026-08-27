{{--
    Styling for the AdminOps dashboard widgets and the client summary.

    Plain CSS, not Tailwind utilities, on purpose. The admin theme
    (`resources/css/filament/admin/theme.css`) only scans `app/Admin` and `resources/views`
    for classes — `extensions/` is not a `@source` — so any utility class written in an
    extension view would simply not exist in the compiled stylesheet. Adding the path
    would be a core edit *and* would make every deployment depend on an admin CSS rebuild.
    This has neither problem: it ships with the extension and needs no build step.

    Colours are the custom properties the panel already publishes into `<head>` (see
    AdminPanelProvider's `panels::head.end` hook, which renders the active theme's
    colors.blade.php), so these widgets follow the store's palette and dark mode without
    knowing anything about either.
--}}
<style>
    .ao-panel {
        color: hsl(var(--color-base));
        font-size: 0.875rem;
    }

    .ao-panel a {
        color: inherit;
        text-decoration: none;
    }

    /* --- Headline tiles: the reference's four-across row at the top of the homepage ---

       Unlike the queue below, a tile always shows its figure, zero included: the value of a
       fixed row is that the same number is always in the same place. `auto-fit` rather than
       a fixed four columns so the row reflows on a narrow window instead of overflowing —
       and so it still looks deliberate when ProvisioningOps is absent and there are only
       three of them. */

    .ao-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
        gap: 0.75rem;
    }

    .ao-tile {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        padding: 1rem 1.15rem;
        border-radius: 0.75rem;
        color: hsl(var(--color-inverted));
        background-color: hsl(var(--color-inactive));
        transition: filter 120ms ease;
    }

    .ao-tile:hover {
        filter: brightness(1.06);
    }

    .ao-tile-success { background-color: hsl(var(--color-success)); }
    .ao-tile-warning { background-color: hsl(var(--color-warning)); }
    .ao-tile-info    { background-color: hsl(var(--color-info)); }
    .ao-tile-brand   { background-color: hsl(var(--color-primary)); }

    .ao-tile-icon {
        flex: none;
        width: 2.25rem;
        height: 2.25rem;
        opacity: 0.85;
    }

    .ao-tile-count {
        display: block;
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
    }

    .ao-tile-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 500;
        opacity: 0.9;
    }

    /* --- Who is around: staff on the desk, and store activity --- */

    .ao-around {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
        gap: 0.75rem 2rem;
    }

    .ao-around .ao-field-value + .ao-field-value {
        margin-top: 0.4rem;
    }

    /* --- At a glance: periods across, measures down (WHMCS's Overview panel) --- */

    .ao-glance {
        width: 100%;
        border-collapse: collapse;
    }

    .ao-glance th,
    .ao-glance td {
        padding: 0.5rem 0.75rem;
        text-align: right;
        white-space: nowrap;
    }

    .ao-glance th:first-child,
    .ao-glance td:first-child {
        text-align: left;
        white-space: normal;
    }

    .ao-glance thead th {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: hsl(var(--color-muted));
        border-bottom: 1px solid hsl(var(--color-neutral));
    }

    .ao-glance tbody tr + tr td {
        border-top: 1px solid hsl(var(--color-neutral) / 0.5);
    }

    .ao-glance tbody th {
        font-weight: 500;
        color: hsl(var(--color-muted));
    }

    .ao-glance-value {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .ao-glance-income .ao-glance-value {
        color: hsl(var(--color-success));
    }

    .ao-glance-zero {
        color: hsl(var(--color-muted));
        font-weight: 400;
    }

    /* --- Action queue: what needs doing, most urgent first --- */

    .ao-queue {
        display: flex;
        flex-direction: column;
    }

    .ao-queue-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0.25rem;
        border-radius: 0.375rem;
        transition: background-color 120ms ease;
    }

    .ao-queue-row + .ao-queue-row {
        border-top: 1px solid hsl(var(--color-neutral) / 0.5);
    }

    .ao-queue-row:hover {
        background-color: hsl(var(--color-neutral) / 0.35);
    }

    .ao-queue-count {
        flex: none;
        min-width: 2.75rem;
        padding: 0.125rem 0.5rem;
        border-radius: 0.375rem;
        text-align: center;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        color: hsl(var(--color-inverted));
        background-color: hsl(var(--color-inactive));
    }

    .ao-queue-row-danger .ao-queue-count {
        background-color: hsl(var(--color-error));
    }

    .ao-queue-row-warning .ao-queue-count {
        background-color: hsl(var(--color-warning));
    }

    .ao-queue-row-info .ao-queue-count {
        background-color: hsl(var(--color-info));
    }

    .ao-queue-label {
        font-weight: 500;
    }

    .ao-queue-note {
        display: block;
        font-size: 0.75rem;
        font-weight: 400;
        color: hsl(var(--color-muted));
    }

    .ao-queue-go {
        margin-left: auto;
        flex: none;
        font-size: 0.75rem;
        color: hsl(var(--color-muted));
    }

    .ao-empty {
        padding: 1.25rem 0.25rem;
        text-align: center;
        color: hsl(var(--color-muted));
    }

    /* --- Client profile tabs ---
       The reference's tab strip: a row of plain buttons on a rule, the active one carrying
       the rule's colour and a heavier weight. Scrolls sideways rather than wrapping, because
       a tab bar that becomes two rows moves every tab under the pointer. */
    .ao-tabs {
        display: flex;
        gap: 0.15rem;
        overflow-x: auto;
        border-bottom: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
        margin-bottom: 1rem;
    }

    .ao-tab {
        flex: none;
        padding: 0.5rem 0.85rem;
        border-bottom: 2px solid transparent;
        font-size: 0.85rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.65));
        white-space: nowrap;
        cursor: pointer;
    }

    .ao-tab:hover {
        color: hsl(var(--color-base));
        background: hsl(var(--color-base) / 0.04);
    }

    .ao-tab-active {
        font-weight: 600;
        color: hsl(var(--color-primary));
        border-bottom-color: hsl(var(--color-primary));
    }

    /* --- Client summary --- */

    .ao-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        gap: 0.75rem 1.5rem;
    }

    .ao-field-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: hsl(var(--color-muted));
    }

    .ao-field-value {
        font-weight: 500;
        overflow-wrap: anywhere;
    }

    .ao-list {
        width: 100%;
        border-collapse: collapse;
    }

    .ao-list th,
    .ao-list td {
        padding: 0.45rem 0.6rem;
        text-align: left;
    }

    .ao-list thead th {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: hsl(var(--color-muted));
        border-bottom: 1px solid hsl(var(--color-neutral));
    }

    .ao-list tbody tr + tr td {
        border-top: 1px solid hsl(var(--color-neutral) / 0.5);
    }

    .ao-list tbody tr:hover {
        background-color: hsl(var(--color-neutral) / 0.35);
    }

    .ao-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .ao-tag {
        display: inline-block;
        padding: 0.05rem 0.45rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        color: hsl(var(--color-inverted));
        background-color: hsl(var(--color-inactive));
    }

    .ao-tag-success { background-color: hsl(var(--color-success)); }
    .ao-tag-danger  { background-color: hsl(var(--color-error)); }
    .ao-tag-warning { background-color: hsl(var(--color-warning)); }
    .ao-tag-info    { background-color: hsl(var(--color-info)); }

    .ao-link {
        color: hsl(var(--color-primary));
        font-weight: 500;
    }

    .ao-link:hover {
        text-decoration: underline;
    }

    /* --- Dashboard chrome: drag, collapse, refresh, hide ---

       The reference puts three small icons in the top-right of every panel heading and a
       settings button above the grid. These style what `widgets/dashboard-tools.blade.php`
       adds to Filament's own widget markup at runtime — so the selectors are ours, not
       Filament's, and nothing here can break a widget that this script never reached. */
    .ao-dash-tools {
        display: flex;
        justify-content: flex-end;
    }

    /* Once the gear has been lifted into the page header, the grid row this widget occupied
       is empty — and an empty row above the tiles reads as a rendering fault rather than as
       nothing. Collapsed rather than `display: none` so the widget's own Livewire root stays
       in the document: removing it from layout is enough, and hiding it entirely would take
       the component that owns the checkboxes with it. */
    .fi-wi-widget.ao-dash-collapsed {
        display: block;
        height: 0;
        margin: 0;
        overflow: hidden;
    }

    /* The reference's position: top right, level with the page heading. */
    .fi-header.ao-dash-header,
    .fi-page-header.ao-dash-header {
        position: relative;
    }

    .ao-dash-header .ao-dash-settings {
        position: absolute;
        inset-inline-end: 0;
        top: 0;
    }

    .ao-dash-settings {
        position: relative;
    }

    .ao-dash-settings-button {
        display: flex;
        align-items: center;
        padding: 0.3rem;
        border-radius: 3px;
        color: hsl(var(--color-base) / 0.55);
        cursor: pointer;
    }

    .ao-dash-settings-button:hover {
        color: hsl(var(--color-base));
        background: hsl(var(--color-base) / 0.06);
    }

    .ao-dash-menu {
        position: absolute;
        inset-inline-end: 0;
        top: 100%;
        z-index: 20;
        min-width: 15rem;
        padding: 0.6rem 0.75rem;
        border: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        border-radius: var(--wa-radius, 3px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
        box-shadow: 0 4px 14px rgb(0 0 0 / 0.12);
        font-size: 0.85rem;
    }

    .ao-dash-menu h4 {
        margin-bottom: 0.4rem;
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    .ao-dash-menu ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .ao-dash-menu label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        cursor: pointer;
    }

    .ao-dash-menu-note {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
        font-size: 0.75rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    /* The heading is the drag handle, so it says so on hover — `grab`, not `move`, because
       nothing moves until the pointer goes down. */
    .ao-wi-header {
        position: relative;
        cursor: grab;
    }

    .ao-wi-header:focus-visible {
        outline: 2px solid hsl(var(--color-primary));
        outline-offset: 2px;
    }

    /* Filament's section headers are flex rows, so the tools land at the end of one without
       being positioned. The `margin-inline-start: auto` is what pins them right in the bar
       this script draws for a widget that has no header of its own. */
    .ao-wi-tools {
        display: flex;
        align-items: center;
        gap: 0.15rem;
        margin-inline-start: auto;
    }

    .ao-wi-tool {
        padding: 0.15rem 0.3rem;
        border-radius: 2px;
        line-height: 1;
        font-size: 0.8rem;
        color: hsl(var(--color-base) / 0.45);
        cursor: pointer;
    }

    .ao-wi-tool:hover {
        color: hsl(var(--color-base));
        background: hsl(var(--color-base) / 0.06);
    }

    .ao-wi-spin span[aria-hidden] {
        display: inline-block;
        animation: ao-spin 0.7s linear infinite;
    }

    @keyframes ao-spin {
        to { transform: rotate(360deg); }
    }

    .ao-wi-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.75rem;
        border-bottom: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    .ao-wi-dragging {
        opacity: 0.45;
    }

    .ao-wi-hidden {
        display: none;
    }

    /* Collapsed: the heading stays, everything under it goes. `:not()` keeps the header —
       whichever of the three kinds it is — so the panel can be rolled back open. */
    .ao-wi-collapsed .fi-sc-section-content,
    .ao-wi-collapsed .fi-wi-chart-chart,
    .ao-wi-collapsed .fi-wi-stats-overview-stats-ctn {
        display: none;
    }

    .ao-wi-collapsed .ao-wi-tool span[aria-hidden] {
        /* The chevron points the other way once there is nothing under it. */
        display: inline-block;
    }

    .ao-wi-collapsed [title^="Collapse"] span[aria-hidden] {
        transform: rotate(180deg);
    }

    /* --- Automation Status ---
       Health first: the two clocks read as green or red at a glance, and a problem is a
       block of prose above them rather than a badge, because the useful part is the command
       that is missing from cron. */
    .ao-auto-problems {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .ao-auto-problem {
        padding: 0.6rem 0.75rem;
        border: 1px solid hsl(var(--color-error) / 0.35);
        border-inline-start: 3px solid hsl(var(--color-error));
        border-radius: var(--wa-radius, 3px);
        background: hsl(var(--color-error) / 0.06);
        font-size: 0.85rem;
    }

    .ao-auto-problem p {
        margin-top: 0.2rem;
        color: hsl(var(--color-base) / 0.8);
    }

    /* The reference's three header tiles: verdict, last run, next run. Big figure, small
       label under it, colour carrying the verdict. */
    .ao-auto-head {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ao-auto-head-tile {
        padding: 1rem 1.15rem;
        border-radius: 0.375rem;
        color: hsl(var(--color-inverted));
        background: hsl(var(--color-inactive));
    }

    .ao-auto-head-tile.ao-auto-ok { background: hsl(var(--color-success)); }
    .ao-auto-head-tile.ao-auto-bad { background: hsl(var(--color-error)); }
    .ao-auto-head-tile.ao-auto-neutral { background: hsl(var(--color-warning)); }
    .ao-auto-head-tile.ao-auto-quiet { background: hsl(var(--color-inactive)); }

    .ao-auto-head-figure {
        display: block;
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.15;
    }

    .ao-auto-head-label {
        display: block;
        font-size: 0.8rem;
        opacity: 0.9;
    }

    .ao-auto-heartbeat {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 1rem;
        font-size: 0.85rem;
        color: hsl(var(--color-base));
    }

    .ao-auto-section {
        margin-bottom: 0.6rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    /* Daily Actions. `auto-fit` rather than the reference's fixed three columns, so the grid
       reflows on a narrow window instead of overflowing — and still looks deliberate when
       an extension that owns two of the tiles is not installed. */
    .ao-auto-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
        gap: 0.75rem;
    }

    .ao-auto-tile {
        border: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        border-radius: var(--wa-radius, 3px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
    }

    .ao-auto-tile-head {
        padding: 0.45rem 0.75rem;
        border-bottom: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    .ao-auto-tile-body {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        padding: 0.6rem 0.75rem 0.35rem;
    }

    .ao-auto-tile-figure {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        color: hsl(var(--color-primary));
    }

    .ao-auto-tile-did {
        font-size: 0.8rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    /* The reference puts this in red at the end of the row, and only when it is not zero. */
    .ao-auto-tile-failed {
        margin-inline-start: auto;
        font-size: 0.78rem;
        font-weight: 600;
        color: hsl(var(--color-error));
    }

    .ao-auto-tile-foot {
        padding: 0 0.75rem 0.6rem;
        font-size: 0.72rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    .ao-auto-clocks {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .ao-auto-clock {
        padding: 0.75rem;
        border: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        border-radius: var(--wa-radius, 3px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
        font-size: 0.85rem;
    }

    .ao-auto-clock-head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ao-auto-dot {
        width: 0.6rem;
        height: 0.6rem;
        border-radius: 999px;
        flex: none;
    }

    .ao-auto-ok .ao-auto-dot { background: hsl(var(--color-success)); }
    .ao-auto-bad .ao-auto-dot { background: hsl(var(--color-error)); }

    .ao-auto-label {
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    .ao-auto-verdict {
        margin-inline-start: auto;
        font-weight: 600;
    }

    .ao-auto-ok .ao-auto-verdict { color: hsl(var(--color-success)); }
    .ao-auto-bad .ao-auto-verdict { color: hsl(var(--color-error)); }

    .ao-auto-when {
        margin-top: 0.35rem;
        color: hsl(var(--color-base));
    }

    .ao-auto-exact,
    .ao-auto-note {
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    .ao-auto-note {
        margin-top: 0.35rem;
        font-size: 0.75rem;
    }

    /* --- Products/Services: the drag-ordered catalogue ---

       The reference's shape: a bordered panel per group, a grey heading band carrying the
       name, the products as a table inside it. Child groups are the same panel indented,
       which is why these are lists rather than the reference's single flat table.

       Colours are the skin's `--wa-*` where the reference is specific about them and the
       panel's own properties elsewhere, so this follows dark mode on the pages that have
       it. Falls back if AdminOps' skin is not loaded. */
    .ao-cat-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .ao-cat {
        border: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        border-radius: var(--wa-radius, 3px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
    }

    .ao-cat-head {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0.75rem;
        background: var(--wa-section, hsl(var(--color-gray-100)));
        border-bottom: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        font-size: 0.875rem;
    }

    .ao-cat-name {
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    /* Pushes everything after it to the right-hand end, as the reference does with its
       per-group icons. */
    .ao-cat-meta {
        margin-inline-start: auto;
        font-size: 0.8rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    .ao-cat-edit {
        font-size: 0.8rem;
        color: var(--wa-link, hsl(var(--color-primary)));
    }

    .ao-cat-edit:hover {
        text-decoration: underline;
    }

    .ao-cat-empty {
        padding: 0.6rem 0.75rem;
        font-size: 0.85rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    /* Indented and ruled rather than nested in another border: two panel edges a few pixels
       apart read as a rendering fault rather than as a hierarchy. */
    .ao-cat-children {
        margin: 0.75rem 0 0.75rem 1.5rem;
        padding-inline-start: 0.75rem;
        border-inline-start: 2px solid var(--wa-rule, hsl(var(--color-gray-200)));
    }

    .ao-cat-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .ao-cat-table th {
        padding: 0.4rem 0.75rem;
        text-align: start;
        font-weight: 600;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
        border-bottom: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
    }

    .ao-cat-table td {
        padding: 0.4rem 0.75rem;
        border-bottom: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
        color: hsl(var(--color-base));
    }

    .ao-cat-table tr:last-child td {
        border-bottom: 0;
    }

    .ao-cat-table a {
        color: var(--wa-link, hsl(var(--color-primary)));
    }

    .ao-cat-table a:hover {
        text-decoration: underline;
    }

    .ao-cat-flag {
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
        font-size: 0.8rem;
    }

    .ao-col-grip { width: 1.75rem; }
    .ao-col-stock { text-align: end; }

    /* The handle. `grab` rather than `move`, because nothing is being moved until the
       pointer goes down — and the row is not draggable until it does. */
    .ao-grip {
        cursor: grab;
        user-select: none;
        color: var(--wa-muted, hsl(var(--color-base) / 0.5));
        font-size: 0.9rem;
        line-height: 1;
        padding: 0.15rem;
        border-radius: 2px;
    }

    .ao-grip:hover {
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    .ao-grip:focus-visible {
        outline: 2px solid hsl(var(--color-primary));
        outline-offset: 1px;
    }

    .ao-dragging {
        opacity: 0.45;
    }

    /* The write is a single indexed UPDATE per row and normally finishes before this is
       perceptible; it exists for the case where it does not, so a slow save cannot be
       mistaken for a drag that did not take. */
    .ao-saving {
        transition: opacity 120ms;
        opacity: 0.7;
    }

    /* Money leaving reads differently from money arriving, which is the whole point of the
       reference splitting In from Out rather than using a sign. */
    .ao-tx-out {
        color: hsl(var(--color-error));
    }

    .ao-catalogue-count,
    .ao-catalogue-empty {
        margin-top: 0.75rem;
        font-size: 0.8rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }
</style>
