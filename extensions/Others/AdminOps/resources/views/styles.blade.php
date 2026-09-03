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
        font-size: 1rem;
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
        /* The same 15px the panel grid uses — one gutter, everywhere. */
        gap: 15px;
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
        border-radius: var(--wa-radius, 6px);
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
        font-size: 0.85rem;
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
        font-size: 0.9rem;
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
        font-size: 0.82rem;
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

    /* The reference's gear, to its screenshot: ~22px, quiet grey, darkening on reach — big
       enough to be furniture, not a speck. */
    .ao-dash-settings-button svg {
        width: 22px;
        height: 22px;
    }

    .ao-dash-settings {
        position: relative;
    }

    .ao-dash-settings-button {
        display: flex;
        align-items: center;
        padding: 0.3rem;
        border-radius: var(--wa-radius, 6px);
        color: #8a8a8a;
        cursor: pointer;
    }

    .ao-dash-settings-button:hover {
        color: #6d6d6d;
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
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
        box-shadow: 0 4px 14px rgb(0 0 0 / 0.12);
        font-size: 0.9rem;
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
        font-size: 0.9375rem;
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
        color: #6e6e6e;
        font-weight: 400;
        font-size: 0.85rem;
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
        color: #6e6e6e;
    }

    .ao-wi .fi-wi-stats-overview-stat-description {
        color: #6e6e6e;
    }

    /* Blue panel titles, exactly as every WHMCS panel titles itself — it is the single most
       recognisable trait of that dashboard, and the black headings were the tell that this
       one was something else. Regular weight, as the reference sets them. */
    .ao-wi .fi-section-header-heading,
    .ao-wi .fi-ta-header-heading,
    .ao-wi .fi-wi-chart-header-heading,
    .ao-wi .ao-wi-bar {
        color: var(--wa-link, #337ab7);
        font-size: 15px;
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
        border-radius: var(--wa-radius, 6px);
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
        border-radius: var(--wa-radius, 6px);
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
            /* 20px columns — the first reading of the target said 25, a closer zoom says 20 — the reference's gutters
               are wider than they look.

               Row gap **zero**, and that is the fix for the one space that refused to be
               standard: with a row-gap, every reserved masonry row costs row+gap, so the
               leftover under a panel quantised in 35px steps and the gap below Support
               could reach ~59px while its neighbours sat at 25. The vertical gutter now
               lives inside the measurement instead — pack() adds 25px to each panel's
               height and rounds to 5px rows, so the visible space below any panel is
               25–30px, every time. */
            column-gap: 15px;
            row-gap: 0;
            grid-auto-rows: 5px;
            /* `dense` is what makes the distances *standard*: without it, auto-placement
               walks forward only, and a tall panel earlier in the sequence strands a hole
               it never looks back to fill — the gaps Leandro boxed in orange. Dense
               backfills them, exactly as the reference's packer does; the cost is that a
               later small panel may sit above an earlier tall one, which is also exactly
               how the reference behaves. */
            grid-auto-flow: dense;
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
            /* 30 = a 128px lazy placeholder plus the 20px gutter, in 5px rows — so the
               frame before the first pack() reserves roughly what is actually there. */
            grid-row: span var(--ao-span, 30);
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
        border-radius: var(--wa-radius, 6px);
        background: hsl(var(--color-error) / 0.06);
        font-size: 0.9rem;
    }

    .ao-auto-problem p {
        margin-top: 0.2rem;
        color: hsl(var(--color-base) / 0.8);
    }

    /* ── Update Paymenter (issue #27) ────────────────────────────────────────
       The reference's Update WHMCS screen: a centred verdict line, the grey/blue version
       tiles joined into one block, Update Now with the release links under it. */
    .ao-up { max-width: 52rem; margin-inline: auto; text-align: center; }

    .ao-up-verdict {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        margin: 1.5rem 0 1.75rem;
        font-size: 1.8rem;
        font-weight: 400;
    }

    .ao-up-verdict.ao-up-new, .ao-up-verdict.ao-up-ok { color: #7bab48; }

    .ao-up-verdict-ic svg { width: 2.2rem; height: 2.2rem; }

    .ao-up-tiles {
        display: grid;
        grid-template-columns: 1fr 1fr;
        max-width: 40rem;
        margin: 0 auto 1.5rem;
        border-radius: 4px;
        overflow: hidden;
    }

    .ao-up-tile-head {
        padding: 0.7rem 1rem;
        font-size: 1.15rem;
        font-weight: 600;
        color: #fff;
    }

    .ao-up-yours .ao-up-tile-head { background: #4a4a4a; }
    .ao-up-latest .ao-up-tile-head { background: #1a4d80; }

    .ao-up-tile-body { padding: 1.1rem 1rem 0.9rem; color: #fff; }

    .ao-up-yours .ao-up-tile-body { background: #7a7a7a; }
    .ao-up-latest .ao-up-tile-body { background: #2e6da4; }

    .ao-up-figure {
        display: block;
        font-size: 3.4rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .ao-up-line { display: block; font-size: 1.1rem; font-weight: 600; }

    .ao-up-sub { display: block; margin-top: 0.35rem; font-size: 0.78rem; opacity: 0.85; }

    .ao-up-actions { margin-bottom: 1.5rem; }

    .ao-up-update {
        display: inline-block;
        padding: 0.5rem 1.1rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: 4px;
        background: #fff;
        color: var(--wa-muted, #6b6b6b);
        font-size: 0.95rem;
        cursor: not-allowed;
    }

    .ao-up-links { margin-top: 0.8rem; display: flex; justify-content: center; gap: 2.5rem; }

    .ao-up-links a { color: #337ab7; font-size: 0.95rem; }

    .ao-up-links a:hover { text-decoration: underline; }

    .ao-up-warning {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        max-width: 46rem;
        margin: 0 auto 1.25rem;
        padding: 0.9rem 1.1rem;
        border-radius: 4px;
        background: #fcf8e3;
        color: #8a6d3b;
        font-size: 0.9rem;
        text-align: left;
    }

    .ao-up-warning-ic { width: 1.6rem; height: 1.6rem; flex: none; }

    .ao-up-warning strong { margin-inline-end: 0.3rem; }

    .ao-up-checked { font-size: 0.9rem; color: var(--wa-muted, #6b6b6b); }

    @media (max-width: 760px) {
        .ao-up-tiles { grid-template-columns: 1fr; }
    }

    /* The reference's three header tiles: verdict, last run, next run. Big figure, small
       label under it, colour carrying the verdict. */
    .ao-auto-head {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    /* The reference's banner tile is two-tone: a darker square carrying the glyph, then
       the lighter body with the figure and its label (issue #28). */
    .ao-auto-head-tile {
        display: flex;
        align-items: stretch;
        border-radius: 0.375rem;
        overflow: hidden;
        color: hsl(var(--color-inverted));
        background: #b0b0b0;
    }

    .ao-auto-head-tile.ao-auto-ok { background: #5cb85c; }
    .ao-auto-head-tile.ao-auto-bad { background: #d9534f; }
    .ao-auto-head-tile.ao-auto-neutral { background: #f0ad4e; }
    .ao-auto-head-tile.ao-auto-quiet { background: #b0b0b0; }

    .ao-auto-head-ic {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: none;
        width: 4.5rem;
        background: rgb(0 0 0 / 0.15);
    }

    .ao-auto-head-ic svg { width: 2.4rem; height: 2.4rem; }

    .ao-auto-head-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 0.85rem 1.15rem;
        min-width: 0;
    }

    .ao-auto-head-figure {
        display: block;
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ao-auto-head-label {
        display: block;
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .ao-auto-head-label a { color: inherit; text-decoration: underline; }

    .ao-auto-heartbeat {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
        color: hsl(var(--color-base));
    }

    .ao-auto-section {
        margin-bottom: 0.6rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    /* ── Automation Status chart ─────────────────────────────────────────────
       The reference's amber area chart. Plain SVG — a polygon for the fill, a polyline for
       the edge — plotted from real CronStat rows, so an install with nothing to plot draws
       a flat line at zero rather than an empty box pretending to be a chart. */
    .ao-auto-chart {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 0.9rem 1.1rem 0.6rem;
        margin-bottom: 1.4rem;
    }

    .ao-auto-chart-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.6rem;
        font-size: 0.9rem;
    }

    .ao-auto-chart-pick {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 600;
    }

    .ao-auto-chart-pick select {
        height: 2rem;
        padding: 0 0.5rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font-size: 0.85rem;
    }

    .ao-auto-chart-range {
        color: var(--wa-muted, #6b6b6b);
        font-weight: 600;
    }

    .ao-auto-chart-svg { width: 100%; height: 220px; display: block; }

    .ao-auto-chart-fill { fill: #fdf0d5; }

    .ao-auto-chart-line {
        fill: none;
        stroke: #f0ad4e;
        stroke-width: 2;
    }

    .ao-auto-chart-dot { fill: #f0ad4e; }

    .ao-auto-chart-axis {
        display: flex;
        justify-content: space-between;
        margin-top: 0.3rem;
        font-size: 0.8rem;
        color: var(--wa-muted, #6b6b6b);
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
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
    }

    .ao-auto-tile-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.45rem 0.75rem;
        border-bottom: 1px solid var(--wa-rule, hsl(var(--color-gray-200)));
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    /* The reference's grey glyph in the tile's top right corner. */
    .ao-auto-tile-ic {
        width: 1.5rem;
        height: 1.5rem;
        flex: none;
        color: #b8b8b8;
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
        font-size: 0.85rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    /* The reference puts this in red at the tile's bottom right, and only when not zero. */
    .ao-auto-tile-failed {
        font-size: 0.82rem;
        font-weight: 600;
        color: hsl(var(--color-error));
    }

    .ao-auto-tile-foot {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0 0.75rem 0.6rem;
        font-size: 0.78rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    /* The reference right-aligns both chart controls. */
    .ao-auto-chart-head.ao-auto-chart-head-right { justify-content: flex-end; }

    .ao-auto-chart-head.ao-auto-chart-head-right .ao-auto-chart-range {
        padding: 0.35rem 0.7rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: 4px;
        background: #fff;
        color: var(--wa-ink, #2b2b2b);
        font-weight: 400;
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
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
        font-size: 0.9rem;
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
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
    }

    .ao-cat-head {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0.75rem;
        background: var(--wa-section, hsl(var(--color-gray-100)));
        border-bottom: 1px solid var(--wa-panel-border, hsl(var(--color-gray-200)));
        font-size: 0.9375rem;
    }

    .ao-cat-name {
        font-weight: 600;
        color: var(--wa-ink, hsl(var(--color-base)));
    }

    /* Pushes everything after it to the right-hand end, as the reference does with its
       per-group icons. */
    .ao-cat-meta {
        margin-inline-start: auto;
        font-size: 0.85rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    .ao-cat-edit {
        font-size: 0.85rem;
        color: var(--wa-link, hsl(var(--color-primary)));
    }

    .ao-cat-edit:hover {
        text-decoration: underline;
    }

    .ao-cat-empty {
        padding: 0.6rem 0.75rem;
        font-size: 0.9rem;
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
        font-size: 0.9rem;
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
        font-size: 0.85rem;
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
        font-size: 0.85rem;
        color: var(--wa-muted, hsl(var(--color-base) / 0.6));
    }

    /* --- View/Search Clients & Manage Users ---
       The reference's furniture, shared by both screens: the grey search band with its
       round green glass, the records line, the navy grid, and the page buttons. Measured
       against the screenshots rather than remembered. */
    .ao-mu {
        font-size: 1rem;
        color: var(--wa-text, #2b2b2b);
    }

    .ao-find {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 0.9rem 1.1rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #f0f0f0;
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

    /* A label row and a control row, both a fixed height, and nothing below them.
       Asking the browser to leave these fields alone turned out not to be enough — Chrome's
       address autofill decorates a Name/Email/Phone row whatever the markup says, and a
       decoration that lands in the field is an extra grid row that makes that one field
       taller than its neighbours, which is what dropped + Advanced and Search below the line
       of the inputs. With the rows declared and the height fixed there is no third row to
       grow into: anything planted in a field lands outside the box and is clipped, and the
       band measures the same whether it was decorated or not. */
    .ao-find-field {
        display: grid;
        /* One column and two rows, both declared, and any track the browser has to invent
           beyond them is zero-sized — so an injected element can neither widen the field by
           claiming a column nor lengthen it by claiming a row. The fixed height and the clip
           are what make that final. */
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: 1.35rem 2.1rem;
        grid-auto-rows: 0;
        grid-auto-columns: 0;
        row-gap: 0.3rem;
        height: 3.75rem;
        overflow: hidden;
        /* Flex from a fixed basis, not content width: the reference fits its whole band on
           one row, and content-sized inputs were what pushed Status onto a second line.
           Bases sized so the row still holds together on a 1280px screen — Leandro's own
           window is ~1385, and the band wrapped there. */
        flex: 1 1 7.5rem;
        min-width: 6.5rem;
    }

    /* Ours are placed by hand, so an injected one has no row left to auto-place into. */
    .ao-find-field > .ao-find-label { grid-row: 1; }

    .ao-find-field > input,
    .ao-find-field > select,
    .ao-find-field > .ao-find-phone { grid-row: 2; }

    /* Nothing but our own elements is laid out, at any level of the band: the form holds the
       glass, the fields box and its two buttons; the box holds fields; a field holds its
       label and its control. Whatever else turns up is something the browser or an extension
       added, and it is taken out of the layout rather than allowed to push our furniture.
       By class, not by tag — a first attempt allowed any <span> in a field and any <div> in
       the form, and an injected element is usually exactly those. Every piece of a band
       carries a class for this reason, the label included. */
    .ao-find > :not(.ao-find-glass):not(.ao-find-fields):not(.ao-find-adv):not(.ao-find-go),
    .ao-find-fields > :not(.ao-find-field),
    .ao-find-field > :not(.ao-find-label):not(.ao-find-phone):not(input):not(select),
    .ao-find-phone > :not(input):not(select) {
        display: none !important;
    }

    /* Unless it has wrapped one of ours — some fillers re-parent the input rather than sit
       beside it, and hiding the wrapper would take the field with it. Dissolved instead, so
       the input stays exactly where the grid put it. */
    .ao-find > :not(.ao-find-glass):not(.ao-find-fields):not(.ao-find-adv):not(.ao-find-go):has(input, select, label),
    .ao-find-fields > :not(.ao-find-field):has(input, select),
    .ao-find-field > :not(.ao-find-label):not(.ao-find-phone):not(input):not(select):has(input, select),
    .ao-find-phone > :not(input):not(select):has(input, select) {
        display: contents !important;
    }

    /* The other half of a decoration is painted rather than planted: an icon as a background
       image, and autofill's own blue fill, which browsers apply through a shadow style that
       ordinary declarations lose to. The inset shadow is the documented way to win. */
    .ao-find-field input {
        background-image: none !important;
    }

    .ao-find-field input:-webkit-autofill,
    .ao-find-field input:-webkit-autofill:hover,
    .ao-find-field input:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0 30px #fff inset;
        box-shadow: 0 0 0 30px #fff inset;
        -webkit-text-fill-color: var(--wa-text, #2b2b2b);
    }

    /* These are search boxes and now say so, which is the one thing that actually keeps
       Chrome's address autofill away: it reads Name / Email / Phone as an address form no
       matter what autocomplete says, but it does not offer to fill a search field at all.
       The type brings a clear button and a platform look with it; both are removed, so the
       fields look exactly as they did. */
    .ao-find-field input[type="search"] {
        appearance: none;
        -webkit-appearance: none;
    }

    .ao-find-field input[type="search"]::-webkit-search-cancel-button,
    .ao-find-field input[type="search"]::-webkit-search-decoration,
    .ao-find-field input[type="search"]::-webkit-search-results-button {
        display: none;
        -webkit-appearance: none;
    }

    .ao-find-field > .ao-find-label {
        font-weight: 600;
        color: var(--wa-ink, #333);
        /* A wrapped label pushes its input a line down and the whole band out of line. */
        white-space: nowrap;
        font-size: 0.9rem;
    }

    /* Grow at the same rate as the rest, not twice as fast: with double share the name
       and email fields took 212px each and pushed Status onto a second line, which is the
       band "misaligning" itself. They still start wider, they just stop hogging the slack. */
    .ao-find-wide { flex: 1 1 8rem; min-width: 7.5rem; }
    .ao-find-grow { flex: 1; }

    .ao-find-field input,
    .ao-find-field select {
        width: 100%;
        min-width: 0;
        height: 2.1rem;
        padding: 0 0.55rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font-size: 0.9375rem;
    }

    /* Laptop squeeze: below ~1320px the band drops its glass circle and tightens, so the
       one-line promise holds down to 1280px screens. */
    @media (max-width: 1320px) {
        .ao-find { gap: 0.5rem; padding-inline: 0.7rem; }
        .ao-find-fields { gap: 0.5rem; }
        .ao-find-glass { display: none; }
        .ao-find-adv, .ao-find-go { padding-inline: 0.7rem; }
    }

    /* Below tablet width the band stacks: full-width fields, full-width buttons, no glass. */
    @media (max-width: 760px) {
        .ao-find { flex-direction: column; align-items: stretch; }
        .ao-find-glass { display: none; }
        .ao-find-field,
        .ao-find-wide { min-width: 100%; }
        .ao-find-adv,
        .ao-find-go { width: 100%; justify-content: center; }
    }

    .ao-find-field input:focus,
    .ao-find-field select:focus {
        outline: 2px solid var(--wa-link, #337ab7);
        outline-offset: -1px;
    }

    .ao-find-go,
    /* As a link too (Products/Services' "Open Full Service", issue #4): the skin's
       generic anchor colour outweighed the button's white, leaving blue-on-blue —
       a solid blue pill with invisible text. */
    .fi-body a.ao-find-go {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        height: 2.1rem;
        padding: 0 1.4rem;
        flex: none;
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-link, #337ab7);
        color: #fff;
        font-weight: 600;
        font-size: 0.9375rem;
        text-decoration: none;
        cursor: pointer;
    }

    .ao-find-go:hover { background: #286090; }

    /* The reference's "+ Advanced": a quiet bordered button beside the blue Search. */
    .ao-find-adv {
        height: 2.4rem;
        flex: none;
        white-space: nowrap;
        height: 2.1rem;
        padding: 0 0.9rem;
        flex: none;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-text, #2b2b2b);
        font-size: 0.9375rem;
        white-space: nowrap;
        cursor: pointer;
    }

    .ao-find-adv:hover { background: #f0f0f0; }

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
        border-radius: var(--wa-radius, 6px);
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
        /* Separate, not collapse: collapsed borders refuse corner radii, and issue #2 asks
           for the grid's edges rounded like the reference's panels. */
        border-collapse: separate;
        border-spacing: 0;
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-mu-grid thead th:first-child { border-start-start-radius: var(--wa-radius, 6px); }
    .ao-mu-grid thead th:last-child { border-start-end-radius: var(--wa-radius, 6px); }
    .ao-mu-grid tbody tr:last-child td:first-child { border-end-start-radius: var(--wa-radius, 6px); }
    .ao-mu-grid tbody tr:last-child td:last-child { border-end-end-radius: var(--wa-radius, 6px); }

    .ao-mu-grid th {
        padding: 0.45rem 0.6rem;
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        font-size: 0.82rem;
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
        color: var(--wa-muted, #6b6b6b);
    }

    /* Status as the reference badges it: a solid pill, uppercase white lettering — the
       green ACTIVE column Leandro boxed. Plain coloured text did not read as a status. */
    .ao-mu-status {
        display: inline-block;
        padding: 0.15rem 0.65rem;
        border-radius: var(--wa-radius, 6px);
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: #fff;
    }

    .ao-mu-status.ao-mu-active { background: #77c13a; }
    .ao-mu-status.ao-mu-inactive { background: #9ea6ad; }

    /* Service states, in the reference's traffic colours. */
    .ao-mu-status.ao-mu-st-active { background: #77c13a; }
    .ao-mu-status.ao-mu-st-pending { background: #f0ad4e; }
    .ao-mu-status.ao-mu-st-suspended { background: #9ea6ad; }
    .ao-mu-status.ao-mu-st-cancelled { background: #d9534f; }

    /* The reference's Search/Filter tab: a file-folder tab that sits on its strip's
       full-width rule — the -1px pull lets the tab's own border merge into the line. */
    .ao-mu-tab {
        display: inline-block;
        margin-bottom: -1px;
        padding: 0.35rem 1rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: 4px 4px 0 0;
        background: #f0f0f0;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
    }

    .ao-mu-tab.ao-on,
    .ao-mu-tab:hover { background: #e2e2e2; }

    .ao-mu-left { text-align: left !important; }

    .ao-mu-dim { color: var(--wa-muted, #6b6b6b); }

    .ao-mu-cell-icon {
        width: 1rem;
        height: 1rem;
        color: var(--wa-link, #337ab7);
    }

    /* --- Add New Client: the reference's two-column zebra form --- */
    .ao-anc-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 2.5rem;
        align-items: start;
    }

    .ao-anc-row {
        display: grid;
        grid-template-columns: 9.5rem 1fr;
        align-items: center;
        gap: 0.9rem;
        padding: 0.45rem 0.7rem;
    }

    /* The reference stripes alternate rows — both shapes a card comes in: the two-column
       grid (.ao-anc-col, Add New Client's own layout) and the plain single-column card
       every other .ao-anc-card form uses (Open New Ticket among fourteen pages). Only the
       second was ever striped; the first was missed entirely, which is what read as pages
       whose rows do not "align" with the reference the way Add New Client's do. Wide rows
       are excluded — they already carry their own explicit background via .ao-anc-grey,
       and striping on top would fight it depending on which rule happens to win. */
    .ao-anc-col > .ao-anc-row:nth-child(odd),
    .ao-anc-card > .ao-anc-row:not(.ao-anc-row-wide):nth-child(odd) {
        background: #f0f0f0;
    }

    .ao-anc-row > span:first-child {
        text-align: right;
        font-weight: 600;
        color: var(--wa-ink, #333);
    }

    .ao-anc-row input[type="text"],
    .ao-anc-row input[type="email"],
    .ao-anc-row input[type="number"],
    .ao-anc-row select {
        width: 100%;
        height: 2.1rem;
        padding: 0 0.55rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font-size: 15px;
    }

    .ao-anc-field {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .ao-anc-field i {
        font-style: normal;
        color: var(--wa-muted, #6b6b6b);
        white-space: nowrap;
    }

    .ao-anc-generate {
        flex: none;
        height: 2.1rem;
        padding: 0 0.8rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        cursor: pointer;
        white-space: nowrap;
    }

    .ao-anc-generate:hover { background: #f0f0f0; }

    /* Brazil's registry block. Set apart from the rest of the form on purpose: it appears
       only for one country, its contents change with the kind of person being registered,
       and it is the part an admin has to get right for a tax document to be issuable. */
    .ao-anc-brazil {
        margin-top: 1.2rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fbfbfb;
    }

    .ao-anc-brazil-head {
        display: flex;
        align-items: baseline;
        gap: 0.6rem;
        flex-wrap: wrap;
        padding: 0.6rem 1rem;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
        background: #f0f0f0;
        border-start-start-radius: var(--wa-radius, 6px);
        border-start-end-radius: var(--wa-radius, 6px);
        font-weight: 600;
    }

    .ao-anc-brazil-head i {
        font-style: normal;
        font-weight: 400;
        font-size: 0.9rem;
        color: var(--wa-muted, #6b6b6b);
    }

    /* The Isento tick sits with the field it stands in for, not on a row of its own. */
    .ao-anc-inline {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex: none;
        white-space: nowrap;
        font-weight: 400;
    }

    .ao-anc-field input:disabled {
        background: #f0f0f0;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-anc-errors {
        margin-top: 1rem;
        padding: 0.7rem 1rem;
        border: 1px solid #ebccd1;
        border-radius: var(--wa-radius, 6px);
        background: #f2dede;
        color: #a94442;
    }

    /* Issue #27: the amber warning colour core itself uses for a genuine risk, not the red
       reserved for a validation error — nothing has gone wrong here, the page just cannot
       be trusted to update this particular install correctly. */
    .ao-upd-notice {
        margin: 1rem 0 1.4rem;
        padding: 0.8rem 1rem;
        border: 1px solid #f0e0b0;
        border-radius: var(--wa-radius, 6px);
        background: #fcf8e3;
        color: #8a6d3b;
        max-width: 46rem;
    }

    .ao-upd-notice p { margin: 0 0 0.5rem; }
    .ao-upd-notice p:last-child { margin-bottom: 0; }

    .ao-anc-submit {
        margin-top: 1.2rem;
        text-align: center;
    }

    /* One column on a narrow window — the reference collapses the same way. */
    @media (max-width: 1100px) {
        .ao-anc-cols { grid-template-columns: 1fr; }
    }

    /* The reference's bordered card around the whole form; the send line and the button
       live below it. */
    .ao-anc-card {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 0.8rem 1rem;
    }

    /* The reference's full-width rows under the columns; Settings and Owner sit on the
       stripe, as its screenshot has them. */
    .ao-anc-row-wide {
        grid-template-columns: 9.5rem 1fr;
        /* Centred, not top-aligned: the reference sits "Email Notifications", "Settings",
           "Owner" and "Admin Notes" in the vertical middle of their bands. */
        align-items: center;
    }

    .ao-anc-grey { background: #f0f0f0; }

    /* The blank alignment rows must be as tall as a real one, or they align nothing. */
    .ao-anc-row[aria-hidden] { min-height: calc(2.1rem + 0.9rem); }

    .ao-anc-checks {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .ao-anc-checks label {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        cursor: pointer;
    }

    .ao-anc-dim { cursor: not-allowed; }

    .ao-anc-checkall {
        align-self: flex-start;
        margin-top: 0.2rem;
        padding: 0;
        border: 0;
        background: none;
        color: var(--wa-link, #337ab7);
        cursor: pointer;
    }

    .ao-anc-checkall:hover { text-decoration: underline; }

    /* The reference's ON/OFF switches, three to a row. */
    .ao-anc-toggles {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.55rem 1.5rem;
    }

    @media (max-width: 1100px) { .ao-anc-toggles { grid-template-columns: 1fr; } }

    .ao-anc-switch {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        cursor: pointer;
    }

    .ao-anc-switch input { display: none; }

    .ao-anc-switch i {
        flex: none;
        width: 58px;
        height: 20px;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: 2px;
        background: #fff;
        position: relative;
        font-style: normal;
    }

    .ao-anc-switch i::before {
        content: 'OFF';
        position: absolute;
        inset-block: 0;
        right: 0;
        width: 55%;
        display: grid;
        place-items: center;
        background: #e6e6e6;
        color: #555;
        font-size: 10px;
        font-weight: 700;
    }

    .ao-anc-switch input:checked + i::before {
        content: 'ON';
        right: auto;
        left: 0;
        background: var(--wa-link, #337ab7);
        color: #fff;
    }

    .ao-anc-send {
        margin-top: 1.1rem;
        text-align: center;
    }

    .ao-anc-send label {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        cursor: pointer;
    }

    /* --- Client Profile Summary: the reference's four panel columns and banded tables --- */
    .ao-cs-head h2 {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--wa-ink, #333);
        margin-bottom: 0.9rem;
    }

    .ao-cs-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 15px;
        align-items: start;
        margin-bottom: 15px;
    }

    @media (max-width: 1400px) { .ao-cs-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 800px) { .ao-cs-grid { grid-template-columns: 1fr; } }

    .ao-cs-col {
        display: flex;
        flex-direction: column;
        gap: 15px;
        min-width: 0;
    }

    .ao-cp {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    /* The reference's panel titles: navy on a quiet band, centred. */
    .ao-cp > h3 {
        padding: 0.5rem 0.75rem;
        background: #ededed;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
        text-align: center;
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--wa-blue, #1a4d80);
    }

    .ao-cp-body {
        padding: 0.6rem 0.75rem;
    }

    .ao-cp-empty {
        text-align: center;
        color: var(--wa-muted, #6b6b6b);
        padding-block: 0.9rem;
    }

    .ao-cp-kv {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0.5rem;
    }

    .ao-cp-kv td {
        padding: 0.3rem 0.35rem;
        vertical-align: top;
        overflow-wrap: anywhere;
    }

    .ao-cp-kv td:first-child {
        width: 45%;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-cp-kv tr + tr td {
        border-top: 1px solid #f0f0f0;
    }

    .ao-cp-kv-band td {
        background: #ededed;
        font-weight: 700;
        color: var(--wa-ink, #333);
    }

    .ao-cp-link {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.25rem 0.35rem;
        color: var(--wa-link, #337ab7);
        background: none;
        border: 0;
        font-size: inherit;
        cursor: pointer;
        text-align: left;
        width: 100%;
    }

    .ao-cp-link:hover { text-decoration: underline; }

    .ao-cp-ic {
        width: 1rem;
        height: 1rem;
        flex: none;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-cp-mail {
        padding: 0.25rem 0.1rem;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.85rem;
    }

    .ao-cp-mail span { color: var(--wa-muted, #6b6b6b); }

    .ao-cp-send {
        display: inline-flex;
        justify-content: center;
        text-align: center;
    }

    /* The banded tables under the panels — grey title strip, then the navy grid. */
    .ao-cs-band {
        margin-bottom: 15px;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        overflow: hidden;
    }

    .ao-cs-band > h4 {
        padding: 0.45rem 0.75rem;
        background: #e7e7e7;
        text-align: center;
        font-weight: 700;
        color: var(--wa-ink, #333);
    }

    /* The rail's "- Category" sub-entries sit indented under List All, as the reference's. */
    .ao-rail-sub a {
        padding-inline-start: 0.9rem;
    }

    /* The reference's per-band footer: Show n entries · Showing x to y · Previous 1 Next. */
    .ao-cs-band-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.45rem 0.75rem;
        background: #fafafa;
        border-top: 1px solid var(--wa-rule, #d9dadb);
        font-size: 0.9rem;
        color: var(--wa-text, #333);
    }

    .ao-cs-band-foot select {
        padding: 0.05rem 0.3rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-cs-band-pages {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .ao-cs-band-pages button {
        padding: 0.2rem 0.7rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-cs-band-pages i {
        display: inline-grid;
        place-items: center;
        min-width: 1.7rem;
        height: 1.7rem;
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        font-style: normal;
        font-weight: 700;
    }

    .ao-cp-notes {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        font: inherit;
        resize: vertical;
    }

    .ao-cp-notes-save {
        margin-top: 0.5rem;
    }

    /* Issue #6's "disproportionate": the link and the button in an action pair must be
       the same box — one picked up a stray border/padding and read as twice the size. */
    .ao-mu-iconpair a,
    .ao-mu-iconpair button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.6rem;
        height: 1.6rem;
        margin-inline: 0.15rem;
        border: 0 !important;
        border-radius: 4px;
        padding: 0 !important;
        background: none !important;
        box-shadow: none !important;
        vertical-align: middle;
    }

    .ao-mu-iconpair a:hover,
    .ao-mu-iconpair button:hover { background: #f0f0f0 !important; }

    /* The Record Withdrawal line under the ledger (issue #6). */
    .ao-af-withdraw {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.8rem;
    }

    .ao-af-withdraw input {
        height: 2.2rem;
        padding: 0 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: 4px;
        font-size: 0.9rem;
    }

    .ao-af-withdraw input[aria-label="Amount"] { width: 8rem; }
    .ao-af-withdraw input[aria-label="Currency"] { width: 4.5rem; text-transform: uppercase; }
    .ao-af-withdraw input[aria-label="Note"] { flex: 1 1 14rem; }

    .ao-mu-icon-red { color: #d9534f; }

    /* The reference's tab strip: boxed buttons in a row, the active one white and joined to
       the content below it — not the underline style Filament favours. */
    .ao-tabs {
        display: flex;
        gap: 0.2rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
        margin-bottom: 1rem;
    }

    .ao-tab {
        flex: none;
        padding: 0.45rem 0.9rem;
        margin-bottom: -1px;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px) 3px 0 0;
        background: #ededed;
        color: var(--wa-text, #333);
        cursor: pointer;
    }

    .ao-tab:hover { background: #ececec; }

    .ao-tab-active,
    .ao-tab-active:hover {
        background: #fff;
        border-bottom-color: #fff;
        color: var(--wa-ink, #333);
        font-weight: 600;
    }

    /* The client switcher, as the reference draws it under the page title. */
    /* Above the tab bar now, as the reference places it. */
    .ao-cs-switch { margin-bottom: 0.7rem; }

    /* Doubled selector: .ao-cp-link sets its own colour later in the sheet, and the red
       must beat it. */
    .ao-cp-link.ao-cp-danger { color: #d9534f; }

    .ao-cs-switch select {
        min-width: 24rem;
        height: 2.2rem;
        padding: 0 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font-size: inherit;
    }

    .ao-cs-filter-tag {
        text-align: right;
        margin-bottom: 0.4rem;
        font-size: 0.9rem;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-cs-selected {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        margin-top: -0.4rem;
        margin-bottom: 1rem;
    }

    .ao-cs-selected button {
        padding: 0.3rem 0.8rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-text, #333);
        opacity: 0.65;
        cursor: not-allowed;
    }

    .ao-cs-selected .ao-cs-danger {
        background: #d9534f;
        border-color: #d43f3a;
        color: #fff;
    }

    .ao-cp-wide { max-width: 56rem; }

    /* The reference's flags strip beside the profile heading. */
    .ao-cs-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.6rem;
    }

    .ao-cs-flags {
        display: flex;
        flex-wrap: wrap;
        gap: 0;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #f0f0f0;
        font-size: 0.9rem;
    }

    .ao-cs-flags > span {
        padding: 0.3rem 0.7rem;
    }

    .ao-cs-flags > span + span {
        border-inline-start: 1px solid var(--wa-panel-border, #ddd);
    }

    .ao-cs-yes { color: #3c763d; text-decoration: underline; }
    .ao-cs-no { color: #d9534f; text-decoration: underline; }

    /* A control the reference draws that Paymenter has nothing behind: lettered like a
       link, does nothing, says why on hover. */
    .ao-cp-dead {
        color: var(--wa-link, #337ab7);
        opacity: 0.75;
        cursor: not-allowed;
    }

    /* ── Responsive backstops ── */
    .ao-cs-switch select { max-width: 100%; }

    .ao-find { flex-wrap: wrap; }

    @media (max-width: 900px) {
        .ao-mu-line { flex-wrap: wrap; }
        .ao-cs-flags { font-size: 0.82rem; }
    }

    /* Wide grids scroll inside their panel rather than widening the page. */
    .ao-mu {
        overflow-x: auto;
    }

    /* Actions as the reference's small buttons, not bare links. */
    .ao-mu-actions a,
    /* Wire-action buttons in the same cell read as the same small button (issue #45's
       Enable/Disable beside Edit) — and the same class works standalone wherever a
       quick-action button sits outside a table cell (Currencies' update pair). */
    button.ao-pg-btn,
    a.ao-pg-btn {
        display: inline-block;
        margin-inline: 0.15rem;
        padding: 0.25rem 0.75rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-text, #2b2b2b);
        white-space: nowrap;
        cursor: pointer;
        font-size: inherit;
    }

    button.ao-pg-btn:hover, a.ao-pg-btn:hover { background: #f0f0f0; }

    .ao-mu-actions a:hover {
        background: #f0f0f0;
        text-decoration: none;
    }

    /* The reference's email badges: quiet grey when verified, warm when not. */
    .ao-mu-mail {
        display: inline-block;
        margin-inline-start: 0.4rem;
        padding: 0.1rem 0.45rem;
        border-radius: var(--wa-radius, 6px);
        font-size: 0.66rem;
        font-style: normal;
        font-weight: 600;
        vertical-align: middle;
        white-space: nowrap;
    }

    .ao-mu-mail-ok { background: #e6e6e6; color: #555; }
    /* Grey like Verified — the reference tints neither badge. */
    .ao-mu-mail-no { background: #e6e6e6; color: #555; }

    /* Two Factor, the reference's way: shield and word, grey off, green on — not a pill. */
    .ao-mu-2fa {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        color: var(--wa-text, #2b2b2b);
    }

    .ao-mu-2fa svg { width: 14px; height: 14px; }

    .ao-mu-2fa-on { color: #3c763d; font-weight: 600; }

    /* Manage User: the reference's dropdown button, on a native <details> so it needs no
       script and closes itself. The menu overlays; the row does not grow. */
    .ao-mu-manage {
        position: relative;
        display: inline-block;
    }

    .ao-mu-manage summary {
        list-style: none;
        cursor: pointer;
        padding: 0.25rem 0.75rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        white-space: nowrap;
    }

    .ao-mu-manage summary::-webkit-details-marker { display: none; }

    .ao-mu-manage summary:hover { background: #f0f0f0; }

    .ao-mu-manage[open] summary {
        background: #e6e6e6;
        border-color: #adadad;
    }

    .ao-mu-manage-menu {
        position: absolute;
        inset-inline-end: 0;
        top: calc(100% + 2px);
        z-index: 20;
        min-width: 8rem;
        padding: 0.25rem 0;
        border: 1px solid #c3c3c3;
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        box-shadow: 0 6px 12px rgb(0 0 0 / 0.175);
        text-align: left;
    }

    .ao-mu-manage-menu a,
    .ao-mu-manage-menu button {
        display: block;
        width: 100%;
        margin: 0;
        padding: 0.35rem 1rem;
        border: 0;
        border-radius: 0;
        background: none;
        text-align: start;
        font: inherit;
        font-size: 0.9rem;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
        white-space: nowrap;
    }

    .ao-mu-manage-menu a:hover,
    .ao-mu-manage-menu button:hover { background: #f0f0f0; }

    /* The reference's split button: Manage User plus a joined caret that drops the menu. */
    .ao-mu-split {
        display: inline-flex;
        align-items: stretch;
    }

    .ao-mu-split > button {
        padding: 0.25rem 0.75rem;
        border: 1px solid var(--wa-border, #ccc);
        border-start-end-radius: 0;
        border-end-end-radius: 0;
        border-start-start-radius: 3px;
        border-end-start-radius: 3px;
        background: #fff;
        font: inherit;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
        white-space: nowrap;
    }

    .ao-mu-split > button:hover { background: #f0f0f0; }

    .ao-mu-split .ao-mu-manage summary {
        padding: 0.25rem 0.5rem;
        margin-inline-start: -1px;
        border-start-start-radius: 0;
        border-end-start-radius: 0;
        background: #e6e6e6;
        height: 100%;
        display: flex;
        align-items: center;
    }

    /* Manage User modal, to the reference: dimmed page, navy title bar, right-aligned
       labels, the accounts table, and the delete/close/save footer. */
    .ao-mud-overlay {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 4rem 1rem 2rem;
        background: rgba(0, 0, 0, 0.5);
        overflow-y: auto;
    }

    .ao-mud {
        width: 56rem;
        max-width: 100%;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        color: var(--wa-text, #2b2b2b);
        /* The footer un-indents itself with negative margins; nothing may escape the box. */
        overflow: hidden;
    }

    .ao-mud-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.7rem 1.1rem;
        border-radius: 8px 8px 0 0;
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        font-size: 1.05rem;
    }

    .ao-mud-head button {
        border: 0;
        background: none;
        color: #fff;
        opacity: 0.7;
        font-size: 1.3rem;
        line-height: 1;
        cursor: pointer;
    }

    .ao-mud-head button:hover { opacity: 1; }

    .ao-mud-body { padding: 1.3rem 1.5rem 0; }

    .ao-mud-row {
        display: grid;
        grid-template-columns: 11rem 1fr;
        gap: 1.4rem;
        align-items: center;
        margin-bottom: 0.9rem;
    }

    .ao-mud-row > span:first-child {
        text-align: end;
        font-weight: 600;
    }

    .ao-mud-row input[type="text"],
    .ao-mud-row input[type="email"],
    .ao-mud-row select {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
    }

    .ao-mud-switch { margin: 0; }

    .ao-mud-accounts { align-items: start; }

    .ao-mud-accounts > span:first-child { padding-top: 0.35rem; }

    .ao-mud-accounts table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .ao-mud-accounts th {
        text-align: start;
        padding: 0.35rem 0.6rem;
        border-bottom: 1px solid var(--wa-rule, #d9dadb);
        font-weight: 600;
    }

    .ao-mud-accounts td {
        padding: 0.4rem 0.6rem;
        background: #f0f4f8;
    }

    .ao-mud-owner { color: #3c763d; font-weight: 700; }

    .ao-mud-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        margin-top: 1.4rem;
        padding: 0.9rem 1.5rem;
        border-top: 1px solid var(--wa-panel-border, #ddd);
        margin-inline: -1.5rem;
    }

    .ao-mud-foot button {
        padding: 0.45rem 1rem;
        border-radius: var(--wa-radius, 6px);
        border: 1px solid transparent;
        font: inherit;
        cursor: pointer;
    }

    .ao-mud-delete {
        background: #e77471;
        color: #fff;
    }

    .ao-mud-delete:hover { background: #d9534f; }

    .ao-mud-foot-right { display: flex; gap: 0.6rem; }

    .ao-mud-close {
        background: #fff;
        border-color: var(--wa-border, #ccc) !important;
        color: var(--wa-text, #2b2b2b);
    }

    .ao-mud-close:hover { background: #f0f0f0; }

    .ao-mud-save {
        background: #337ab7;
        color: #fff;
    }

    .ao-mud-save:hover { background: #286090; }

    @media (max-width: 700px) {
        .ao-mud-row { grid-template-columns: 1fr; gap: 0.3rem; }
        .ao-mud-row > span:first-child { text-align: start; }
    }

    /* ── Manage Orders ──────────────────────────────────────────────────────
       The reference's status words are coloured text, not pills. */
    .ao-mo-complete { color: #3c763d; }
    .ao-mo-incomplete { color: #d9534f; }
    .ao-mo-active { color: #7ac143; }
    .ao-mo-pending { color: #f0ad4e; }
    .ao-mo-suspended { color: #d9534f; }
    .ao-mo-terminated { color: var(--wa-muted, #6b6b6b); }

    .ao-mo-delete {
        border: 0;
        background: none;
        padding: 0;
        cursor: pointer;
        color: #d9534f;
    }

    /* The reference's green Accept Order in the With Selected bar. */
    .ao-mu-selected .ao-mo-accept {
        background: #5cb85c;
        border-color: #4cae4c;
        color: #fff;
    }

    .ao-mu-selected .ao-mo-accept:hover { background: #449d44; }

    .ao-mo-delete:hover { color: #b52b27; }

    /* ── Add New Order ──────────────────────────────────────────────────────
       The reference's split: the striped form on the left, Order Summary card right. */
    .ao-ano {
        display: flex;
        gap: 2rem;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .ao-ano-main { flex: 2 1 34rem; min-width: 0; }

    .ao-ano-heading {
        margin: 1.1rem 0 0.5rem;
        font-weight: 700;
    }

    .ao-ano-checks {
        display: flex;
        gap: 1.2rem;
        flex-wrap: wrap;
    }

    .ao-ano-checks label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .ao-ano-qty { max-width: 6rem; }

    .ao-ano-side {
        flex: 1 1 16rem;
        max-width: 22rem;
        text-align: center;
    }

    .ao-ano-side > h4 {
        font-size: 1.15rem;
        margin-bottom: 0.8rem;
    }

    .ao-ano-card {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: 8px;
        background: #fff;
        margin-bottom: 1rem;
        text-align: start;
    }

    .ao-ano-none {
        padding: 0.7rem 1rem;
        text-align: center;
        color: var(--wa-muted, #6b6b6b);
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
    }

    .ao-ano-line,
    .ao-ano-sub,
    .ao-ano-total {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.55rem 0.9rem;
    }

    .ao-ano-line { border-bottom: 1px solid var(--wa-panel-border, #ddd); }

    /* The reference's "» Region: …" annotation, indented under the line it belongs to. */
    /* Issue #10, twice over: "same font size as the general page" and "it is not
       legible" — this note read smaller and greyer than the line it belongs to. */
    /* Issue #10 "it is illegible": the note was crammed against the line above by a
       negative margin and inherited a small size — give it its own breathing room and
       the content type size. */
    .ao-ano-note {
        padding: 0.2rem 0.9rem 0.55rem 1.6rem;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
        color: var(--wa-ink, #2b2b2b);
        font-size: 0.95rem;
        line-height: 1.45;
    }

    /* A line's option fields — core's own ConfigOption tree plus a server's checkout
       fields (ProxyPanel's Region among them) — set apart from the fixed fields above.
       No heading: Leandro's #10, these are not WHMCS's configurable-options concept. */
    .ao-ano-configs {
        margin-top: 0.4rem;
        padding-top: 0.6rem;
        border-top: 1px dashed var(--wa-panel-border, #ddd);
    }

    .ao-ano-radios {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 1rem;
    }

    .ao-ano-radios label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-weight: 400;
    }

    .ao-ano-sub { background: #fdfce5; border-bottom: 1px solid var(--wa-panel-border, #ddd); }

    .ao-ano-total {
        background: #eaf6e6;
        font-size: 1.15rem;
        font-weight: 700;
    }

    /* The reference's pink "Recurring" strip under Total — what keeps billing after this
       invoice, separate from the one-off Total above it. */
    .ao-ano-recurring {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.45rem 0.9rem;
        background: #f2dede;
        color: #a94442;
        font-size: 0.9rem;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
    }

    .ao-ano-recurring:last-child { border-bottom: none; }

    /* The reference's credit-balance card: a plain note plus the two-way choice, sitting
       between the summary card and Submit Order the way the reference has it. */
    .ao-ano-credit {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 8px);
        background: #fbfbfb;
        padding: 0.8rem 1rem;
        margin-bottom: 1rem;
        text-align: start;
        font-size: 0.9rem;
    }

    .ao-ano-credit p { margin: 0 0 0.6rem; color: var(--wa-text, #2b2b2b); }

    .ao-ano-credit-opt {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        font-weight: 400;
        line-height: 1.35;
        margin-bottom: 0.5rem;
    }

    .ao-ano-credit-opt:last-child { margin-bottom: 0; }

    .ao-ano-credit-opt input { margin-top: 0.2rem; flex: none; }

    .ao-ano-submit { padding: 0.55rem 1.6rem; }

    .ao-ano-item + .ao-ano-item { margin-top: 0.8rem; }

    /* The reference's green "+ Add Another Product" pill. */
    .ao-ano-add {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.8rem;
        padding: 0.35rem 0.9rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9375rem;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
    }

    .ao-ano-add span { color: #7ac143; }

    .ao-ano-add:hover { background: #f0f0f0; }

    .ao-ano-remove {
        border: 0;
        background: none;
        padding: 0 0.3rem;
        font-size: 1.1rem;
        line-height: 1;
        color: #d9534f;
        cursor: pointer;
    }

    /* The reference styles Create Custom Promo as a small bordered button. */
    .ao-ano-promo {
        white-space: nowrap;
        font-size: 0.9rem;
        padding: 0.3rem 0.7rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-ano-promo:hover { background: #f0f0f0; text-decoration: none; }

    .ao-ano-off { color: var(--wa-muted, #6b6b6b); cursor: not-allowed; }

    .ao-tx-trans { font-size: 0.85em; color: var(--wa-muted, #6b6b6b); }

    /* The reference's Refresh / Last Updated line over the balance tiles. */
    .ao-tx-bal-line {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-tx-refresh {
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 0.2rem 0.6rem;
        font: inherit;
        font-size: 0.9rem;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
    }

    .ao-tx-refresh:hover { background: #f0f0f0; }

    /* ── Invoices ───────────────────────────────────────────────────────────
       The reference's headline bar: Paid green, Unpaid red, Overdue black-on-grey. */
    /* Issue #12: the reference's totals bar is a centred line in a teal-bordered white
       box, not a grey band. */
    .ao-inv-bar {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.4rem 2rem;
        padding: 0.8rem 1.1rem;
        border: 2px solid #7edfe6;
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font-size: 1.05rem;
        font-weight: 700;
    }

    /* Issue #12: the reference's tab + filter + records line share one bordered panel. */
    .ao-inv-panel {
        border: 1px solid #7edfe6;
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 0.7rem 1rem 0.4rem;
        margin-bottom: 0.6rem;
    }

    .ao-inv-panel .ao-mu-line { margin-bottom: 0.2rem; }

    .ao-inv-bar-paid { color: #3c763d; }
    .ao-inv-bar-unpaid { color: #d9534f; }
    .ao-inv-bar-overdue { color: var(--wa-text, #2b2b2b); }

    .ao-inv-paid { color: #7ac143; }
    .ao-inv-unpaid { color: #d9534f; }
    .ao-inv-overdue { color: #b52b27; font-weight: 700; }
    .ao-inv-cancelled { color: var(--wa-muted, #6b6b6b); }
    .ao-inv-draft { color: #f0ad4e; }
    .ao-inv-refunded { color: #5bc0de; }

    /* ── Transactions ───────────────────────────────────────────────────────
       The reference's chart-and-tiles top, then Gateway Balances. */
    .ao-tx-tabs {
        display: flex;
        gap: 0.3rem;
        align-items: flex-end;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
        margin-bottom: 0.9rem;
    }

    /* An open Search/Filter band hangs straight off the strip's rule, as the
       reference's does — the rule becomes the band's top edge. */
    .ao-tx-tabs:has(+ .ao-find) { margin-bottom: 0; }

    .ao-tx-tabs + .ao-find {
        border-top: 0;
        border-radius: 0 0 var(--wa-radius, 6px) var(--wa-radius, 6px);
    }

    /* Knowledgebase (issue #24): the reference's "Check to Hide" beside the name field,
       and its empty Browse by Tag state. */
    .ao-kb-hide { display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; }

    .ao-kb-notags { margin: 0.3rem 0 0; }

    /* ── Issue #8, corrected ─────────────────────────────────────────────────
       The reference's page title is plain text on the page background — no panel,
       no border (the earlier white band misread the screenshots; the follow-up
       comparisons settled it). The Search/Filter strip below carries the page-wide
       rule instead — that lives on .ao-tx-tabs. Only the spacing is tuned here. */
    .fi-page:has(.ao-mu) .fi-page-header-main-ctn { gap: 0; }

    .fi-page:has(.ao-mu) .fi-header { margin-bottom: 0.75rem; }

    .ao-tx-tab-dead { opacity: 0.7; cursor: not-allowed; }

    .ao-tx-top {
        display: flex;
        gap: 1.6rem;
        align-items: stretch;
        flex-wrap: wrap;
        margin: 0.9rem 0;
    }

    .ao-tx-chart {
        flex: 2 1 30rem;
        min-width: 0;
        position: relative;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 1.6rem 0.8rem 0.4rem;
    }

    .ao-tx-chart svg { width: 100%; height: auto; display: block; }

    .ao-tx-axis {
        position: absolute;
        top: 0.4rem;
        left: 0.8rem;
        font-size: 0.75rem;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-tx-tiles {
        flex: 1 1 16rem;
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
        justify-content: center;
    }

    .ao-tx-tile {
        display: flex;
        align-items: center;
        gap: 0.9rem;
    }

    {{-- Issue #11: the reference draws these as plain grey squares with a white glyph, not
         circular and not colour-coded by type — "colourful icons" is a menu/nav rule, and
         following it here fought the one screenshot Leandro actually pointed at. --}}
    .ao-tx-tile-ic {
        width: 3rem;
        height: 3rem;
        border-radius: var(--wa-radius, 6px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        background: #7c7c7c;
        color: #ffffff;
    }

    .ao-tx-tile-ic svg { width: 1.4rem; height: 1.4rem; }

    .ao-tx-ic-income,
    .ao-tx-ic-fees,
    .ao-tx-ic-out { color: #ffffff; }

    .ao-tx-tile-body { display: flex; flex-direction: column; }

    .ao-tx-tile-label { color: var(--wa-muted, #6b6b6b); }

    .ao-tx-tile-body b { font-size: 1.25rem; }

    .ao-tx-tile-body i,
    .ao-tx-note { font-style: normal; font-size: 0.85rem; color: var(--wa-muted, #6b6b6b); }

    /* The tile context (.ao-tx-tile-body i) outweighed the bare class and painted the
       reference's green/red trend lines grey — matched here at higher specificity. */
    .ao-tx-up,
    .ao-tx-tile-body i.ao-tx-up { color: #3c763d; font-style: normal; font-size: 0.85rem; }
    .ao-tx-down,
    .ao-tx-tile-body i.ao-tx-down { color: #d9534f; font-style: normal; font-size: 0.85rem; }
    /* Issue #11: the reference's Pending gateway balance is teal, not green. */
    .ao-tx-pending { color: #4fc0c8; font-style: normal; }

    .ao-tx-heading { font-weight: 700; margin: 0.4rem 0; }

    .ao-tx-balances {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.9rem;
    }

    .ao-tx-balance {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        min-width: 11rem;
        padding: 0.7rem 1rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-tx-balance b { font-size: 1.05rem; }

    .ao-tx-balance i { font-style: normal; font-size: 0.85rem; color: var(--wa-muted, #6b6b6b); }

    .ao-tx-desc { max-width: 26rem; overflow-wrap: anywhere; }

    .ao-tx-out { color: #d9534f; }

    /* ── Create New Quote ───────────────────────────────────────────────────
       The reference's quote form: two-column General Information, the button row, the
       line-items grid with inputs in its cells, sums on the right. */
    .ao-cq-general {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 2rem;
    }

    @media (max-width: 900px) {
        .ao-cq-general { grid-template-columns: 1fr; }
    }

    .ao-cq-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin: 0.9rem 0;
    }

    .ao-cq-buttons button,
    .ao-cq-buttons a {
        padding: 0.4rem 0.9rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9375rem;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
        text-decoration: none;
    }

    .ao-cq-buttons button:hover,
    .ao-cq-buttons a:hover { background: #f0f0f0; }

    .ao-cq-buttons button:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Compound selector, because .ao-cq-buttons button would otherwise repaint it white. */
    .ao-cq-buttons .ao-find-go {
        border-color: transparent;
        background: #337ab7;
        color: #fff;
    }

    .ao-cq-buttons .ao-find-go:hover { background: #286090; }

    .ao-cq-buttons .ao-cq-delete {
        background: #d9534f;
        border-color: #d43f3a;
        color: #fff;
    }

    .ao-cq-buttons .ao-cq-delete:hover:not(:disabled) { background: #c9302c; }

    .ao-cq-radio {
        display: block;
        margin: 0.5rem 0 0.35rem;
    }

    .ao-cq-client {
        width: 100%;
        height: 2.2rem;
        padding: 0 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        margin-bottom: 0.6rem;
        font: inherit;
    }

    .ao-cq-items td { padding: 0.3rem 0.4rem; }

    .ao-cq-items input[type="text"],
    .ao-cq-items input[type="number"] {
        width: 100%;
        min-width: 4.5rem;
        height: 2rem;
        padding: 0 0.5rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        font: inherit;
        font-size: 0.9375rem;
    }

    .ao-cq-items .ao-cq-desc { min-width: 16rem; }

    .ao-cq-qty { width: 4.5rem; }
    .ao-cq-num { width: 7.5rem; }
    .ao-cq-taxed { width: 4rem; text-align: center; }
    .ao-cq-total { white-space: nowrap; }

    .ao-cq-under {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 0.6rem;
    }

    .ao-cq-addline {
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 0.3rem 0.8rem;
        font: inherit;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .ao-cq-pre { margin-inline-start: 0.8rem; font-size: 0.9rem; }

    .ao-cq-pre select {
        margin-inline-start: 0.4rem;
        height: 2rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9rem;
    }

    .ao-cq-sums td { padding: 0.25rem 0.9rem; text-align: end; }

    .ao-cq-sums .ao-cq-due td { font-weight: 700; border-top: 1px solid var(--wa-rule, #d9dadb); }

    .ao-cq-notes .ao-anc-row { align-items: start; }

    .ao-cq-notes .ao-anc-row > span i {
        display: block;
        font-style: normal;
        font-weight: 400;
        font-size: 0.82rem;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-cq-notes textarea {
        width: 100%;
        padding: 0.45rem 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        font: inherit;
        font-size: 0.9375rem;
    }

    /* ── Support suite ──────────────────────────────────────────────────────
       Tickets, overview tiles and charts, predefined replies, downloads, network
       issues — the reference's Support wing. */
    .ao-st-bulk {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin: 0.6rem 0;
        font-weight: 600;
    }

    .ao-st-bulk button {
        padding: 0.25rem 0.7rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9rem;
        font-weight: 400;
        cursor: pointer;
    }

    .ao-st-bulk button:hover { background: #f0f0f0; }

    .ao-st-bulk .ao-st-danger {
        background: #d9534f;
        border-color: #d43f3a;
        color: #fff;
    }

    .ao-st-bulk .ao-st-danger:hover { background: #c9302c; }

    .ao-st-flag { width: 1.6rem; color: #d9534f; }

    .ao-st-open { color: #3c763d; }
    .ao-st-answered { color: #8a6d3b; }
    .ao-st-closed { color: #6b6b6b; }

    .ao-st-operator {
        display: block;
        margin-top: 0.15rem;
        font-style: normal;
        font-size: 0.72rem;
        letter-spacing: 0.03em;
        color: #31708f;
        background: #d9edf7;
        border-radius: 2px;
        padding: 0.05rem 0.3rem;
        width: fit-content;
        margin-inline: auto;
    }

    /* Support Overview: the Displaying band, the cream tiles, the two charts. */
    .ao-so-band {
        padding: 0.6rem 0.9rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #f0f0f0;
        margin-bottom: 1rem;
    }

    .ao-so-band select {
        margin-inline-start: 0.5rem;
        height: 2rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9375rem;
    }

    .ao-so-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
        gap: 0.9rem;
        margin-bottom: 1.2rem;
    }

    .ao-so-tile {
        background: #fdf7e3;
        border-radius: var(--wa-radius, 6px);
        padding: 0.9rem;
        text-align: center;
    }

    .ao-so-tile span { display: block; margin-bottom: 0.4rem; }

    .ao-so-tile b { font-size: 1.5rem; color: var(--wa-blue, #1a4d80); }

    .ao-so-charts {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
    }

    @media (max-width: 900px) {
        .ao-so-charts { grid-template-columns: 1fr; }
    }

    .ao-so-chart h4 { font-weight: 700; margin-bottom: 0.5rem; text-align: center; }

    .ao-so-none { text-align: center; color: var(--wa-muted, #6b6b6b); padding: 3rem 0; }

    /* Open New Ticket. */
    .ao-ont-send { white-space: nowrap; margin-inline-start: 0.6rem; }

    .ao-ont-dept b { margin-inline-start: 1rem; }

    .ao-ont-priority { max-width: 9rem; }

    .ao-ont-services { margin: 1rem 0; }

    .ao-ont-editor {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
    }

    .ao-ont-toolbar {
        display: flex;
        gap: 0.25rem;
        padding: 0.4rem;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
        background: #fafafa;
    }

    .ao-ont-toolbar button {
        min-width: 2rem;
        padding: 0.25rem 0.5rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .ao-ont-toolbar button:hover { background: #f0f0f0; }

    .ao-ont-toolbar .ao-ont-preview.ao-on,
    .ao-ont-toolbar .ao-ont-preview:hover {
        background: #337ab7;
        border-color: #2e6da4;
        color: #fff;
    }

    .ao-ont-editor textarea {
        width: 100%;
        border: 0;
        padding: 0.7rem;
        font: inherit;
        font-size: 0.9rem;
        resize: vertical;
    }

    .ao-ont-rendered { padding: 0.7rem 1rem; min-height: 12rem; }

    .ao-ont-rendered :is(h1, h2, h3) { font-weight: 700; margin: 0.6rem 0 0.3rem; }

    .ao-ont-rendered ul { list-style: disc; padding-inline-start: 1.4rem; }

    .ao-ont-rendered ol { list-style: decimal; padding-inline-start: 1.4rem; }

    .ao-ont-inserts {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin: 0.8rem 0;
    }

    .ao-ont-attach .ao-anc-row > span:first-child i {
        display: block;
        font-style: normal;
        font-size: 0.82rem;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-ont-submit { text-align: center; margin: 1rem 0; }

    /* The related-service radio column (issue #20). */
    .ao-ont-radio { width: 2rem; text-align: center; }

    .ao-ont-radio input { accent-color: #337ab7; }

    .ao-ont-pick { display: flex; flex-direction: column; gap: 0.3rem; max-height: 40vh; overflow-y: auto; }

    /* Predefined Replies / Downloads shared furniture. */
    .ao-pr-center { text-align: center; margin: 0.9rem 0; }

    .ao-pr-crumb { margin: 0.8rem 0 0.6rem; }

    /* The reference paints the crumb's location in link blue (issues #21, #23, #24). */
    .ao-pr-here { color: var(--wa-link, #337ab7); }

    .ao-pr-none { margin: 0.8rem 0; }

    .ao-pr-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.5rem 0.2rem;
        border-bottom: 1px solid var(--wa-rule, #e5e5e5);
    }

    .ao-pr-name { font-weight: 600; }

    .ao-pr-reply summary { cursor: pointer; font-weight: 600; }

    .ao-pr-reply p { margin-top: 0.4rem; white-space: pre-wrap; color: var(--wa-text, #2b2b2b); }

    .ao-dl-band {
        margin: 0.8rem 0 0.4rem;
        padding: 0.55rem 0.9rem;
        border-radius: var(--wa-radius, 6px);
        background: #f0f0f0;
        font-weight: 600;
        font-size: 1.05rem;
    }

    .ao-dl-desc { color: var(--wa-muted, #6b6b6b); font-size: 0.9rem; }

    /* Network Issues: the reference's red validation banner. */
    .ao-ni-error {
        display: flex;
        align-items: flex-start;
        gap: 0.8rem;
        padding: 0.9rem 1.1rem;
        border: 1px solid #ebccd1;
        border-radius: var(--wa-radius, 6px);
        background: #f2dede;
        color: #a94442;
        margin-bottom: 1rem;
    }

    .ao-ni-error b { font-size: 1.05rem; }

    .ao-ni-desc {
        width: 100%;
        padding: 0.6rem 0.8rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        font: inherit;
        font-size: 0.9rem;
    }

    .ao-ont-viewall { margin: 0.2rem 0 0.9rem; }

    .ao-rail-none { padding: 0.6rem 0.9rem; color: var(--wa-muted, #6b6b6b); }

    /* ── Rich editor ─────────────────────────────────────────────────────────
       {@see resources/views/components/rich-editor.blade.php}. One frame around a
       toolbar and either half of the toggle — the reference's own two-state affordance,
       both halves genuinely editable now rather than one being a read-only preview. */
    .ao-an-editor-wrap { display: block; width: 100%; }

    .ao-rte {
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        overflow: hidden;
    }

    .ao-rte-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.15rem;
        padding: 0.4rem 0.5rem;
        background: #f5f5f5;
        border-bottom: 1px solid var(--wa-panel-border, #ddd);
    }

    .ao-rte-toolbar button {
        min-width: 1.9rem;
        height: 1.9rem;
        padding: 0 0.4rem;
        border: 1px solid transparent;
        border-radius: 4px;
        background: transparent;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .ao-rte-toolbar button:hover {
        background: #fff;
        border-color: var(--wa-border, #ccc);
    }

    .ao-rte-toolbar [data-ao-rte-source] {
        margin-inline-start: auto;
        font-family: monospace;
    }

    .ao-rte-sep {
        width: 1px;
        height: 1.3rem;
        background: var(--wa-panel-border, #ddd);
        margin: 0 0.2rem;
    }

    .ao-rte-area,
    .ao-rte-source {
        min-height: 12rem;
        padding: 0.7rem 0.9rem;
        border: 0;
        width: 100%;
        font: inherit;
        font-size: 0.9375rem;
    }

    .ao-rte-source { font-family: monospace; font-size: 0.85rem; resize: vertical; }

    .ao-rte-area:focus,
    .ao-rte-source:focus { outline: none; }

    .ao-rte-area :where(ul, ol) { padding-inline-start: 1.5rem; }

    .ao-rte-area blockquote {
        margin: 0.5rem 0;
        padding-inline-start: 0.8rem;
        border-inline-start: 3px solid var(--wa-panel-border, #ddd);
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-an-langs { columns: 3; margin: 0.4rem 0 1rem; }

    .ao-an-langs li { font-weight: 700; padding: 0.3rem 0; break-inside: avoid; }

    @media (max-width: 760px) { .ao-an-langs { columns: 1; } }

    /* The reference's framed ticket filter: label left, one control per row. */
    .ao-stf {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        padding: 1rem 1.2rem 0.4rem;
        margin-bottom: 0.9rem;
    }

    .ao-stf-row {
        display: grid;
        grid-template-columns: 9.5rem 1fr;
        gap: 1rem;
        align-items: center;
        margin-bottom: 0.6rem;
    }

    .ao-stf-row > span { text-align: end; font-weight: 600; }

    .ao-stf-row input,
    .ao-stf-row select {
        width: 100%;
        height: 2.2rem;
        padding: 0 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9rem;
    }

    .ao-stf-row input:disabled { background: #f5f5f5; color: var(--wa-muted, #6b6b6b); }

    .ao-stf-row .ao-stf-mid { width: 60%; min-width: 16rem; }

    .ao-stf-row .ao-stf-small { width: 30%; min-width: 11rem; }

    .ao-stf-submit { text-align: center; margin: 0.9rem 0 0.6rem; }

    @media (max-width: 700px) {
        .ao-stf-row { grid-template-columns: 1fr; gap: 0.25rem; }
        .ao-stf-row > span { text-align: start; }
        .ao-stf-row .ao-stf-mid, .ao-stf-row .ao-stf-small { width: 100%; min-width: 0; }
    }

    /* The reference sizes its inputs to their meaning, not the page: width utilities the
       support forms pin themselves with. */
    /* Doubled class: the form rows size their inputs with attribute selectors, and the
       utility must outrank them without resorting to !important. */
    .ao-mu .ao-w-25.ao-w-25 { width: 25%; min-width: 11rem; flex: 0 0 auto; }
    .ao-mu .ao-w-30.ao-w-30 { width: 30%; min-width: 13rem; flex: 0 0 auto; }
    .ao-mu .ao-w-40.ao-w-40 { width: 40%; min-width: 16rem; flex: 0 0 auto; }
    .ao-mu .ao-w-45.ao-w-45 { width: 45%; min-width: 18rem; flex: 0 0 auto; }
    .ao-mu .ao-w-60.ao-w-60 { width: 60%; min-width: 20rem; flex: 0 0 auto; }

    @media (max-width: 760px) {
        .ao-mu .ao-w-25.ao-w-25, .ao-mu .ao-w-30.ao-w-30, .ao-mu .ao-w-40.ao-w-40,
        .ao-mu .ao-w-45.ao-w-45, .ao-mu .ao-w-60.ao-w-60 {
            width: 100%;
            min-width: 0;
        }
    }

    /* ── Reports ────────────────────────────────────────────────────────────
       The reference's landing grid — General cyan, Exports dark, the rest white — and
       the report screen's chart and Tools. */
    .ao-rp-intro { margin-bottom: 1.2rem; }

    .ao-rp-cat {
        text-align: center;
        font-size: 1.2rem;
        color: #555;
        margin: 1.2rem 0 0.7rem;
    }

    .ao-rp-pills {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
    }

    .ao-rp-pill {
        display: inline-block;
        padding: 0.5rem 1rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font-size: 0.9rem;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
        text-decoration: none;
    }

    .ao-rp-pill:hover { background: #f0f0f0; text-decoration: none; }

    .ao-rp-pill.ao-rp-general {
        background: #5bc0de;
        border-color: #46b8da;
        color: #fff;
    }

    .ao-rp-pill.ao-rp-general:hover { background: #31b0d5; }

    .ao-rp-pill.ao-rp-exports {
        background: #4a5a6a;
        border-color: #3f4d5a;
        color: #fff;
        font: inherit;
        font-size: 0.9rem;
    }

    .ao-rp-pill.ao-rp-exports:hover { background: #3f4d5a; }

    .ao-rp-pill.ao-rp-dead { opacity: 0.55; cursor: not-allowed; }

    /* ── System Settings cards ───────────────────────────────────────────────
       Issue #40, from the reference screenshots: each card is a tall tile — a large,
       pale icon centred in its own zone, a rule under it, then a bold NAVY title over a
       grey one-liner. Leandro's "everything is in shades of grey" was ours reading title
       and icon in the same tone; the reference's contrast lives in the navy title. */
    .ao-ss-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
        gap: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .ao-ss-card {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        text-decoration: none;
        color: inherit;
        overflow: hidden;
        transition: border-color 120ms, box-shadow 120ms;
    }

    .ao-ss-card:hover {
        border-color: var(--wa-link, #337ab7);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        text-decoration: none;
    }

    .ao-ss-card:hover .ao-ss-card-ic { color: var(--wa-link, #337ab7); }

    .ao-ss-card-ic {
        display: grid;
        place-items: center;
        padding: 1.4rem 0 1.2rem;
        border-bottom: 1px solid var(--wa-panel-border, #e5e5e5);
        color: #c8ccd0;
        transition: color 120ms;
    }

    .ao-ss-card-ic svg { width: 3.25rem; height: 3.25rem; }

    .ao-ss-card-body {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
        padding: 0.9rem 1.1rem 1.1rem;
    }

    .ao-ss-card-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: #14385e;
    }

    .ao-ss-card-desc {
        font-size: 0.9rem;
        color: var(--wa-muted, #6b6b6b);
        line-height: 1.4;
    }

    @media (max-width: 700px) {
        .ao-ss-grid { grid-template-columns: 1fr; }
    }

    /* ── System Settings frame (issue #40) ───────────────────────────────────
       The reference's landing frame around the cards: setup-tasks progress top right,
       the left rail (search, areas, Recently Visited), and the "All Settings" band. */
    .ao-ssx-hero {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 1rem;
    }

    .ao-ssx-tasks {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        column-gap: 0.6rem;
        min-width: 19rem;
        padding: 0.8rem 1rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-ssx-tasks-toggle {
        grid-column: 1 / -1;
        justify-self: start;
        margin-bottom: 0.45rem;
        font-weight: 700;
        color: var(--wa-ink, #2b2b2b);
        cursor: pointer;
    }

    .ao-ssx-tasks-toggle:hover { color: var(--wa-link, #337ab7); }

    .ao-ssx-progress {
        height: 0.9rem;
        border-radius: 999px;
        background: #eee;
        overflow: hidden;
    }

    .ao-ssx-progress-bar {
        display: block;
        height: 100%;
        background: #5cb85c;
    }

    .ao-ssx-progress-pct { font-size: 0.9rem; color: var(--wa-ink, #2b2b2b); }

    .ao-ssx-tasklist {
        grid-column: 1 / -1;
        margin-top: 0.7rem;
        display: grid;
        gap: 0.35rem;
        font-size: 0.9rem;
    }

    .ao-ssx-tasklist li { display: flex; align-items: center; gap: 0.45rem; }

    .ao-ssx-task-ic { width: 1.1rem; height: 1.1rem; flex: none; color: #b8b8b8; }

    .ao-ssx-task-done .ao-ssx-task-ic { color: #5cb85c; }

    .ao-ssx-task-done span { color: var(--wa-muted, #6b6b6b); }

    .ao-ssx-tasklist a { color: var(--wa-link, #337ab7); }

    .ao-ssx-tasklist a:hover { text-decoration: underline; }

    .ao-ssx-cols {
        display: grid;
        grid-template-columns: 15rem minmax(0, 1fr);
        gap: 1.5rem;
        align-items: start;
    }

    .ao-ssx-search {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0 0.7rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-ssx-search-ic { width: 1.1rem; height: 1.1rem; flex: none; color: var(--wa-muted, #6b6b6b); }

    .ao-ssx-search input {
        width: 100%;
        height: 2.4rem;
        border: 0;
        outline: none;
        background: transparent;
        font-size: 0.95rem;
    }

    /* The reference's area list: quiet rows, a green bar and tint on the active one. */
    .ao-ssx-areas {
        margin-top: 1rem;
        border-left: 3px solid var(--wa-rule, #e5e5e5);
    }

    .ao-ssx-areas button {
        display: block;
        width: 100%;
        padding: 0.55rem 0.9rem;
        margin-left: -3px;
        border-left: 3px solid transparent;
        text-align: left;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--wa-ink, #2b2b2b);
        cursor: pointer;
    }

    .ao-ssx-areas button:hover { background: #f5f5f5; }

    .ao-ssx-areas button.ao-on {
        border-left-color: #5cb85c;
        background: #eef4e9;
    }

    .ao-ssx-recent { margin-top: 1.6rem; }

    .ao-ssx-recent h4 {
        padding-bottom: 0.4rem;
        border-bottom: 1px solid var(--wa-rule, #e5e5e5);
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--wa-ink, #2b2b2b);
    }

    .ao-ssx-recent ol { margin-top: 0.5rem; display: grid; gap: 0.3rem; font-size: 0.9rem; }

    .ao-ssx-recent a { color: var(--wa-link, #337ab7); }

    .ao-ssx-recent a:hover { text-decoration: underline; }

    .ao-ssx-band {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding-bottom: 0.6rem;
        border-bottom: 2px solid var(--wa-rule, #e5e5e5);
        margin-bottom: 1rem;
    }

    .ao-ssx-band h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--wa-ink, #2b2b2b);
    }

    .ao-ssx-band select {
        height: 2.2rem;
        padding: 0 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: 4px;
        background: #fff;
        font-size: 0.9rem;
    }

    .ao-ssx-none { color: var(--wa-muted, #6b6b6b); }

    @media (max-width: 900px) {
        .ao-ssx-cols { grid-template-columns: 1fr; }
        .ao-ssx-hero { justify-content: stretch; }
        .ao-ssx-tasks { width: 100%; }
    }

    .ao-rv-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.8rem;
    }

    .ao-rv-head h3 { font-size: 1.15rem; font-weight: 600; margin-bottom: 0.3rem; }

    .ao-rv-note { margin-top: 0.4rem; }

    .ao-rv-tools summary { white-space: nowrap; }

    .ao-rv-chart { margin: 0.6rem 0 1.2rem; }

    .ao-rv-chart h4 { font-weight: 700; margin: 0 0 0.3rem 3.2rem; }

    .ao-rv-chart svg { width: 100%; max-width: 68rem; height: auto; }

    .ao-rv-table td:first-child { font-weight: 600; }

    /* The reference's date boxes: compact bordered fields sized to a date, calendar
       control inside — not a bare control stretched across the page. Leandro's circled
       screenshot, Network Issues' Start/End dates. */
    .ao-mu input[type="date"],
    .ao-mu input[type="datetime-local"] {
        width: auto;
        max-width: 15rem;
        height: 2.2rem;
        padding: 0 0.6rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.9rem;
        flex: 0 0 auto;
    }

    /* ── Colourful icons ────────────────────────────────────────────────────
       Leandro: the reference's icons are always colourful — they signal function at a
       glance. A cycling palette over every icon list, so neighbours never share a colour. */
    .ao-cp-body .ao-cp-link:nth-of-type(5n+1) .ao-cp-ic { color: #337ab7; }
    .ao-cp-body .ao-cp-link:nth-of-type(5n+2) .ao-cp-ic { color: #5cb85c; }
    .ao-cp-body .ao-cp-link:nth-of-type(5n+3) .ao-cp-ic { color: #f0ad4e; }
    .ao-cp-body .ao-cp-link:nth-of-type(5n+4) .ao-cp-ic { color: #9b59b6; }
    .ao-cp-body .ao-cp-link:nth-of-type(5n+5) .ao-cp-ic { color: #5bc0de; }

    .ao-cp-link.ao-cp-danger .ao-cp-ic,
    .ao-mo-delete .ao-mu-cell-icon,
    .ao-mu-icon-red { color: #d9534f; }

    .ao-rail-panel:nth-of-type(6n+1) .ao-rail-heading-icon { color: #337ab7; }
    .ao-rail-panel:nth-of-type(6n+2) .ao-rail-heading-icon { color: #5cb85c; }
    .ao-rail-panel:nth-of-type(6n+3) .ao-rail-heading-icon { color: #f0ad4e; }
    .ao-rail-panel:nth-of-type(6n+4) .ao-rail-heading-icon { color: #9b59b6; }
    .ao-rail-panel:nth-of-type(6n+5) .ao-rail-heading-icon { color: #5bc0de; }
    .ao-rail-panel:nth-of-type(6n+6) .ao-rail-heading-icon { color: #d9534f; }

    .ao-rail-list li:nth-child(5n+1) .ao-rail-link-icon { color: #337ab7; }
    .ao-rail-list li:nth-child(5n+2) .ao-rail-link-icon { color: #5cb85c; }
    .ao-rail-list li:nth-child(5n+3) .ao-rail-link-icon { color: #f0ad4e; }
    .ao-rail-list li:nth-child(5n+4) .ao-rail-link-icon { color: #9b59b6; }
    .ao-rail-list li:nth-child(5n+5) .ao-rail-link-icon { color: #d9534f; }

    /* Database Status / System pages. */
    .ao-db-line { margin: 0.5rem 0 0.9rem; }

    .ao-db-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
        align-items: start;
    }

    @media (max-width: 900px) { .ao-db-cols { grid-template-columns: 1fr; } }

    .ao-phi-facts { max-width: 46rem; }

    .ao-phi-ext { color: var(--wa-muted, #6b6b6b); line-height: 1.7; }

    .ao-phc-ok {
        padding: 0.9rem 1.1rem;
        border: 1px solid #d6e9c6;
        border-radius: var(--wa-radius, 6px);
        background: #dff0d8;
        color: #3c763d;
        margin-bottom: 1rem;
    }

    .ao-phc-bad { color: #d9534f; }

    /* ── Utilities: calendar, to-do, whois, resolver ───────────────────────── */
    .ao-cal-title { font-size: 1.15rem; }

    .ao-cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        overflow: hidden;
    }

    .ao-cal-head {
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        text-align: center;
        padding: 0.4rem 0.2rem;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .ao-cal-cell {
        min-height: 6rem;
        padding: 0.3rem 0.4rem;
        border-top: 1px solid var(--wa-rule, #e5e5e5);
        border-inline-start: 1px solid var(--wa-rule, #e5e5e5);
        font-size: 0.85rem;
    }

    .ao-cal-blank { background: #f7f7f7; }

    .ao-cal-today { background: #fdf7e3; }

    .ao-cal-cell > b { display: block; margin-bottom: 0.25rem; }

    .ao-cal-event {
        display: block;
        margin-bottom: 0.2rem;
        padding: 0.1rem 0.35rem;
        border-radius: 2px;
        font-size: 0.78rem;
        color: #fff;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ao-cal-service { background: #337ab7; }

    .ao-cal-invoice { background: #d9534f; }

    .ao-cal-legend { margin-top: 0.7rem; display: flex; gap: 0.6rem; }

    .ao-cal-legend .ao-cal-event { display: inline-block; }

    .ao-who-result {
        margin-top: 0.9rem;
        padding: 0.9rem 1.1rem;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fafafa;
        font-size: 0.875rem;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 60vh;
        overflow-y: auto;
    }

    .ao-td-done td { color: var(--wa-muted, #6b6b6b); text-decoration: line-through; }

    .ao-td-done td.ao-mu-check, .ao-td-done td.ao-mu-actions { text-decoration: none; }

    .ao-sa-hint {
        padding: 0.8rem 1rem;
        border: 1px solid #bce8f1;
        border-radius: var(--wa-radius, 6px);
        background: #d9edf7;
        color: #31708f;
        margin-bottom: 0.9rem;
    }

    /* Issue #4: the "+" opens the row in place — the reference's inline detail strip. */
    .ao-ps-plus {
        min-width: 1.8rem;
        padding: 0.15rem 0.45rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-weight: 700;
        color: var(--wa-link, #337ab7);
        cursor: pointer;
    }

    .ao-ps-plus:hover, .ao-ps-plus.ao-on { background: #f0f0f0; }

    .ao-ps-detail td {
        background: #f7fafd;
        text-align: start;
        padding: 0.9rem 1.1rem;
    }

    .ao-ps-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        gap: 1.2rem;
        align-items: start;
    }

    .ao-ps-detail-grid dt { font-weight: 700; margin-top: 0.35rem; }

    .ao-ps-detail-grid dd { margin: 0 0 0.2rem; }

    .ao-ps-detail-actions { display: flex; flex-direction: column; gap: 0.5rem; align-items: start; }

    .ao-ps-detail-actions .ao-find-go { text-decoration: none; display: inline-flex; align-items: center; }

    /* Issue #4: guarantee the detail-panel action buttons are white-on-blue. The generic
       `.ao-mu-grid a` link colour sits in the same cascade, and the client caught a build
       where it won — leaving a solid blue pill with invisible blue text. This rule
       (0,3,1) outranks it unconditionally, so the text can never vanish again. */
    .ao-mu-grid .ao-ps-detail-actions a.ao-find-go { color: #fff; }

    .ao-hl-pre {
        white-space: pre-wrap;
        word-break: break-all;
        font-size: 0.85rem;
        max-height: 24rem;
        overflow: auto;
        margin: 0.4rem 0;
        padding: 0.9rem 1rem;
        border: 1px solid var(--wa-border, #e2e2e2);
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-canvas, hsl(var(--color-gray-50)));
    }

    /* The two-column framed filter (Service Addons) and the campaigns screen. */
    .ao-stf-two {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 2.5rem;
    }

    @media (max-width: 1000px) { .ao-stf-two { grid-template-columns: 1fr; } }

    .ao-ec-banner {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        padding: 1rem 1.2rem;
        border: 1px solid #bce8f1;
        border-radius: var(--wa-radius, 6px);
        background: #d9edf7;
        color: #31708f;
        margin-bottom: 1rem;
    }

    .ao-ec-banner-ic {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.2rem;
        height: 2.2rem;
        border-radius: 50%;
        background: #31708f;
        color: #fff;
        font-weight: 700;
        flex: none;
    }

    .ao-ec-intro { margin-bottom: 1rem; }

    .ao-ec-radios { display: flex; gap: 1.4rem; flex-wrap: wrap; }

    .ao-ec-multi { align-items: center; }

    .ao-ec-multi select {
        width: 100%;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        font: inherit;
        font-size: 0.9rem;
        padding: 0.2rem;
    }

    .ao-ec-count { margin-inline-end: 1rem; font-weight: 600; }

    .ao-ec-foot { margin-top: 1rem; font-size: 0.9rem; color: var(--wa-muted, #6b6b6b); }

    /* Issue #37: the dashboard's WHMCS panels — the stat pairs, the automation grid,
       the ticket/client lists and their link rows. */
    .ao-wg-cols { display: flex; gap: 1.5rem; flex-wrap: wrap; }

    .ao-wg-wrap > * { flex: 1 1 40%; }

    .ao-wg-stat b { display: block; font-size: 1.6rem; font-weight: 600; }

    .ao-wg-stat span { color: var(--wa-muted, #6b6b6b); }

    .ao-wg-green { color: #5cb85c; }
    .ao-wg-blue { color: #5bc0de; }
    .ao-wg-orange { color: #f0ad4e; }
    .ao-wg-pink { color: #e64d8a; }
    .ao-wg-ink { color: var(--wa-text, #2b2b2b); }

    .ao-wg-empty { color: var(--wa-muted, #6b6b6b); }

    .ao-wg-auto {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.9rem 1.4rem;
    }

    .ao-wg-auto-stat { text-align: center; }

    .ao-wg-auto-stat svg { width: 5.5rem; height: 1.4rem; display: block; margin: 0 auto 0.15rem; }

    .ao-wg-auto-stat span { display: block; color: var(--wa-muted, #6b6b6b); font-size: 0.9rem; }

    .ao-wg-auto-stat b { font-size: 1.25rem; }

    .ao-wg-lastrun { margin-top: 0.8rem; color: var(--wa-muted, #6b6b6b); }

    .ao-wg-icostat { display: flex; align-items: center; gap: 0.7rem; }

    .ao-wg-icostat .ao-wg-ic { font-size: 1.8rem; }

    .ao-wg-icostat b { font-size: 1.2rem; }

    .ao-wg-tickets { list-style: none; margin: 0.9rem 0 0; padding: 0; }

    .ao-wg-tickets li {
        display: flex;
        align-items: baseline;
        gap: 0.6rem;
        padding: 0.35rem 0.2rem;
        border-top: 1px solid var(--wa-rule, #e5e5e5);
    }

    .ao-wg-tickets li a { flex: 1; min-width: 0; }

    .ao-wg-tickets li i { font-style: normal; color: var(--wa-link, #337ab7); font-size: 0.85rem; white-space: nowrap; }

    .ao-wg-ip { color: var(--wa-muted, #6b6b6b); font-size: 0.82rem; font-style: italic; }

    .ao-wg-links {
        display: flex;
        justify-content: space-between;
        gap: 0.8rem;
        margin-top: 0.9rem;
        padding-top: 0.6rem;
        border-top: 1px solid var(--wa-rule, #e5e5e5);
    }

    .ao-wg-links a { text-decoration: underline; }

    /* General Settings (issue #39): WHMCS's file-folder tab bar over a framed form of
       label-left rows, hint text inline after each field. */
    .ao-gs-tabs { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-bottom: -1px; position: relative; z-index: 1; }

    .ao-gs-tab {
        padding: 0.45rem 1rem;
        border: 1px solid var(--wa-panel-border, #ccc);
        border-radius: var(--wa-radius, 6px) var(--wa-radius, 6px) 0 0;
        background: #f0f0f0;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
    }

    .ao-gs-tab.ao-on { background: #fff; border-bottom-color: #fff; font-weight: 600; }

    .ao-gs-frame {
        border: 1px solid var(--wa-panel-border, #ccc);
        border-radius: 0 var(--wa-radius, 6px) var(--wa-radius, 6px) var(--wa-radius, 6px);
        background: #fff;
        padding: 1.2rem 1.4rem;
    }

    .ao-gs-row {
        display: grid;
        grid-template-columns: 16rem 1fr;
        gap: 1.2rem;
        align-items: start;
        padding: 0.45rem 0;
    }

    .ao-gs-row:nth-child(odd) { background: #f7f7f7; }

    .ao-gs-label { text-align: end; font-weight: 600; padding-top: 0.35rem; }

    .ao-gs-field { display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap; }

    .ao-gs-field input[type="text"],
    .ao-gs-field input[type="password"],
    .ao-gs-field input[type="number"],
    .ao-gs-field input[type="time"],
    .ao-gs-field select { min-width: 22rem; }

    .ao-gs-field textarea { min-width: 30rem; }

    .ao-gs-hint { color: var(--wa-muted, #6b6b6b); }

    .ao-gs-empty { margin: 0.4rem 0; }

    .ao-gs-actions { display: flex; justify-content: center; gap: 0.6rem; margin-top: 1rem; }

    .ao-gs-cancel {
        display: inline-flex;
        align-items: center;
        padding: 0.45rem 1.1rem;
        border: 1px solid var(--wa-panel-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-text, #2b2b2b);
        text-decoration: none;
    }

    @media (max-width: 900px) {
        .ao-gs-row { grid-template-columns: 1fr; gap: 0.25rem; }
        .ao-gs-label { text-align: start; }
        .ao-gs-field input, .ao-gs-field select, .ao-gs-field textarea { min-width: 0; width: 100%; }
    }

    /* Products/Services catalogue (issues #35, #41): the reference's one flat table —
       a single navy header, group bands with icons at the right, product rows sharing
       the same grid so every column lines up across groups. */
    .ao-ct { --ao-ct-cols: minmax(0, 2.4fr) 1.3fr 0.9fr 0.55fr 1fr 7rem; }

    .ao-ct-row {
        display: grid;
        grid-template-columns: var(--ao-ct-cols);
        gap: 0 0.8rem;
        align-items: center;
        padding: 0.45rem 0.9rem;
    }

    .ao-ct-row > span { text-align: center; }

    .ao-ct-row > .ao-ct-name { text-align: start; }

    .ao-ct-head {
        background: var(--wa-grid, #1a4d80);
        color: #fff;
        font-weight: 700;
        border-radius: var(--wa-radius, 6px) var(--wa-radius, 6px) 0 0;
    }

    /* One continuous table like the reference — the old per-group panel gap, border
       and radius are all flattened away inside .ao-ct. */
    .ao-ct .ao-cat-list { list-style: none; margin: 0; padding: 0; display: block; gap: 0; }

    .ao-ct .ao-cat { border: 0; border-radius: 0; background: transparent; }

    .ao-ct { border: 1px solid var(--wa-panel-border, #ddd); border-radius: var(--wa-radius, 6px); overflow: hidden; }

    .ao-ct .ao-catalogue-count { padding: 0.5rem 0.9rem; margin: 0; background: #fff; }

    .ao-ct-band {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0.9rem;
        background: #ececec;
        border: 1px solid var(--wa-panel-border, #ddd);
        border-inline-width: 0;
    }

    .ao-ct-band-name b { font-weight: 700; }

    .ao-ct-band .ao-ct-icons { margin-left: auto; }

    .ao-ct-icons {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
    }

    .ao-ct-icons button { background: none; border: 0; padding: 0; cursor: pointer; display: inline-flex; }

    .ao-ct-product { background: #fff; border-bottom: 1px solid #eee; }

    .ao-ct-product:nth-child(even) { background: #f7f9fc; }

    .ao-ct-product a { color: var(--wa-link, #337ab7); }

    .ao-ct .ao-cat-empty {
        margin: 0;
        padding: 0.5rem 0.9rem;
        background: #fff;
        border-bottom: 1px solid #eee;
        color: var(--wa-muted, #6b6b6b);
    }

    .ao-ct .ao-cat-children { padding-left: 1.4rem; }

    @media (max-width: 900px) {
        .ao-ct { --ao-ct-cols: minmax(0, 1fr) 6rem; }
        .ao-ct-row > span:nth-child(2), .ao-ct-row > span:nth-child(3),
        .ao-ct-row > span:nth-child(4), .ao-ct-row > span:nth-child(5) { display: none; }
    }

    /* Email Templates (issue #48): the reference's two-column category layout, each a
       navy mini-grid with the green/red status dot. */
    .ao-et-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0 2rem;
        align-items: start;
    }

    @media (max-width: 1100px) { .ao-et-cols { grid-template-columns: 1fr; } }

    .ao-et-status { width: 5.5rem; }

    .ao-et-icon { width: 3rem; }

    /* The reference's status marks are circled badges, not bare glyphs (issue #48). */
    .ao-et-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.35rem;
        height: 1.35rem;
        border-radius: 50%;
        font-size: 0.7rem;
        font-weight: 700;
        color: #fff;
    }

    .ao-et-dot.ao-on { background: #5cb85c; }

    .ao-et-dot.ao-off { background: #d9534f; }

    /* Service Addons (issue #7): the reference's +/− toggle and its unfolded band. */
    .ao-sa-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.4rem;
        height: 1.4rem;
        margin-inline-end: 0.25rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--wa-ink, #2b2b2b);
        cursor: pointer;
        vertical-align: middle;
    }

    .ao-sa-toggle:hover { color: var(--wa-link, #337ab7); }

    .ao-sa-detail td {
        background: #f7f7f7;
        text-align: left;
        padding: 0.6rem 0.9rem;
    }

    .ao-sa-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        gap: 0.4rem 1.5rem;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    /* API Credentials (issue #50): the reference's Generate button is green. */
    .ao-api-generate { background: #5cb85c; border-color: #4cae4c; color: #fff; }

    .ao-api-generate:hover { background: #449d44; color: #fff; }

    /* Affiliates detail (issue #6): the reference's framed two-column summary with
       label-left rows, editable commission fields inline. */
    .ao-af-frame {
        border: 1px solid var(--wa-panel-border, #ddd);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        padding: 1rem 1.2rem;
        margin-bottom: 0.9rem;
    }

    .ao-af-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 0 2.5rem; }

    .ao-af-row {
        display: grid;
        grid-template-columns: 11rem 1fr;
        gap: 1rem;
        align-items: center;
        padding: 0.4rem 0.6rem;
    }

    .ao-af-row:nth-child(odd) { background: #f7f7f7; }

    .ao-af-row > span:first-child { text-align: end; font-weight: 600; }

    .ao-af-field { display: flex; align-items: center; gap: 0.5rem; color: var(--wa-muted, #6b6b6b); }

    .ao-af-field input {
        width: 7rem;
        padding: 0.3rem 0.5rem;
        border: 1px solid var(--wa-panel-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
    }

    .ao-af-tabs { margin-top: 0.4rem; }

    @media (max-width: 1000px) { .ao-af-cols { grid-template-columns: 1fr; } }

    /* Automation Status (issue #33): the reference sets its month calendar beside the
       Daily Actions tiles, with "Today" floated at the band's right. */
    .ao-auto-daily-head { display: flex; align-items: baseline; justify-content: space-between; }

    .ao-auto-today-label { font-size: 1.4rem; color: #c9c9c9; }

    .ao-auto-daily { display: flex; gap: 1.2rem; align-items: flex-start; }

    .ao-auto-daily-tiles { flex: 1 1 auto; min-width: 0; }

    .ao-auto-cal {
        flex: 0 0 21rem;
        padding: 1rem;
        border: 1px solid var(--wa-panel-border, #e2e2e2);
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-canvas, #f7f7f7);
    }

    .ao-auto-cal-month {
        text-align: center;
        font-size: 1.1rem;
        padding: 0.5rem 0;
        background: #ececec;
        border-radius: var(--wa-radius, 6px);
        margin-bottom: 0.5rem;
    }

    .ao-auto-cal-grid { width: 100%; border-collapse: collapse; }

    .ao-auto-cal-grid th { padding: 0.35rem 0; font-weight: 700; }

    .ao-auto-cal-grid td { text-align: center; }

    .ao-auto-cal-grid td a {
        display: block;
        padding: 0.35rem 0;
        color: var(--wa-text, #2b2b2b);
        text-decoration: none;
        border-radius: 4px;
    }

    .ao-auto-cal-grid td a:hover { background: #e8e8e8; }

    .ao-auto-cal-other a, .ao-auto-cal-other { color: #b5b5b5 !important; }

    .ao-auto-cal-today a { background: #fcd487; font-weight: 700; }

    .ao-auto-cal-today-btn {
        display: block;
        text-align: center;
        margin-top: 0.5rem;
        padding: 0.5rem 0;
        background: #ececec;
        border-radius: var(--wa-radius, 6px);
        color: var(--wa-text, #2b2b2b);
        text-decoration: none;
    }

    .ao-auto-cal-today-btn:hover { background: #e0e0e0; text-decoration: none; }

    @media (max-width: 1100px) {
        .ao-auto-daily { flex-direction: column; }
        .ao-auto-cal { width: 100%; flex-basis: auto; }
    }

    /* Cancellation Requests (issue #30): the reference's segmented Open/Completed
       toggle — two joined buttons, the active one pressed darker. */
    .ao-sc-toggle { display: flex; margin-bottom: 0.7rem; }

    .ao-sc-toggle button {
        padding: 0.45rem 1.1rem;
        border: 1px solid var(--wa-panel-border, #ccc);
        background: #fff;
        color: var(--wa-text, #2b2b2b);
        cursor: pointer;
    }

    .ao-sc-toggle button:first-child { border-radius: var(--wa-radius, 6px) 0 0 var(--wa-radius, 6px); }

    .ao-sc-toggle button:last-child { border-radius: 0 var(--wa-radius, 6px) var(--wa-radius, 6px) 0; border-left: 0; }

    .ao-sc-toggle button.ao-on { background: #e8e8e8; font-weight: 600; }

    .ao-ni-options { margin-bottom: 0.6rem; }

    /* Issue #25: .ao-cp-link is a full-width flex block by default, which stacked the
       reference's one-line "Options: Open | Scheduled | Resolved | Create New" row into
       a column — and its width overflow was the page's stray horizontal scrollbar. */
    .ao-ni-options .ao-cp-link {
        display: inline-flex;
        width: auto;
        margin: 0 0.25rem;
    }

    .ao-ni-options .ao-ni-new { color: #5cb85c; font-weight: 600; align-items: center; gap: 0.25rem; }

    .ao-ni-new-ic { width: 1.05em; height: 1.05em; color: #5cb85c; }

    /* The reference's dialling-code picker sits flush against its number field. */
    .ao-find-phone { display: flex; }

    .ao-find-phone select {
        width: auto;
        flex: none;
        border-start-end-radius: 0;
        border-end-end-radius: 0;
        border-inline-end: 0;
        background: #f0f0f0;
        padding-inline: 0.4rem;
    }

    .ao-find-phone input {
        border-start-start-radius: 0;
        border-end-start-radius: 0;
    }

    /* The rail's Filter Tickets form. */
    /* Issue #25: the reference's Filter Tickets box is tighter than ours was — the
       extra height here was what pushed the rail past the viewport and put a page
       scrollbar on otherwise-short pages. */
    .ao-rail-filter {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        padding: 0.55rem 0.9rem;
    }

    .ao-rail-filter label {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .ao-rail-filter select,
    .ao-rail-filter input {
        height: 1.7rem;
        padding: 0 0.45rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        font: inherit;
        font-size: 0.875rem;
        font-weight: 400;
    }

    .ao-rail-filter button {
        margin-top: 0.1rem;
        height: 1.9rem;
        border: 0;
        border-radius: var(--wa-radius, 6px);
        background: #337ab7;
        color: #fff;
        font: inherit;
        font-size: 0.9375rem;
        cursor: pointer;
    }

    .ao-rail-filter button:hover { background: #286090; }

    /* Advanced Search (rail): the reference puts the term and its Search button — the
       plain grey one, not Filter Tickets' blue — on one row. */
    .ao-rail-search-row { display: flex; gap: 0.35rem; }

    .ao-rail-search-row input { flex: 1; min-width: 0; }

    .ao-rail-search-row button {
        margin-top: 0;
        height: 1.7rem;
        padding: 0 0.7rem;
        background: #fff;
        border: 1px solid var(--wa-border, #ccc);
        color: var(--wa-ink, #2b2b2b);
    }

    .ao-rail-search-row button:hover { background: #ededed; }

    /* An honestly-dead rail entry (Domain Registrations): sits in the list where the
       reference lists it, muted and un-underlined, its reason on the title. */
    .ao-rail-list .ao-rail-dead {
        display: flex;
        padding: 2px 0;
        color: var(--wa-muted, #6b6b6b);
        line-height: 1.5;
        cursor: help;
    }

    /* The small "Are you sure?" variant of the modal, as the reference confirms resets. */
    .ao-mud-sm { width: 37rem; }

    .ao-mud-text { padding: 1.1rem 1.5rem 0; }

    .ao-mud-text p + p { margin-top: 0.8rem; }

    /* Outside the padded form body the footer has nothing to un-indent from — the negative
       margins that align it inside .ao-mud-body would push it out of the dialog here. */
    .ao-mud > .ao-mud-foot { margin-inline: 0; }

    .ao-mud-foot-only-right { justify-content: flex-end; margin-top: 1.1rem; }

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
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-text, #2b2b2b);
        font-size: 0.9375rem;
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
        border-radius: var(--wa-radius, 6px);
        background: var(--wa-blue, #1a4d80);
        color: #fff;
        font-weight: 700;
    }

    /* ── Edit Order's whole-order buttons ────────────────────────────────────
       The reference's row under the order's line items — Accept green, Cancel and Set Back
       to Pending neutral, Delete red, the same palette its With Selected bar uses. */
    .ao-eo-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-top: 1.2rem;
    }

    .ao-eo-actions button {
        padding: 0.5rem 1.1rem;
        border: 1px solid var(--wa-border, #ccc);
        border-radius: var(--wa-radius, 6px);
        background: #fff;
        color: var(--wa-text, #2b2b2b);
        font-size: 0.9rem;
        cursor: pointer;
    }

    .ao-eo-accept {
        background: #5cb85c !important;
        border-color: #4cae4c !important;
        color: #fff !important;
    }

    .ao-eo-accept:hover { background: #449d44 !important; }

    .ao-eo-cancel:hover,
    .ao-eo-pending:hover { background: #f0f0f0; }

    .ao-eo-delete {
        background: #d9534f !important;
        border-color: #d43f3a !important;
        color: #fff !important;
    }

    .ao-eo-delete:hover { background: #c9302c !important; }
</style>
