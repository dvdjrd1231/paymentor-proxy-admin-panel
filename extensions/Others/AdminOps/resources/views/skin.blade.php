{{--
    WHMCS "Six" admin skin for the Filament panel.

    Plain CSS at `panels::head.end`, not a theme rebuild: the admin theme does not scan
    `extensions/`, and a rebuild would put a Vite build in the deploy path (the server has no
    npm). Loading after the compiled stylesheet wins on equal specificity.

    Restyles Filament's markup rather than replacing it, so the panel keeps every resource,
    form and table it has. Selectors are Filament 5's — a public surface, but not a stability
    guarantee: re-check after an upstream major. Worst case it stops applying and stock
    Filament shows through; nothing here can break the panel functionally.
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
        --wa-text: #333333;          /* was #4a4a4a — Leandro: reads as grey, wants nearer black */
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
        --wa-footer-h: 34px;

        /* The account menu's person glyph, inline so it needs no request and cannot 404 —
           see the note at `.fi-user-menu-trigger`. Black in the data URI because it is used
           as a mask: only the shape matters, the colour comes from `background-color`. */
        --ao-user-mark: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'/%3E%3Cpath d='M4.5 20a7.5 7.5 0 0 1 15 0'/%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3C/svg%3E");
    }

    /* ── Canvas ──────────────────────────────────────────────────────────────
       WHMCS sits on a flat grey canvas with white panels on it, rather than
       Filament's white page with subtle grey sections. */
    .fi-body {
        background: var(--wa-canvas);
        color: var(--wa-text);
        font-family: 'Open Sans', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        font-size: 15px;
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
        /* The bar is one line high by design, so nothing in it may wrap onto a second one —
           that line would be clipped rather than shown. See `.fi-topbar-nav-groups`. */
        flex-wrap: nowrap;
    }

    /* The brand block is fixed furniture; only the menus should give up space. */
    nav.fi-topbar .fi-topbar-start {
        flex: none;
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

    /* Global search: a bare magnifier that grows into a field on reach, as the reference
       does. Collapsing the input rather than replacing the component keeps core's results and
       its ⌘K binding.

       The open field is an **overlay**, not part of the row: in flow its 14rem squeezed the
       menus until Filament wrapped the last one onto a clipped second line, so opening the
       search made *Addons* disappear. */
    nav.fi-topbar .fi-global-search-ctn {
        position: relative;
        flex: none;
    }

    nav.fi-topbar .fi-global-search {
        flex: none;
    }

    /* A fixed cell for the collapsed magnifier, so the bar reserves the same space whether
       the field is open or shut. Without it the cell would be 0 wide once its only child
       goes absolute, and the icon would have nothing to sit in. */
    nav.fi-topbar .fi-global-search-field {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: var(--wa-topbar-h);
    }

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

    /* Open: lifted out of the row and hung off the right-hand end of the search cell, so it
       grows leftwards over the menus for as long as the pointer or the caret is in it, and
       the row underneath never moves. */
    nav.fi-topbar .fi-global-search-field:hover .fi-input-wrp,
    nav.fi-topbar .fi-global-search-field:focus-within .fi-input-wrp {
        position: absolute;
        top: 50%;
        inset-inline-end: 0;
        transform: translateY(-50%);
        width: 16rem;
        z-index: 30;
        background: #ffffff;
        border-color: var(--wa-blue-dark);
    }

    /* The results panel is positioned against `.fi-global-search`, and core stretches it to
       `inset-inline: 1rem` — the full width of a search that is `flex: 1`. This one is a
       2.5rem cell, so that would render the results in a column two characters wide. */
    nav.fi-topbar .fi-global-search-results-ctn {
        inset-inline: auto;
        inset-inline-end: 0;
        width: 20rem;
        max-width: calc(100vw - 1rem);
    }

    nav.fi-topbar .fi-global-search-field:hover .fi-input-wrp .fi-icon,
    nav.fi-topbar .fi-global-search-field:focus-within .fi-input-wrp .fi-icon {
        color: var(--wa-muted);
    }

    nav.fi-topbar .fi-global-search-field:hover .fi-input,
    nav.fi-topbar .fi-global-search-field:focus-within .fi-input {
        width: 100%;
        padding-inline-end: 0.5rem;
    }

    /* ── Toolbar icons ───────────────────────────────────────────────────────
       The `+` at the start of the bar and the utility icons at its end, both
       injected by AdminOps through the topbar render hooks. Square, flat, and
       shading the whole cell on hover — the same treatment as a menu entry, so
       the two clusters read as part of the bar rather than as buttons on it. */
    /* Filament spaces `.fi-topbar-end` out by 1rem, which reads as five separate buttons
       floating on the bar. On the reference they are one contiguous strip of cells, so the
       gap is closed here and the cells carry their own padding instead. */
    .fi-topbar-end {
        column-gap: 0;
    }

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
        /* Two of these are `<a>` now, not `<button>` — the reference's cogs and updater go
           straight somewhere rather than opening a menu — so the anchor defaults have to be
           undone or they arrive underlined and in the link colour. */
        text-decoration: none;
    }

    .ao-tool:hover {
        background: var(--wa-blue-dark);
        color: #ffffff;
    }

    /* The reference closes its bar with the question mark, and puts the account menu in front
       of it. Filament renders its user menu last, after every render hook in `.fi-topbar-end`,
       so there is no hook between them to render into — but the row is a flexbox, so ordering
       the help cluster after everything else puts the two in the reference's order without
       replacing the account menu with a copy of our own. */
    .ao-tool-wrap-help {
        order: 1;
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

    /* ── The account menu ────────────────────────
       Where Sign out lives, and why it looked missing: the trigger is nothing but an avatar
       `<img>`, and Paymenter's provider returns a Gravatar URL. Unreachable host or no
       Gravatar and the button collapses to an invisible control on a blue bar.

       So the remote image is dropped for an inline `mask-image` glyph — no request, cannot
       404. Filament's menu and its working sign-out are untouched. */
    nav.fi-topbar .fi-user-menu-trigger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: var(--wa-topbar-h);
        padding: 0 0.75rem;
        cursor: pointer;
    }

    nav.fi-topbar .fi-user-menu-trigger:hover {
        background: var(--wa-blue-dark);
    }

    nav.fi-topbar .fi-user-menu-trigger .fi-avatar {
        display: none;
    }

    nav.fi-topbar .fi-user-menu-trigger::after {
        content: '';
        display: block;
        width: 1.35rem;
        height: 1.35rem;
        background-color: #ffffff;
        -webkit-mask: var(--ao-user-mark) center / contain no-repeat;
        mask: var(--ao-user-mark) center / contain no-repeat;
    }

    /* Menu entries: white on blue, the whole cell shading on hover.

       `flex-wrap: nowrap` overrides Filament, which wraps these onto extra lines — right for
       a bar that grows, wrong for one pinned to 45px, where the second line is simply clipped
       and a menu vanishes silently. When they truly will not fit the row scrolls sideways,
       which is safe only because Filament teleports these panels to `<body>`. The scrollbar
       is hidden; wheel, trackpad and keyboard still scroll. */
    .fi-topbar-nav-groups {
        gap: 0;
        flex-wrap: nowrap;
        min-width: 0;
        margin-inline: 0;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .fi-topbar-nav-groups::-webkit-scrollbar {
        display: none;
    }

    .fi-topbar-item {
        flex: none;
    }

    /* Fixed furniture at the end of the bar: it must not be squeezed by the menus, nor grow
       and squeeze them. `.fi-global-search` is `flex: 1` in core, which is what let the open
       search take the menus' width in the first place. */
    .fi-topbar-end {
        flex: none;
    }

    /* Give up ornament before content as the window narrows. Measured: brand 152px + menus
       721px + icons 249px = 1122px, and Filament hides these menus below 1024px — so the gap
       to close is ~130px. Tighter cells at 1400px, then at 1200px the chevrons go (~20px x 7,
       decoration) and the icons tighten. About 200px, enough to reach the hamburger. */
    @media (max-width: 1400px) {
        .fi-topbar-item-btn {
            padding-inline: 0.6rem;
        }
    }

    @media (max-width: 1200px) {
        .fi-topbar-item-btn {
            padding-inline: 0.45rem;
            column-gap: 0;
        }

        .fi-topbar-group-toggle-icon {
            display: none;
        }

        .ao-tool,
        nav.fi-topbar .fi-user-menu-trigger {
            padding-inline: 0.45rem;
        }
    }

    /* Full-height cells, so hovering shades the bar from top to bottom as the reference
       does rather than leaving a pale margin above and below the label. */
    .fi-topbar-item-btn {
        color: #ffffff;
        border-radius: 0;
        height: var(--wa-topbar-h);
        padding: 0 0.9rem;
        font-size: 15px;
        font-weight: 400;
        line-height: var(--wa-topbar-h);
    }

    /* Hover only — deliberately not `.fi-active`. Leandro: the menu being *on* the page it
       links to should not keep its cell shaded; the reference highlights on hover and on
       press, never at rest. The explicit reset below is load-bearing: dropping our
       `.fi-active` rule stops *us* shading it, but Filament's own stylesheet still paints
       the active item, so at rest it must be told to paint nothing. */
    .fi-topbar-item.fi-active .fi-topbar-item-btn {
        background: transparent;
    }

    .fi-topbar-item-btn:hover,
    .fi-topbar-item.fi-active .fi-topbar-item-btn:hover {
        background: var(--wa-blue-dark);
        color: #ffffff;
    }

    /* Filament marks the active group with an underline; WHMCS shades the cell instead. */
    .fi-topbar-item-btn::after {
        display: none;
    }

    /* Degraded-JS guard. If `support.js` does not execute, `filamentDropdown` is never
       registered; Alpine still boots and strips `x-cloak`, then fails to position anything —
       so every dropdown in the bar renders open at once and the page looks destroyed.

       `x-float` always writes an inline `display` (none when closed, block when open), so its
       absence means nothing has taken charge of the panel, and hidden is the right default.
       Costs nothing when the script loads, and degrades to "menus will not open". */
    .fi-dropdown-panel:not([style*='display']) {
        display: none;
    }

    /* The dropdown panels themselves: square, bordered, tight rows.

       The negative margin closes the strip Leandro circled between the bar and the open
       menu: x-float positions the panel with an inline transform this stylesheet cannot
       reach, so the margin pulls the box back up against the bar the way the reference's
       menus sit. Topbar only — a form's select dropdown keeps its breathing room. */
    nav.fi-topbar .fi-dropdown-panel {
        margin-top: -7px;
    }

    .fi-dropdown-panel {
        border-radius: var(--wa-radius);
        border: 1px solid #c3c3c3;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
        padding: 0.3rem 0;
    }

    .fi-dropdown-list-item {
        border-radius: 0;
        padding: 0.4rem 1.1rem;
        font-size: 15px;
        color: var(--wa-ink);
    }

    .fi-dropdown-list-item:hover {
        background: #f5f5f5;
        color: var(--wa-blue);
    }

    /* The reference's flyout: the category entries open beside their parent on hover. The
       wrapper is the hover surface, so the pointer can travel from the parent row into the
       side panel without the panel closing under it. */
    .ao-flyout {
        position: relative;
    }

    .ao-flyout .ao-has-sub {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .ao-flyout .ao-has-sub::after {
        content: '\25B8';
        margin-inline-start: 1.2rem;
        color: var(--wa-muted, #999);
        font-size: 0.75rem;
    }

    .ao-flyout-panel {
        display: none;
        position: absolute;
        left: 100%;
        top: -0.35rem;
        z-index: 40;
        min-width: 13rem;
        padding: 0.3rem 0;
        background: #fff;
        border: 1px solid #c3c3c3;
        border-radius: var(--wa-radius);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.175);
    }

    .ao-flyout:hover > .ao-flyout-panel {
        display: block;
    }

    /* The reference rules off the last entry of its help menu — the one that answers "what
       is this installation", rather than "where do I get help". */
    .fi-dropdown-list-item.ao-drop-item-separated {
        margin-top: 0.3rem;
        border-top: 1px solid #e5e5e5;
        padding-top: 0.55rem;
    }

    /* ── The wrench menu ─────────────────────────────────────────────────────
       The reference's `ul.drop-icons`: not a list of links but a grid of tiles, each an icon
       over its label, three to a row. Three because that is the reference's own count, and
       because at two the panel is a column and at four the labels start wrapping. */
    /* Three equal columns of whatever the panel is, rather than three fixed ones: the panel
       is `width: 100vw` capped by its width class, so on a phone it is the screen and the
       tiles have to come with it. Fixed columns overflowed it instead. */
    .ao-drop-icons {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        width: 100%;
        gap: 1px;
        padding: 0.35rem;
    }

    @media (max-width: 26rem) {
        .ao-drop-icons {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .ao-drop-icons .ao-drop-icon {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        gap: 0.4rem;
        padding: 0.7rem 0.4rem;
        border-radius: var(--wa-radius);
        color: var(--wa-ink);
        font-size: 12px;
        line-height: 1.3;
        text-align: center;
        text-decoration: none;
    }

    .ao-drop-icons .ao-drop-icon:hover {
        background: #f5f5f5;
        color: var(--wa-blue);
    }

    /* Muted, and staying muted on hover: on the reference these mark the tile, they are not
       the thing you are reading. Sized through the anchor for the reason given at
       `.ao-tool .ao-tool-icon` — `generate_icon_html()` sets a size at two-class
       specificity, so a lone class here would lose to it. */
    .ao-drop-icons .ao-drop-icon .ao-drop-icon-mark .fi-icon {
        width: 1.4rem;
        height: 1.4rem;
        color: var(--wa-link);
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
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 2px 0;
        color: var(--wa-link);
        text-decoration: underline;
        line-height: 1.5;
    }

    /* The shortcut icons, muted so the blue labels stay the thing being read. Sized to the
       text, not the row, exactly as the reference's little glyph column is. */
    .ao-rail-link-icon {
        width: 0.85rem;
        height: 0.85rem;
        flex: none;
        color: var(--wa-muted, hsl(var(--color-base) / 0.55));
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
        font-size: 15px;
        font-weight: 400;
        color: var(--wa-ink);
    }

    .fi-section-content {
        padding: 0.9rem;
    }

    /* ── Forms ───────────────────────────────────────────────────────────────
       The same 14px reading size as everything else — Filament's inputs and labels were
       the last place still under it, which is where "sometimes the fonts feel small"
       was coming from: the edit screens. */
    .fi-input,
    .fi-select-input,
    .fi-fo-field-label,
    .fi-fo-field-label-content,
    .fi-checkbox-label,
    .fi-radio-label {
        font-size: 15px;
    }

    /* ── Tables ──────────────────────────────────────────────────────────────
       The reference's solid blue header row with centred white labels, hairline-separated
       cells, and plain white rows underneath. */
    /* 30px tall and closed by a #d9dadb rule — both measured off the reference.

       `thead th` as well as the named class: the actions column's header cell does not
       carry `.fi-ta-header-cell`, so it alone stayed white at the end of every navy row —
       the odd-one-out Leandro circled on Manage User Services. Painting every th in the
       head means no column can opt out, whatever class Filament gives it. */
    .fi-ta table > thead th,
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
        font-size: 15px;
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
        font-size: 15px;
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
        font-size: 15px;
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
        font-size: 15px;
    }

    /* ── Forms ───────────────────────────────────────────────────────────────
       The reference labels a field to the left of it in plain dark text, on a panel with
       no rounding. Filament's own layout is kept — only the type and the chrome change,
       because moving every field to a two-column grid would fight the responsive rules
       each resource form already declares. */
    .fi-fo-field-label,
    .fi-fo-field-label-content {
        font-size: 15px;
        font-weight: 600;
        color: var(--wa-ink);
    }

    .fi-fo-field-label-required-mark {
        color: #d9534f;
    }

    .fi-input,
    .fi-select-input {
        font-size: 15px;
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
        font-size: 15px;
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
        font-size: 15px;
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
       The reference's four blocks are two-tone: the icon sits in a darker square at the
       start of the tile, the figure on the lighter body. Both colours of all four were
       sampled from the reference rather than taken from Bootstrap's palette, which is
       what they were before and why they read as the wrong colours. */
    .fi-body .ao-tile {
        border-radius: var(--wa-radius);
        padding: 0;
        gap: 0;
        align-items: stretch;
        overflow: hidden;
    }

    /* The icon becomes the darker block: stretched to the tile's full height, padded
       rather than sized, so the block grows with the tile instead of floating in it. */
    .fi-body .ao-tile .ao-tile-icon {
        flex: none;
        width: 4.5rem;
        height: auto;
        padding: 1rem 1.25rem;
        opacity: 1;
        align-self: stretch;
    }

    .fi-body .ao-tile-figure {
        padding: 0.9rem 1.15rem;
    }

    .fi-body .ao-tile-success { background-color: #5dc560; }
    .fi-body .ao-tile-success .ao-tile-icon { background-color: #49a94d; }

    .fi-body .ao-tile-brand { background-color: #ea5395; }
    .fi-body .ao-tile-brand .ao-tile-icon { background-color: #d61a6c; }

    .fi-body .ao-tile-warning { background-color: #eaae53; }
    .fi-body .ao-tile-warning .ao-tile-icon { background-color: #d28818; }

    .fi-body .ao-tile-info { background-color: #8dd5d9; }
    .fi-body .ao-tile-info .ao-tile-icon { background-color: #68b1b5; }

    /* ── Forms ────────────────────────
       The reference bands a form #ffffff / #efefef with the label right-aligned. Done with
       `:nth-child` over `.fi-fo-field` rather than markup this skin would have to introduce.

       Confined to `.fi-layout` — the panel's *page* layout. Unscoped it also banded the
       sign-in form, which is what "the login page style is broken" was. */
    .fi-layout .fi-fo-field:nth-child(even) {
        background: #efefef;
    }

    .fi-layout .fi-fo-field {
        padding: 0.5rem 0.75rem;
    }

    /* Inline labels get the reference's right alignment; stacked ones are left alone,
       because right-aligning a label that sits *above* its field just looks broken. */
    .fi-layout .fi-fo-field-has-inline-label .fi-fo-field-label-col {
        text-align: end;
    }

    /* ── Sign-in ─────────────────────────────────────────────────────────────
       The panel's own login page (`.fi-simple-layout`). It carries none of the chrome above —
       no bar, no rail — so left alone it was stock Filament with this skin's form rules
       leaking onto it. Given its own treatment instead: a bordered card on the grey the
       reference uses behind a bare page, and sized in `rem` off the viewport so it stays a
       card on a phone rather than a full-bleed white sheet. */
    /* Filament sizes this to the full viewport, which was written for a page with no footer
       under it. Leaving it would put the sign-in box one footer's worth below centre and give
       a login page — the one page with nothing to scroll — a scrollbar. */
    .fi-simple-layout {
        background: #f0f0f0;
        min-height: calc(100dvh - var(--wa-footer-h));
        padding: 1rem;
    }

    .fi-simple-main {
        max-width: 26rem;
        margin-block: 0;
        padding: 1.75rem;
        border: 1px solid var(--wa-panel-border);
        border-top: 3px solid var(--wa-blue);
        border-radius: var(--wa-radius);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    /* Filament stacks the brand, the page heading and the form with generous spacing meant
       for a full-width page; at this width that pushed the button below the fold on a short
       window. */
    .fi-simple-layout .fi-logo {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--wa-blue);
    }

    .fi-simple-layout .fi-header-heading {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--wa-ink);
    }

    /* The one place a full-width primary button is right: it is the only thing to press. */
    .fi-simple-layout .fi-form .fi-btn {
        width: 100%;
        justify-content: center;
        padding-block: 0.5rem;
        font-size: 15px;
    }

    /* The CAPTCHA (`adminops::captcha`). reCAPTCHA v2's checkbox is a fixed 304px in a
       fixed-size iframe — it cannot be made to fill the card, so it is centred rather than
       left to sit off to one side of a 26rem column. `transform-origin` is set for the
       narrow-window rule below, which is the only way to shrink a cross-origin iframe. */
    .ao-captcha {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.4rem;
    }

    .ao-captcha > [wire\:ignore] {
        transform-origin: top center;
    }

    .ao-captcha-note {
        font-size: 12px;
        color: var(--wa-muted);
        text-align: center;
    }

    .ao-captcha-error {
        font-size: 12px;
        color: #b42318;
        text-align: center;
    }

    /* v3 renders no widget: the empty div would otherwise contribute the flex gap. */
    .ao-captcha-v3 {
        display: none;
    }

    @media (max-width: 30rem) {
        .fi-simple-main {
            padding: 1.15rem;
        }

        /* The card's content box drops below 304px here, and an overflowing challenge would
           push the sign-in button sideways on a phone. */
        .ao-captcha > [wire\:ignore] {
            transform: scale(0.85);
            margin-block-end: -1.5rem;
        }
    }

    /* ── Footer ──────────────────────────────────────────────────────────────
       Copyright at the start, pipe-separated links at the end — the reference's split bar,
       running the full width of the window and passing *under* the left rail rather than
       starting where the content does. That full bleed is why AdminOps renders it at
       `panels::body.end`: the bar is a sibling of `.fi-layout`, not a child of the content
       column, so it needs no negative margin and no knowledge of how wide the rail is.

       It wraps rather than shrinks on a narrow window, so neither half is ever truncated. */
    .ao-admin-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.25rem 1rem;
        min-height: var(--wa-footer-h);
        padding: 0.5rem 1rem;
        background: var(--wa-blue);
        color: #ffffff;
        font-size: 12px;
        line-height: 1.4;
    }

    /* The layout above it is sized so the two together are exactly one viewport. Filament
       gives `.fi-main-ctn` `100dvh - 4rem`, which assumed its own 64px bar and no footer at
       all; left alone that is a scrollbar on every page with nothing under the fold but the
       footer. */
    .fi-main-ctn {
        min-height: calc(100dvh - var(--wa-topbar-h) - var(--wa-footer-h));
    }

    .ao-admin-footer-links {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
    }

    .ao-admin-footer-sep {
        color: rgba(255, 255, 255, 0.45);
    }

    .ao-admin-footer a {
        color: #ffffff;
        text-decoration: none;
    }

    .ao-admin-footer a:hover {
        text-decoration: underline;
    }

    /* Dark mode is left alone rather than forced off: the rules above set explicit colours on
       the chrome, so it stays readable either way, and forcing light would override a
       preference the panel offers to match a reference that never had the choice. */
</style>

<script>
    {{-- The reference opens its menus on hover; Filament's dropdowns open on click. Rather
         than replace the dropdown (Alpine owns it), the hover is translated into the click
         Alpine expects: enter an item, its menu opens and any other closes; leave both the
         item and its panel, it closes after a beat — the beat is what lets the pointer
         travel from the label down into the panel without the menu vanishing en route.
         Everything is delegated from the document, so it survives any re-render. --}}
    (() => {
        if (window.aoHoverMenus) return;
        window.aoHoverMenus = true;

        let closer = null;

        {{-- The hover unit is `.fi-dropdown`, not `.fi-topbar-item`: Filament nests
             `.fi-dropdown > .fi-dropdown-trigger > li.fi-topbar-item > button`, with the
             panel a *sibling* of the trigger — so from the button, the first ancestor that
             contains both halves is the dropdown itself. --}}
        const panelOf = (item) => item.querySelector('.fi-dropdown-panel');
        const isOpen = (item) => {
            const panel = panelOf(item);

            return !!panel && panel.style.display !== 'none' && panel.style.display !== '';
        };
        {{-- mousedown, not click: the trigger's Alpine binding is `x-on:mousedown`, so a
             programmatic click() lands on nothing at all — found by reading the trigger's
             attributes after click() silently failed. --}}
        const toggle = (item) => item.querySelector('.fi-dropdown-trigger')
            ?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

        document.addEventListener('mouseover', (event) => {
            const nav = event.target.closest('nav.fi-topbar');
            if (!nav) return;

            const item = event.target.closest('.fi-dropdown');

            clearTimeout(closer);

            if (!item || !panelOf(item)) return;

            for (const other of nav.querySelectorAll('.fi-dropdown')) {
                if (other !== item && isOpen(other)) toggle(other);
            }

            if (!isOpen(item)) toggle(item);
        });

        document.addEventListener('mouseout', (event) => {
            const item = event.target.closest('nav.fi-topbar .fi-dropdown');
            if (!item || !panelOf(item)) return;

            const to = event.relatedTarget;
            if (to && item.contains(to)) return;

            clearTimeout(closer);
            closer = setTimeout(() => {
                if (isOpen(item) && !item.matches(':hover')) toggle(item);
            }, 250);
        });

        {{-- The reference's flyout: the "- Category" rows the navigation flattens into the
             menu are folded back into a side panel that opens on hovering their parent —
             so the dropdown reads exactly as WHMCS's does. Done to the rendered markup
             because Filament's navigation cannot nest; idempotent per panel, so a menu
             processed once stays processed. --}}
        document.addEventListener('mouseover', (event) => {
            const panel = event.target.closest('nav.fi-topbar .fi-dropdown-panel');
            if (!panel || panel.dataset.aoSubDone) return;
            panel.dataset.aoSubDone = '1';

            const items = [...panel.querySelectorAll('.fi-dropdown-list-item')];

            for (let index = 0; index < items.length; index++) {
                const subs = [];
                let next = index + 1;

                while (next < items.length && items[next].textContent.trim().startsWith('- ')) {
                    subs.push(items[next]);
                    next++;
                }

                if (!subs.length) continue;

                const parent = items[index];
                const wrap = document.createElement('div');
                wrap.className = 'ao-flyout';
                parent.before(wrap);
                wrap.append(parent);
                parent.classList.add('ao-has-sub');

                const fly = document.createElement('div');
                fly.className = 'ao-flyout-panel';
                fly.append(...subs);
                wrap.append(fly);

                index = next - 1;
            }
        });
    })();
</script>
