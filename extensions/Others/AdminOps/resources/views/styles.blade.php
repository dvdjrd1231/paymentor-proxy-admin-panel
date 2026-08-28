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

    /* The reference's tiles: a darker block of the tile's own colour on the left with the
       icon reversed out white, figure and label on the lighter body, corners barely
       rounded. The dark block is black at 16% over the tile colour, so every tone gets its
       own darker shade without naming four more colours. */
    .ao-tile {
        display: flex;
        align-items: stretch;
        gap: 1rem;
        padding: 0;
        border-radius: 3px;
        overflow: hidden;
        background-color: hsl(var(--color-inactive));
        transition: filter 120ms ease;
    }

    /* White lettering, said twice over: explicit #fff because var(--color-inverted)
       resolves to rgb(84,84,84) in this theme, and with the `a.` prefix because most tiles
       are links and `.ao-panel a { color: inherit }` out-ranks a bare class — which is how
       the figures were quietly repainted panel-grey on the coloured ground. */
    a.ao-tile,
    .ao-tile {
        color: #fff;
    }

    .ao-tile:hover {
        filter: brightness(1.06);
    }

    .ao-tile-success { background-color: hsl(var(--color-success)); }
    .ao-tile-warning { background-color: hsl(var(--color-warning)); }
    .ao-tile-info    { background-color: hsl(var(--color-info)); }
    .ao-tile-brand   { background-color: hsl(var(--color-primary)); }

    /* Sized off the reference's tiles, which are shorter than they look: ~64px tall, a
       ~28px glyph on the dark block, a 24px figure. Ours had drifted a size up on all
       three. */
    .ao-tile-icon {
        flex: none;
        box-sizing: content-box;
        width: 1.75rem;
        height: auto;
        align-self: stretch;
        padding: 0.8rem 1rem;
        background: rgb(0 0 0 / 0.16);
        color: #fff;
        opacity: 1;
    }

    .ao-tile-figure {
        align-self: center;
        padding-block: 0.6rem;
        padding-inline-end: 1rem;
    }

    .ao-tile-count {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.15;
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
       nothing.

       `display: none`, and it must be: the previous `height: 0` removed the box but not the
       grid *row*, and the grid pays its 24px row-gap on every row — so the tiles sat a full
       gap lower for a row containing nothing. A `display: none` grid item creates no row at
       all. The earlier worry that hiding it would break the Livewire component was wrong on
       both counts: the element stays in the document either way, and by the time this class
       is applied the gear and menu have already been moved out into the page header. */
    .fi-wi-widget.ao-dash-collapsed {
        display: none;
    }

    /* The reference keeps every page title tight under the top bar and the content tight
       under the title; Filament spends 32px on each. This began scoped to the dashboard,
       until the Users page showed the same two bands on every screen — so it is the whole
       admin area now, which is also what makes the pages feel like one application. */
    .fi-page-header-main-ctn {
        padding-block-start: 0.75rem;
        gap: 1rem;
    }

    /* WHMCS page titles are quiet: ~20px, sitting right on the breadcrumb line. Filament's
       24px title with 8px of breadcrumb margin above it reads as a banner. */
    .fi-header h1 {
        font-size: 1.25rem;
        line-height: 1.4;
    }

    .fi-breadcrumbs {
        margin-bottom: 0.15rem;
    }

    /* ── Table density ──────────────────────────────────────────────────────────────
       The reference's rows are ~34px; Filament's were 73px, and almost none of it was
       content: `.fi-ta-text-item` carries 16px of padding above and below a 24px line,
       inside a cell that adds 8px more each way. Trimmed at both levels, a row is ~40px —
       twice the records on screen, which on a list page is the entire point of the page. */
    /* The extra ancestor is load-bearing: these are injected in the head, before Filament's
       own stylesheet, so at equal specificity Filament wins on order and nothing changes. */
    .fi-ta .fi-ta-cell {
        padding-block: 2px;
    }

    /* Both spellings: a plain cell wraps its text in `.fi-ta-text-item`, a cell with a
       description line wraps the pair in `.fi-ta-text` — each with its own 16px paddings,
       so trimming only the first left any row with a second line at twice the height of
       its neighbours, and the Users list read as ragged. */
    .fi-ta-cell .fi-ta-text-item,
    .fi-ta-cell .fi-ta-text {
        padding-block: 0.3rem;
    }

    .fi-ta-cell .fi-ta-text-description {
        font-size: 0.78rem;
        line-height: 1.3;
    }

    /* Actions column too, or rows with buttons stay tall and the table ripples. */
    .fi-ta-cell .fi-ta-actions {
        padding-block: 0.3rem;
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

    /* The reference's gear is ~20px; ours was 16 and read as a speck. */
    .ao-dash-settings-button svg {
        width: 20px;
        height: 20px;
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

    /* Sized to its contents, and bounded by the window rather than by a guess at how many
       widgets a panel will ever have. An extension can add widgets, so the list is not a
       fixed length: past roughly a dozen the list scrolls inside the menu instead of running
       off the bottom of the screen, and the heading and the note stay put while it does. */
    .ao-dash-menu {
        position: absolute;
        inset-inline-end: 0;
        top: 100%;
        z-index: 20;
        display: flex;
        flex-direction: column;
        min-width: 15rem;
        width: max-content;
        max-width: min(22rem, calc(100vw - 2rem));
        max-height: min(70vh, 34rem);
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

    /* The list is the part that scrolls; `min-height: 0` is what allows a flex child to be
       shorter than its content, without which it simply overflows the menu. */
    .ao-dash-menu ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .ao-dash-menu label {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        cursor: pointer;
        /* A long widget name wraps rather than widening the menu past its maximum. */
        overflow-wrap: anywhere;
    }

    .ao-dash-menu h4,
    .ao-dash-menu-note {
        flex: none;
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
        /* The four-arrow cursor: hovering a title says "this object can be moved", which
           grab did not — grab reads as scroll-drag on most desktops. */
        cursor: move;
    }

    .ao-wi-header:focus-visible {
        outline: 2px solid hsl(var(--color-primary));
        outline-offset: 2px;
    }

    /* The table widget's header is the one place the tools land that is not already a flex
       row; without this they stack under the heading instead of sitting at its end. The
       heading is pushed left explicitly — centred, it read as a different kind of panel
       from its neighbours. */
    /* The table widget's header fought every flex arrangement — its heading container
       centres itself with auto margins and pushed the tools onto a second line. So the
       tools are pinned to the corner absolutely instead, and the centring margin removed:
       title left, tools right, one line, whatever the inner markup does. */
    .fi-ta-header.ao-wi-header {
        position: relative;
    }

    .fi-ta-header.ao-wi-header > :first-child {
        margin-inline: 0;
        text-align: left;
    }

    .fi-ta-header.ao-wi-header .fi-ta-header-heading {
        text-align: left;
    }

    .fi-ta-header.ao-wi-header .ao-wi-tools {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        inset-inline-end: 0.8rem;
    }

    /* Filament's section headers are flex rows, so the tools land at the end of one without
       being positioned. The `margin-inline-start: auto` is what pins them right in the bar
       this script draws for a widget that has no header of its own. */
    /* Ours, not Tailwind's. The admin panel's stylesheet does not define `.sr-only`, so the
       labels these buttons carry for screen readers were rendering as visible text: every
       panel heading read "↻Refresh At a glance ▲Collapse At a glance ✕Hide At a glance".
       Depending on a class the framework happens to ship is how that went unnoticed. */
    .ao-sr {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        clip-path: inset(50%);
        white-space: nowrap;
        border-width: 0;
    }

    .ao-wi-tools {
        display: flex;
        align-items: center;
        gap: 0.15rem;
        margin-inline-start: auto;
    }

    /* The reference's tool icons: pale grey strokes that darken on hover, no hover pill —
       quiet enough that the blue title stays the thing being read. */
    .ao-wi-tool {
        padding: 0.15rem 0.25rem;
        line-height: 1;
        color: #b9bfc4;
        cursor: pointer;
    }

    .ao-wi-tool:hover {
        color: #7d8790;
    }

    .ao-wi-tool svg {
        display: block;
    }

    .ao-wi-spin svg {
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
        color: var(--wa-link, #337ab7);
    }

    /* The reference's dashboard headers are *white*, not the #f5f5f5 band its other panels
       wear — a blue title sitting directly on the panel, tools in light grey at the right,
       and no rule underneath. The band styling in the skin is correct everywhere else, so
       only panels this chrome has adopted are overridden. */
    .ao-wi .fi-section-header,
    .ao-wi .fi-ta-header,
    .ao-wi .ao-wi-bar {
        background: #fff;
        /* The zoom shows a hairline under each title — far lighter than the #ddd the other
           panels close their bands with. */
        border-bottom: 1px solid #ececec;
        padding: 0.55rem 0.8rem;
    }

    /* The reference letters its stats, not its labels: figures in rotating WHMCS colours —
       green, orange, pink — at regular weight, labels in quiet grey. Ours were black-on-black
       bold, which read as a different product. Sampled from the screenshot. */
    .ao-wi .fi-wi-stats-overview-stat-label {
        color: #8b8b8b;
        font-weight: 400;
        font-size: 0.8rem;
    }

    .ao-wi .fi-wi-stats-overview-stat-value {
        font-weight: 400;
    }

    /* Stamped by the widget script rather than counted by CSS: two attempts at counting
       wrappers both lost — the stats' real ancestor chain matches neither the stat class
       nor the container name — so decorate() stamps ao-stat-c1/2/3 on each stat in order
       and colour follows the stamp. */
    .ao-stat-c1 .fi-wi-stats-overview-stat-value { color: #7ac143; }
    .ao-stat-c2 .fi-wi-stats-overview-stat-value { color: #f5a54a; }
    .ao-stat-c3 .fi-wi-stats-overview-stat-value { color: #f062a2; }

    /* At a glance rows in the same rotation the reference letters Automation Overview
       with — grey measure names, coloured figures. */
    .ao-glance tbody tr:nth-child(4n+1) td:not(:first-child) { color: #7ac143; }
    .ao-glance tbody tr:nth-child(4n+2) td:not(:first-child) { color: #f5a54a; }
    .ao-glance tbody tr:nth-child(4n+3) td:not(:first-child) { color: #f062a2; }
    .ao-glance tbody tr:nth-child(4n)   td:not(:first-child) { color: #b57bd4; }

    .ao-glance tbody td:first-child {
        color: #8b8b8b;
    }

    .ao-wi .fi-wi-stats-overview-stat-description {
        color: #8b8b8b;
    }

    /* Blue panel titles, exactly as every WHMCS panel titles itself — it is the single most
       recognisable trait of that dashboard, and the black headings were the tell that this
       one was something else. Regular weight, as the reference sets them. */
    .ao-wi .fi-section-header-heading,
    .ao-wi .fi-ta-header-heading,
    .ao-wi .fi-wi-chart-header-heading,
    .ao-wi .ao-wi-bar {
        color: var(--wa-link, #337ab7);
        font-size: 14px;
        font-weight: 400;
    }

    /* While a drag is in flight the in-flow panel becomes the reference's estimate box:
       the browser's ghost carries the picture under the cursor, and the dashed outline
       shows where it will land, gliding between candidate slots as the others FLIP out
       of the way. `visibility` rather than `display` on the contents, so the box keeps
       the panel's exact size. */
    .ao-wi-dragging {
        border: 2px dashed #b9b9b9;
        background: #fbfbfb;
    }

    .ao-wi-dragging > * {
        visibility: hidden;
    }

    /* Carried (click-to-pick-up): the in-flow panel is the dashed estimate box — the same
       treatment a native drag gets — while a fixed-position ghost of it follows the
       cursor. That is the reference's picture exactly: the object in hand, the dashes
       showing where it will land. */
    .ao-wi-carrying {
        border: 2px dashed #6ea3d8;
        background: #fbfbfb;
    }

    .ao-wi-carrying > * {
        visibility: hidden;
    }

    .ao-wi-ghost {
        position: fixed;
        z-index: 60;
        pointer-events: none;
        margin: 0;
        opacity: 0.93;
        background: #fff;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: 3px;
        overflow: hidden;
        box-shadow: 0 10px 28px rgb(0 0 0 / 0.25);
    }

    .ao-wi-hidden {
        display: none;
    }

    /* Every adopted panel is one bordered box, as every WHMCS panel is. The wrapper carries
       the border because the widgets inside are inconsistent: a section brings its own box,
       a table brings a different one, and a stats row brings none at all — which is why some
       dashboard blocks had borders and some floated. One border at one level, and the inner
       containers stop competing with it. */
    .fi-wi-widget.ao-wi {
        border: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        border-radius: var(--wa-radius, 3px);
        background: #fff;
        overflow: hidden;
    }

    /* The reference's dashboard is white panels on a quiet grey page — panel edges read from
       the contrast, not only from the border. Scoped to the one page that has adopted
       panels; every other admin screen keeps the white canvas the skin gives it. */
    main.fi-main:has(.fi-wi-widget.ao-wi) {
        background: #f3f5f6;
    }

    .fi-wi-widget.ao-wi .fi-section,
    .fi-wi-widget.ao-wi .fi-ta-ctn {
        border: none;
        box-shadow: none;
        border-radius: 0;
    }

    /* The reference lays its homepage out in columns — panels side by side, not a single
       stack a screen and a half tall. Three of them, as WHMCS has, with WHMCS's tight
       gutters rather than Filament's 24px.

       Done here rather than by setting `columnSpan` on the widgets because three of the
       seven are Paymenter's own, and those are core. This reaches only panels the chrome
       has adopted: the tile row and this widget's own block are untouched, so the tiles
       keep the full width the reference gives them. */
    @media (min-width: 1024px) {
        /* Keyed on .ao-grid — the class the widget script adds at boot — not on
           :has(.ao-wi). The :has version only matched once panels were *decorated*, so in
           the seconds before that the grid kept Filament's 24px gap while the 10px masonry
           rows already applied: every undecorated child reserved 40 rows plus 39 fat gaps,
           1336px of nothing each, which pushed the lazy widgets out of the viewport so
           they never loaded without scrolling. One class, one moment, both rules. */
        .fi-grid.ao-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.65rem;
            grid-auto-rows: 10px;
        }

        /* `13` is a lazy placeholder's own height, so the frame before the first pack()
           reserves roughly what is actually there.

           `align-self: start` on *every* child, and that is the load-bearing half: a
           stretched grid item's height is its span's height, so measuring it hands the
           span straight back and the box never shrinks to its content. The tiles row
           proved it — not being an adopted panel it missed the .ao-wi start-alignment,
           stretched to its own guess, and wore 170px of empty space as if it were
           content. Start-aligned, a child's height is its content's, and packing
           converges. */
        .ao-grid > * {
            grid-row: span var(--ao-span, 13);
            align-self: start;
        }

        .fi-wi-widget.ao-wi {
            grid-column: span 1 / span 1;

            /* Panels sit at the top of their row rather than stretching to match the
               tallest one beside them. It does not close the gap a short panel leaves —
               only masonry does that, and masonry collapses the chart (see the note in the
               widget script) — but it does stop a half-empty panel being stretched. */
            align-self: start;
        }

        /* The revenue chart takes two of the three, as the reference's System Overview
           does — a graph one column wide is a sparkline pretending to be a chart. */
        .fi-wi-widget.ao-wi.fi-wi-chart {
            grid-column: span 2 / span 2;
        }

        /* Capped, or it isn't a strip: Chart.js keeps its aspect ratio, so a chart two
           columns wide renders two columns *tall*. The reference's chart is ~300px high
           however wide the screen. Chart.js is responsive and follows the container. */
        .fi-wi-widget.ao-wi.fi-wi-chart .fi-wi-chart-canvas-ctn {
            height: 300px;
        }

        .fi-wi-widget.ao-wi.fi-wi-chart .fi-wi-chart-canvas-ctn canvas {
            max-height: 100%;
        }
    }

    /* Collapsed: the heading stays, everything under it goes, so the panel can be rolled
       back open.

       These are the class names Filament 5 actually emits — taken from its own Blade views,
       not guessed. The `.fi-sc-section-content` spelling that was here matched nothing, so
       the collapse button changed a class and the panel sat there fully open. */
    .ao-wi-collapsed .fi-section-content,
    .ao-wi-collapsed .fi-section-content-ctn,
    .ao-wi-collapsed .fi-wi-chart-canvas-ctn,
    .ao-wi-collapsed .fi-wi-stats-overview-stats-ctn,
    .ao-wi-collapsed .fi-ta-ctn {
        display: none;
    }

    /* The chevron points the other way once there is nothing under it — so the button says
       "expand" when the panel is rolled up, which is what the reference does. */
    .ao-wi-collapsed [title^="Collapse"] svg {
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

    /* --- View/Search Clients & Manage Users ---
       The reference's furniture, shared by both screens: the grey search band with its
       round green glass, the records line, the navy grid, and the page buttons. Measured
       against the screenshots rather than remembered. */
    .ao-mu {
        font-size: 0.8125rem;
        color: var(--wa-text, #4a4a4a);
    }

    .ao-find {
        display: flex;
        align-items: flex-end;
        gap: 1rem;
        padding: 0.9rem 1.1rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 3px);
        background: #f8f8f8;
        margin-bottom: 0.9rem;
    }

    .ao-find-glass {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        flex: none;
        align-self: center;
        border-radius: 999px;
        background: #86c44a;
        color: #fff;
    }

    .ao-find-fields {
        display: flex;
        flex: 1;
        flex-wrap: wrap;
        gap: 0.9rem;
    }

    .ao-find-field {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
        min-width: 9rem;
    }

    .ao-find-field > span {
        font-weight: 600;
        color: var(--wa-ink, #333);
    }

    .ao-find-wide { flex: 1; min-width: 13rem; }
    .ao-find-grow { flex: 1; }

    .ao-find-field input,
    .ao-find-field select {
        height: 2.1rem;
        padding: 0 0.55rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 3px);
        background: #fff;
        font-size: 0.8125rem;
    }

    .ao-find-field input:focus,
    .ao-find-field select:focus {
        outline: 2px solid var(--wa-link, #337ab7);
        outline-offset: -1px;
    }

    .ao-find-go {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        height: 2.1rem;
        padding: 0 1.4rem;
        flex: none;
        border-radius: var(--wa-radius, 3px);
        background: var(--wa-link, #337ab7);
        color: #fff;
        font-weight: 600;
        font-size: 0.8125rem;
        cursor: pointer;
    }

    .ao-find-go:hover { background: #286090; }

    .ao-mu-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.5rem;
    }

    .ao-mu-line-right {
        display: inline-flex;
        align-items: center;
        gap: 1rem;
    }

    .ao-mu-jump select {
        margin-inline-start: 0.3rem;
        padding: 0.1rem 0.4rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 3px);
        background: #fff;
    }

    /* The reference's little ON pill on Hide Inactive Clients. */
    .ao-mu-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        cursor: pointer;
        color: inherit;
    }

    .ao-mu-toggle i {
        font-style: normal;
        font-size: 0.66rem;
        font-weight: 700;
        padding: 0.1rem 0.45rem;
        border-radius: 999px;
        background: #b6b6b6;
        color: #fff;
    }

    .ao-mu-toggle.ao-on i { background: var(--wa-link, #337ab7); }

    .ao-mu-grid {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }

    .ao-mu-grid th {
        padding: 0.45rem 0.6rem;
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        font-size: 0.78rem;
        font-weight: 700;
        text-align: center;
        border-inline-end: 1px solid rgb(255 255 255 / 0.18);
        white-space: nowrap;
    }

    .ao-mu-grid td {
        padding: 0.45rem 0.6rem;
        text-align: center;
        border-bottom: 1px solid var(--wa-rule, #d9dadb);
    }

    .ao-mu-grid tbody tr:hover { background: #f5f8fb; }

    .ao-mu-grid a {
        color: var(--wa-link, #337ab7);
    }

    .ao-mu-grid a:hover { text-decoration: underline; }

    .ao-mu-check { width: 2rem; }

    .ao-mu-none {
        padding: 0.8rem !important;
        color: var(--wa-muted, #888);
    }

    .ao-mu-status.ao-mu-active { color: #3c763d; font-weight: 600; }
    .ao-mu-status.ao-mu-inactive { color: var(--wa-muted, #888); }

    .ao-mu-actions a { margin-inline: 0.35rem; white-space: nowrap; }

    .ao-mu-selected {
        margin-top: 0.7rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .ao-mu-selected button,
    .ao-mu-pages button {
        padding: 0.35rem 0.9rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 3px);
        background: #fff;
        color: var(--wa-text, #4a4a4a);
        font-size: 0.8125rem;
        cursor: pointer;
    }

    .ao-mu-selected button:hover,
    .ao-mu-pages button:hover:not(:disabled) { background: #f0f0f0; }

    .ao-mu-pages button:disabled { opacity: 0.5; cursor: default; }

    .ao-mu-pages {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.4rem;
        margin-top: 1.1rem;
    }

    .ao-mu-page-now {
        display: inline-grid;
        place-items: center;
        min-width: 2.1rem;
        height: 2.1rem;
        border-radius: var(--wa-radius, 3px);
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        font-weight: 700;
    }
</style>
