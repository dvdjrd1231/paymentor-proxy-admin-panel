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
    /* WHMCS Six's palette, taken from its own stylesheet. Declared on .fi-body rather
       than :root so nothing here leaks into the client-area theme, which publishes its
       own custom properties into the same document on some pages. */
    .fi-body {
        --wa-blue: #22558c;          /* top menu bar */
        --wa-blue-dark: #1b4470;     /* hover / active */
        --wa-blue-deep: #163a60;     /* the darker header band */
        --wa-ink: #333333;
        --wa-text: #4a4a4a;
        --wa-muted: #888888;
        --wa-border: #dddddd;
        --wa-panel-border: #e3e3e3;
        --wa-section: #f5f5f5;
        --wa-canvas: #f0f0f0;
        --wa-radius: 3px;
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
       The blue band with the menu dropdowns, and the brand block at its start. */
    .fi-topbar > nav.fi-topbar {
        background: var(--wa-blue);
        border-bottom: 0;
        box-shadow: none;
        min-height: 46px;
        padding-inline: 0.75rem;
    }

    .fi-topbar .fi-logo,
    .fi-topbar .fi-topbar-item-label,
    .fi-topbar .fi-icon-btn,
    .fi-topbar .fi-dropdown-trigger button {
        color: #ffffff;
    }

    /* Menu entries: white on blue, the whole cell shading on hover, as on the reference. */
    .fi-topbar-nav-groups {
        gap: 0;
    }

    .fi-topbar-item-btn {
        color: #ffffff;
        border-radius: 0;
        padding: 0.85rem 0.9rem;
        font-size: 13px;
        font-weight: 400;
        line-height: 1;
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
        border: 1px solid var(--wa-border);
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
        background: var(--wa-section);
        color: var(--wa-blue);
    }

    /* ── The left rail ───────────────────────────────────────────────────────
       Injected at `panels::layout.start`, so it is the first child of `.fi-layout`
       and becomes the page's left column. Filament's own sidebar is translated
       off-screen by `.fi-body-has-top-navigation`, so there is no second column. */
    .ao-rail {
        flex: none;
        width: 13rem;
        background: #ffffff;
        border-inline-end: 1px solid var(--wa-border);
        display: flex;
        flex-direction: column;
        min-height: 100%;
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

    .ao-rail-heading {
        margin: 0;
        padding: 0.5rem 0.75rem;
        background: var(--wa-section);
        border-block: 1px solid var(--wa-border);
        font-size: 12px;
        font-weight: 700;
        color: var(--wa-ink);
    }

    .ao-rail-panel + .ao-rail-panel .ao-rail-heading {
        border-top: 0;
    }

    .ao-rail-list,
    .ao-rail-staff {
        list-style: none;
        margin: 0;
        padding: 0.4rem 0.75rem;
        font-size: 12px;
    }

    .ao-rail-list a {
        display: block;
        padding: 0.15rem 0;
        color: var(--wa-blue);
        text-decoration: underline;
    }

    .ao-rail-list a:hover {
        color: var(--wa-blue-dark);
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

    /* WHMCS's "Minimise Sidebar" bar, pinned to the bottom of the column. */
    .ao-rail-toggle {
        flex: none;
        width: 100%;
        padding: 0.4rem;
        border: 0;
        border-top: 1px solid var(--wa-border);
        background: #555555;
        color: #ffffff;
        font-size: 11px;
        cursor: pointer;
        text-align: center;
    }

    .ao-rail-toggle:hover {
        background: var(--wa-ink);
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

    .fi-main {
        padding-block: 1rem;
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

    .fi-section-header {
        background: var(--wa-section);
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
       The reference's dark header row with white labels, and tight zebra rows. */
    .fi-ta-header-cell {
        background: var(--wa-blue);
        color: #ffffff;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding-block: 0.55rem;
    }

    .fi-ta-header-cell button,
    .fi-ta-header-cell .fi-ta-header-cell-label {
        color: #ffffff;
    }

    .fi-ta-row:nth-child(even) {
        background: #fafafa;
    }

    .fi-ta-row:hover {
        background: #eef4fa;
    }

    .fi-ta-cell {
        font-size: 12.5px;
        padding-block: 0.45rem;
    }

    .fi-ta-text-item-label {
        color: var(--wa-text);
    }

    /* ── Buttons ─────────────────────────────────────────────────────────────
       Square, small, WHMCS blue for the primary action. */
    .fi-btn {
        border-radius: var(--wa-radius);
        font-size: 12.5px;
        font-weight: 400;
        box-shadow: none;
    }

    .fi-btn.fi-color-primary {
        background: var(--wa-blue);
        border: 1px solid var(--wa-blue-dark);
        color: #ffffff;
    }

    .fi-btn.fi-color-primary:hover {
        background: var(--wa-blue-dark);
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

    /* ── Footer ──────────────────────────────────────────────────────────── */
    .ao-admin-footer {
        padding: 0.6rem 1rem;
        border-top: 1px solid var(--wa-border);
        background: var(--wa-blue-deep);
        color: #ffffff;
        font-size: 11.5px;
        text-align: center;
    }

    /* Dark mode is deliberately left alone rather than forced off. The reference has no
       dark palette to copy, but the rules above set explicit colours on the chrome, so a
       staff member who prefers dark keeps a readable panel — the WHMCS blue simply sits on
       Filament's dark surfaces instead of its light ones. Forcing light would override a
       preference the panel offers, to match a reference that never had the choice. */
</style>
