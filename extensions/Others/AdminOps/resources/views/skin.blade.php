{{--
    WHMCS "Six" admin skin for the Filament panel.

    Plain CSS injected through the `panels::head.end` render hook, for the same reason as
    `styles.blade.php`: the admin theme (`resources/css/filament/admin/theme.css`) only scans
    `app/Admin` and `resources/views` for classes, `extensions/` is not a `@source`, and
    rebuilding it would need a Vite build on every deployment. This loads after that compiled
    stylesheet, so it wins on equal specificity and needs no build step — which matters,
    because the server deploy does not run npm.

    It restyles Filament's own markup rather than replacing it. That is the whole trade: the
    panel keeps every resource, policy, form and table it already has, and those internals
    stay Filament's HTML — they read as the reference design, they are not its markup.

    Class names below are Filament 5's (`fi-topbar`, `fi-section`, `fi-ta-*`, `fi-btn`).
    They are a public styling surface but not a stability guarantee: after an upstream
    Filament major, check this file. Nothing here can break the panel functionally — the
    worst case is that it stops applying and the stock Filament design shows through.
--}}
<style>
    /* WHMCS Six's palette, sampled pixel-by-pixel from the reference screenshots rather
       than eyeballed — every value below is the dominant colour of that region in
       `docs`-referenced shots, so the panel matches the reference exactly rather than
       approximately. Declared on .fi-body rather than :root so nothing here leaks into
       the client-area theme, which publishes its own custom properties into the same
       document on some pages. */
    .fi-body {
        --wa-blue: #1a4d80;          /* top menu bar, table headers, footer — all one blue */
        --wa-blue-dark: #0d2640;     /* hover / open menu item */
        --wa-ink: #333333;           /* page headings */
        --wa-text: #4a4a4a;
        --wa-muted: #888888;
        --wa-link: #337ab7;          /* rail links, primary button */
        --wa-border: #cccccc;        /* rail edge, dropdown edge */
        --wa-panel-border: #dddddd;
        --wa-rule: #d9dadb;          /* under the table header */
        --wa-section: #e9e9e9;       /* rail heading band */
        --wa-rail: #f6f6f6;          /* rail body */
        --wa-canvas: #ffffff;        /* the reference's content area is white, not grey */
        --wa-radius: 3px;

        /* Measured off the reference: the bar is 45px tall and the rail column is 195px
           wide plus its 1px rule. Kept as variables so the two places that need each
           number cannot drift apart. */
        --wa-topbar-h: 45px;
        --wa-rail-w: 195px;
    }

    /* ── Canvas ──────────────────────────────────────────────────────────────
       WHMCS sits on a flat grey canvas with white panels on it, rather than
       Filament's white page with subtle grey sections. */
    .fi-body {
        background: var(--wa-canvas);
        color: var(--wa-text);
        font-family: 'Open Sans', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        font-size: 13px;
    }

    /* ── Top menu bar ────────────────────────────────────────────────────────
       The blue band with the menu dropdowns, and the brand block at its start.

       The element is `<nav class="fi-topbar">`, not a nav inside a `.fi-topbar`.
       An earlier `.fi-topbar > nav.fi-topbar` matched nothing, so the bar stayed white
       while the rules below still turned its text white — an invisible menu. Both the
       Livewire wrapper and the nav are painted, so the band is full-bleed either way. */
    .fi-topbar-ctn,
    nav.fi-topbar {
        background: var(--wa-blue);
        border-bottom: 0;
        box-shadow: none;
    }

    nav.fi-topbar {
        min-height: var(--wa-topbar-h);
        height: var(--wa-topbar-h);
        padding-inline: 0;
        gap: 0;
    }

    nav.fi-topbar .fi-logo,
    nav.fi-topbar .fi-topbar-item-label,
    nav.fi-topbar .fi-icon-btn,
    nav.fi-topbar .fi-btn,
    nav.fi-topbar .fi-dropdown-trigger button,
    nav.fi-topbar .fi-topbar-item-icon {
        color: #ffffff;
    }

    /* The reference's menu labels are text only — the icons live in the left rail's section
       headings instead, which is where {@see Rail::section()} now puts them. The group icon
       is still set on the navigation group, because that is where the rail reads it from;
       only its appearance in the bar is dropped. */
    nav.fi-topbar .fi-topbar-item-icon {
        display: none;
    }

    /* Global search: a bare magnifier on the blue that grows into a field when you reach for
       it, which is how the reference's search behaves. Collapsing the input rather than
       replacing the component keeps core's resource-aware results and its ⌘K binding —
       the keystroke focuses the input, and focus is what opens it. */
    nav.fi-topbar .fi-global-search-field .fi-input-wrp {
        background: transparent;
        border: 1px solid transparent;
        border-radius: var(--wa-radius);
        box-shadow: none;
        transition: background-color 120ms ease, border-color 120ms ease;
    }

    nav.fi-topbar .fi-global-search-field .fi-input-wrp .fi-icon {
        color: #ffffff;
    }

    nav.fi-topbar .fi-global-search-field .fi-input {
        width: 0;
        min-width: 0;
        padding-inline: 0;
        transition: width 140ms ease, padding-inline 140ms ease;
    }

    nav.fi-topbar .fi-global-search-field:hover .fi-input-wrp,
    nav.fi-topbar .fi-global-search-field:focus-within .fi-input-wrp {
        background: #ffffff;
        border-color: var(--wa-blue-dark);
    }

    nav.fi-topbar .fi-global-search-field:hover .fi-input-wrp .fi-icon,
    nav.fi-topbar .fi-global-search-field:focus-within .fi-input-wrp .fi-icon {
        color: var(--wa-muted);
    }

    nav.fi-topbar .fi-global-search-field:hover .fi-input,
    nav.fi-topbar .fi-global-search-field:focus-within .fi-input {
        width: 14rem;
        padding-inline-end: 0.5rem;
    }

    /* ── Toolbar icons ───────────────────────────────────────────────────────
       The `+` at the start of the bar and the utility icons at its end, both
       injected by AdminOps through the topbar render hooks. Square, flat, and
       shading the whole cell on hover — the same treatment as a menu entry, so
       the two clusters read as part of the bar rather than as buttons on it. */
    .ao-tool {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        padding: 0.6rem 0.7rem;
        border: 0;
        background: transparent;
        color: #ffffff;
        cursor: pointer;
        line-height: 1;
    }

    .ao-tool:hover {
        background: var(--wa-blue-dark);
    }

    /* Selected through the button, not on its own: `generate_icon_html()` adds
       `fi-icon fi-size-md`, which sets a size at two-class specificity, so a lone
       `.ao-tool-icon` would lose to it however late this sheet loads. */
    .ao-tool .ao-tool-icon {
        width: 1.15rem;
        height: 1.15rem;
    }

    /* The reference tints two of them: its updates arrow is amber, and the `+` is the
       heaviest mark in the bar because it is the one thing there you press rather than
       browse. */
    .ao-tool.ao-tool-create .ao-tool-icon {
        width: 1.3rem;
        height: 1.3rem;
        stroke-width: 2.5;
    }

    .ao-tool.ao-tool-updates .ao-tool-icon {
        color: #f0ad4e;
    }

    /* The red marker on the reference's gear. Positioned on the icon rather than beside it
       so the bar's height never changes when a failure appears. */
    .ao-tool-badge {
        position: absolute;
        top: 0.3rem;
        inset-inline-end: 0.25rem;
        min-width: 1rem;
        padding: 0 0.2rem;
        border-radius: 999px;
        background: #d9534f;
        color: #ffffff;
        font-size: 9px;
        font-weight: 700;
        line-height: 1rem;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    /* Menu entries: white on blue, the whole cell shading on hover, as on the reference. */
    .fi-topbar-nav-groups {
        gap: 0;
    }

    /* Full-height cells, so hovering shades the bar from top to bottom as the reference
       does rather than leaving a pale margin above and below the label. */
    .fi-topbar-item-btn {
        color: #ffffff;
        border-radius: 0;
        height: var(--wa-topbar-h);
        padding: 0 0.9rem;
        font-size: 13px;
        font-weight: 400;
        line-height: var(--wa-topbar-h);
    }

    .fi-topbar-item-btn:hover,
    .fi-topbar-item.fi-active .fi-topbar-item-btn {
        background: var(--wa-blue-dark);
        color: #ffffff;
    }

    /* Filament marks the active group with an underline; WHMCS shades the cell instead. */
    .fi-topbar-item-btn::after {
        display: none;
    }

    /* The dropdown panels themselves: square, bordered, tight rows. */
    .fi-dropdown-panel {
        border-radius: var(--wa-radius);
        border: 1px solid #c3c3c3;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
        padding: 0.3rem 0;
    }

    .fi-dropdown-list-item {
        border-radius: 0;
        padding: 0.4rem 1.1rem;
        font-size: 13px;
        color: var(--wa-ink);
    }

    .fi-dropdown-list-item:hover {
        background: #f5f5f5;
        color: var(--wa-blue);
    }

    /* ── The left rail ───────────────────────────────────────────────────────
       Injected at `panels::layout.start`, so it is the first child of `.fi-layout`
       and becomes the page's left column. Filament's own sidebar is translated
       off-screen by `.fi-body-has-top-navigation`, so there is no second column. */
    /* 192px, measured off the reference rather than guessed — content begins at 208px,
       i.e. the column plus its 1px rule and the content's own padding. */
    .ao-rail {
        flex: none;
        width: var(--wa-rail-w);
        background: var(--wa-rail);
        border-inline-end: 1px solid var(--wa-border);
        display: flex;
        flex-direction: column;
        min-height: 100%;
        font-size: 12px;
    }

    .ao-rail-collapsed {
        width: 2rem;
    }

    .ao-rail-collapsed .ao-rail-inner {
        display: none;
    }

    .ao-rail-inner {
        flex: 1 1 auto;
        overflow-y: auto;
    }

    /* Grey band, 1px rules top and bottom, bold dark label — the reference's section head.
       Panels butt directly against each other with no gap, so the top rule is dropped on
       every panel after the first to avoid a 2px seam. */
    .ao-rail-heading {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin: 0;
        padding: 6px 10px;
        background: var(--wa-section);
        border-block: 1px solid var(--wa-border);
        font-size: 12px;
        font-weight: 700;
        color: var(--wa-ink);
        line-height: 1.35;
    }

    /* Grey, never coloured: on the reference these mark the section, they do not compete
       with the links underneath them. */
    .ao-rail-heading .ao-rail-heading-icon {
        width: 0.95rem;
        height: 0.95rem;
        flex: none;
        color: var(--wa-muted);
    }

    .ao-rail-panel + .ao-rail-panel .ao-rail-heading {
        border-top: 0;
    }

    .ao-rail-list,
    .ao-rail-staff {
        list-style: none;
        margin: 0;
        padding: 6px 10px;
    }

    /* Underlined blue links, tight leading, as on the reference. */
    .ao-rail-list a {
        display: block;
        padding: 2px 0;
        color: var(--wa-link);
        text-decoration: underline;
        line-height: 1.5;
    }

    .ao-rail-list a:hover {
        color: #23527c;
    }

    .ao-rail-list-counted a {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .ao-rail-count {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        text-decoration: none;
    }

    .ao-rail-count-zero {
        color: var(--wa-muted);
        font-weight: 400;
    }

    .ao-rail-facts {
        margin: 0;
        padding: 0.4rem 0.75rem;
        font-size: 12px;
    }

    .ao-rail-facts dt {
        color: var(--wa-muted);
        float: inline-start;
        clear: both;
        margin-inline-end: 0.35rem;
    }

    .ao-rail-facts dt::after {
        content: ':';
    }

    .ao-rail-facts dd {
        margin: 0;
        color: var(--wa-ink);
    }

    .ao-rail-staff li {
        padding: 0.15rem 0;
    }

    .ao-rail-staff-name {
        color: var(--wa-ink);
    }

    .ao-rail-staff-seen {
        color: var(--wa-muted);
        margin-inline-start: 0.3rem;
    }

    /* "Minimise Sidebar", pinned to the bottom of the column on a dark band. */
    .ao-rail-toggle {
        flex: none;
        width: 100%;
        padding: 5px 8px;
        border: 0;
        background: #666666;
        color: #ffffff;
        font-size: 11px;
        cursor: pointer;
        text-align: center;
        font-family: inherit;
    }

    .ao-rail-toggle:hover {
        background: #4d4d4d;
    }

    @media (max-width: 1024px) {
        /* Below Filament's `lg` breakpoint the rail would eat most of the width, and its
           contents are all reachable from the menu bar anyway. */
        .ao-rail {
            display: none;
        }
    }

    /* ── Page header ─────────────────────────────────────────────────────────
       WHMCS states the page name in a plain heading over a rule, with no
       breadcrumb trail above it. */
    .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 400;
        color: var(--wa-ink);
    }

    /* WHMCS runs its content to the full width of the window; Filament caps it at 7xl and
       centres it, which left a wide empty gutter beside the rail on a normal monitor. */
    .fi-main {
        max-width: none;
        padding-block: 1rem;
        padding-inline: 1rem;
    }

    .fi-main-ctn {
        background: var(--wa-canvas);
    }

    /* ── Panels ──────────────────────────────────────────────────────────────
       Filament's rounded, shadowless sections become WHMCS's bordered panels with
       a grey heading strip. */
    .fi-section,
    .fi-wi > .fi-sc,
    .fi-ta-ctn,
    .fi-fo-component-ctn > .fi-sc {
        border-radius: var(--wa-radius);
        border: 1px solid var(--wa-panel-border);
        background: #ffffff;
        box-shadow: none;
    }

    /* Panel headings are the lighter #f5f5f5, not the rail's #e9e9e9 — the reference uses
       two different greys and collapsing them flattens the page. */
    .fi-section-header {
        background: #f5f5f5;
        border-bottom: 1px solid var(--wa-panel-border);
        padding: 0.65rem 0.9rem;
    }

    .fi-section-header-heading {
        font-size: 14px;
        font-weight: 400;
        color: var(--wa-ink);
    }

    .fi-section-content {
        padding: 0.9rem;
    }

    /* ── Tables ──────────────────────────────────────────────────────────────
       The reference's solid blue header row with centred white labels, hairline-separated
       cells, and plain white rows underneath. */
    /* 30px tall and closed by a #d9dadb rule — both measured off the reference. */
    .fi-ta-header-cell {
        background: var(--wa-blue);
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        text-transform: none;
        letter-spacing: 0;
        text-align: center;
        height: 30px;
        padding-block: 0;
        border-inline-start: 1px solid rgba(255, 255, 255, 0.18);
        border-bottom: 1px solid var(--wa-rule);
    }

    .fi-ta-header-cell:first-child {
        border-inline-start: 0;
    }

    .fi-ta-header-cell button,
    .fi-ta-header-cell .fi-ta-header-cell-label {
        color: #ffffff;
        justify-content: center;
        width: 100%;
    }

    /* The reference's rows are plain white with a hairline under each — no zebra. Its
       weight comes from the header, and striping on top of that reads as two competing
       grids. Hover still shades, because a wide row needs somewhere to put your eye. */
    .fi-ta-row {
        background: #ffffff;
        border-bottom: 1px solid #eeeeee;
    }

    .fi-ta-row:hover {
        background: #f5f9fd;
    }

    .fi-ta-cell {
        font-size: 12.5px;
        padding-block: 0.5rem;
    }

    .fi-ta-text-item-label {
        color: var(--wa-text);
    }

    /* The toolbar above a list — search box, filters, per-page — as the reference's grey
       strip rather than Filament's white one. */
    .fi-ta-header-ctn,
    .fi-ta-header-toolbar {
        background: #f5f5f5;
        border-bottom: 1px solid var(--wa-panel-border);
    }

    .fi-ta-header-toolbar {
        padding: 0.55rem 0.75rem;
        gap: 0.5rem;
    }

    /* Empty lists: the reference states the fact in one grey line, centred, with no
       illustration above it. */
    .fi-ta-empty-state-icon-bg {
        display: none;
    }

    .fi-ta-empty-state-heading {
        font-size: 13px;
        font-weight: 400;
        color: var(--wa-muted);
    }

    .fi-ta-empty-state-description {
        font-size: 12px;
        color: var(--wa-muted);
    }

    /* ── Pagination ──────────────────────────────────────────────────────────
       Bordered square boxes, the current page filled in WHMCS blue — the reference's
       « Previous Page · 1 · Next Page » strip. */
    .fi-pagination {
        padding-block: 0.6rem;
        font-size: 12.5px;
    }

    .fi-pagination-item-btn,
    .fi-pagination-previous-btn,
    .fi-pagination-next-btn {
        border-radius: var(--wa-radius);
        border: 1px solid var(--wa-border);
        background: #ffffff;
        color: #337ab7;
        box-shadow: none;
        font-weight: 400;
    }

    .fi-pagination-item-btn:hover,
    .fi-pagination-previous-btn:hover,
    .fi-pagination-next-btn:hover {
        background: var(--wa-section);
        color: #23527c;
    }

    /* Filament marks the current page with its primary colour; the reference fills the
       box. `aria-current` is the only reliable handle — the active item carries no class
       of its own. */
    .fi-pagination-item-btn[aria-current='page'] {
        background: var(--wa-blue);
        border-color: var(--wa-blue-dark);
        color: #ffffff;
    }

    .fi-pagination-overview,
    .fi-pagination-records-per-page-select-ctn {
        color: var(--wa-text);
        font-size: 12.5px;
    }

    /* ── Forms ───────────────────────────────────────────────────────────────
       The reference labels a field to the left of it in plain dark text, on a panel with
       no rounding. Filament's own layout is kept — only the type and the chrome change,
       because moving every field to a two-column grid would fight the responsive rules
       each resource form already declares. */
    .fi-fo-field-label,
    .fi-fo-field-label-content {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--wa-ink);
    }

    .fi-fo-field-label-required-mark {
        color: #d9534f;
    }

    .fi-input,
    .fi-select-input {
        font-size: 12.5px;
    }

    /* ── Stat tiles ──────────────────────────────────────────────────────────
       The row of figures the reference puts above a report — a pale card, small grey
       label, large navy number. */
    .fi-wi-stats-overview-stat {
        border-radius: var(--wa-radius);
        border: 1px solid var(--wa-panel-border);
        background: #fffdf5;
        box-shadow: none;
        text-align: center;
        padding: 0.9rem 0.75rem;
    }

    .fi-wi-stats-overview-stat-label {
        font-size: 12.5px;
        font-weight: 400;
        color: var(--wa-text);
    }

    .fi-wi-stats-overview-stat-value {
        font-size: 1.75rem;
        font-weight: 400;
        color: var(--wa-blue-deep);
    }

    /* ── Buttons ─────────────────────────────────────────────────────────────
       Square, small, WHMCS blue for the primary action. */
    .fi-btn {
        border-radius: var(--wa-radius);
        font-size: 12.5px;
        font-weight: 400;
        box-shadow: none;
    }

    /* The reference's primary button is Bootstrap's #337ab7, a lighter blue than the bar —
       sampled off its Search button. Using the bar's blue made every button look like a
       piece of chrome. */
    .fi-btn.fi-color-primary {
        background: var(--wa-link);
        border: 1px solid #2e6da4;
        color: #ffffff;
    }

    .fi-btn.fi-color-primary:hover {
        background: #286090;
        border-color: #204d74;
    }

    /* ── Inputs ──────────────────────────────────────────────────────────── */
    .fi-input-wrp,
    .fi-select-input-wrp {
        border-radius: var(--wa-radius);
        box-shadow: none;
        border: 1px solid #cccccc;
    }

    /* ── Headline tiles ──────────────────────────────────────────────────────
       Squarer and flatter here than the rounded cards the default panel gets, to
       match the reference's four blocks. */
    .fi-body .ao-tile {
        border-radius: var(--wa-radius);
    }

    .fi-body .ao-tile-success { background-color: #5cb85c; }
    .fi-body .ao-tile-warning { background-color: #f0ad4e; }
    .fi-body .ao-tile-info    { background-color: #5bc0de; }
    .fi-body .ao-tile-brand   { background-color: #d9534f; }

    /* ── Footer ──────────────────────────────────────────────────────────────
       Copyright at the start, links at the end — the reference's split bar. It wraps
       rather than shrinks on a narrow window, so neither half is ever truncated. */
    .ao-admin-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem 1rem;
        padding: 0.6rem 1rem;
        border-top: 1px solid var(--wa-border);
        background: var(--wa-blue);
        color: #ffffff;
        font-size: 11.5px;
    }

    .ao-admin-footer-links {
        display: flex;
        gap: 0.9rem;
    }

    .ao-admin-footer a {
        color: #ffffff;
        text-decoration: none;
    }

    .ao-admin-footer a:hover {
        text-decoration: underline;
    }

    /* Dark mode is deliberately left alone rather than forced off. The reference has no
       dark palette to copy, but the rules above set explicit colours on the chrome, so a
       staff member who prefers dark keeps a readable panel — the WHMCS blue simply sits on
       Filament's dark surfaces instead of its light ones. Forcing light would override a
       preference the panel offers, to match a reference that never had the choice. */
</style>
