{{--
    WHMCS-style design system for the Proxy theme.

    Hand-written CSS (not Tailwind) on purpose: this child theme reuses the default
    theme's *pre-compiled* Vite bundle, so any new Tailwind class we invented would
    not exist in that build. Plain CSS always works and needs no build step.

    BRANDING: change --brand (and --brand-dark) below to your brand colour.
--}}
<style>
    :root {
        --brand: #e8365d;
        --brand-dark: #c72b4c;
        --brand-contrast: #ffffff;

        --wf-page-bg: #f0f2f5;
        --wf-border: #e3e6ea;
        --wf-label: #4a5260;
        --wf-muted: #7b8494;
        --wf-bg: #ffffff;
        --wf-section: #f7f8fa;
        --wf-text: #2b3038;
        --wf-radius: 4px;
        --wf-shell: 1170px;
    }

    /* The WHMCS design is light-only. The layout never applies the `dark` class, but
       we neutralise it here too so a stray toggle can't darken the chrome. */
    .dark { color-scheme: light; }

    /* WHMCS pages sit on a light grey canvas, not the base theme's dark background. */
    body { background: var(--wf-page-bg) !important; color: var(--wf-text); }

    .wf-shell { max-width: var(--wf-shell); margin: 0 auto; padding: 0 1rem; width: 100%; box-sizing: border-box; }

    /* ── 1. Header bar (white; logo left, actions right) ───────────────── */
    .wf-header { background: var(--wf-bg); border-bottom: 1px solid var(--wf-border); }
    .wf-header-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 78px; flex-wrap: wrap; }
    .wf-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none; }
    .wf-logo { height: 42px; width: auto; }
    .wf-brand-text { font-size: 1.6rem; font-weight: 700; color: var(--brand); letter-spacing: -.01em; }
    .wf-header-actions { display: flex; align-items: center; gap: .5rem; }

    .wf-hbtn {
        display: inline-block; padding: .5rem 1rem; font-size: .9rem; text-decoration: none;
        color: var(--brand); background: transparent;
        border: 1px solid var(--wf-border); border-radius: var(--wf-radius);
        transition: background .15s, color .15s, border-color .15s;
    }
    .wf-hbtn:hover { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 8%, transparent); }
    .wf-hbtn--primary { background: var(--brand); border-color: var(--brand); color: var(--brand-contrast); }
    .wf-hbtn--primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); color: var(--brand-contrast); }

    /* Logout (Livewire component) rendered in the header → primary button look */
    .wf-header-actions .wf-logout button {
        display: inline-block; padding: .5rem 1.15rem; font-size: .9rem; font-family: inherit;
        background: var(--brand); color: var(--brand-contrast);
        border: 1px solid var(--brand); border-radius: var(--wf-radius);
        cursor: pointer; transition: background .15s;
    }
    .wf-header-actions .wf-logout button:hover { background: var(--brand-dark); border-color: var(--brand-dark); }

    /* ── 2. Brand-coloured menu bar ────────────────────────────────────── */
    .wf-menubar { background: var(--brand); }
    .wf-menubar-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }

    .wf-menu { display: flex; align-items: center; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; }
    .wf-menu-item { position: relative; }
    .wf-menu-right { position: relative; }

    .wf-menu-link {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .95rem 1.1rem; font-size: .95rem; line-height: 1;
        color: var(--brand-contrast); text-decoration: none;
        background: transparent; border: 0; cursor: pointer; font-family: inherit;
        transition: background .15s;
    }
    .wf-menu-link:hover, .wf-menu-link.is-active { background: rgba(0, 0, 0, .14); color: var(--brand-contrast); }
    .wf-caret { font-size: .7rem; opacity: .85; }

    .wf-dropdown {
        position: absolute; top: 100%; left: 0; z-index: 60; min-width: 220px;
        list-style: none; margin: 0; padding: .35rem 0;
        background: var(--wf-bg); border: 1px solid var(--wf-border);
        border-top: 2px solid var(--brand);
        border-radius: 0 0 var(--wf-radius) var(--wf-radius);
        box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
    }
    .wf-dropdown--right { left: auto; right: 0; }
    .wf-dropdown a {
        display: block; padding: .55rem 1rem; font-size: .9rem;
        color: var(--wf-text); text-decoration: none;
    }
    .wf-dropdown a:hover { background: var(--wf-section); color: var(--brand); }
    .wf-dropdown-sep { border-top: 1px solid var(--wf-border); margin-top: .35rem; padding-top: .35rem; }

    /* Paymenter's logout is a Livewire component, so normalise whatever it renders
       (button/link) to look like the other dropdown entries. */
    .wf-dropdown-logout :is(a, button) {
        display: block; width: 100%; text-align: left;
        padding: .55rem 1rem; font-size: .9rem; font-family: inherit;
        color: var(--wf-text); background: transparent; border: 0; cursor: pointer;
    }
    .wf-dropdown-logout :is(a, button):hover { background: var(--wf-section); color: var(--brand); }

    .wf-burger { display: none; background: transparent; border: 0; color: var(--brand-contrast); font-size: 1.3rem; padding: .8rem .6rem; cursor: pointer; }

    @media (max-width: 820px) {
        .wf-burger { display: inline-block; }
        .wf-menu { display: none; width: 100%; flex-direction: column; align-items: stretch; }
        .wf-menu--open { display: flex; }
        .wf-menu-link { width: 100%; justify-content: space-between; }
        .wf-dropdown { position: static; box-shadow: none; border: 0; background: rgba(0, 0, 0, .12); border-radius: 0; }
        .wf-dropdown a { color: var(--brand-contrast); }
        .wf-menubar-inner { flex-wrap: wrap; }
    }

    /* ── 3. Page + card ────────────────────────────────────────────────── */
    .wf-page { max-width: var(--wf-shell); margin: 0 auto; padding: 1.75rem 1rem 3rem; }
    .wf-page--narrow { max-width: 560px; }

    .wf-card {
        background: var(--wf-bg);
        border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius);
        padding: 1.75rem;
        color: var(--wf-text);
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
    }

    /* Breadcrumbs: "Portal Home / Register" */
    .wf-crumbs { font-size: .82rem; color: var(--wf-muted); margin: 0 0 1.25rem; }
    .wf-crumbs a { color: var(--wf-muted); text-decoration: none; }
    .wf-crumbs a:hover { color: var(--brand); }
    .wf-crumbs span { margin: 0 .4rem; opacity: .6; }

    /* Page heading: big thin brand title + grey subtitle */
    .wf-title { display: flex; align-items: baseline; gap: .6rem; flex-wrap: wrap; margin-bottom: .35rem; }
    .wf-title h1 { color: var(--brand); font-size: 2.3rem; font-weight: 300; line-height: 1.1; margin: 0; }
    .wf-title span { color: var(--wf-muted); font-size: 1.05rem; font-weight: 300; }
    .wf-title-rule { border: 0; border-top: 1px solid var(--brand); opacity: .35; margin: .75rem 0 1.5rem; }

    /* Centered section divider */
    .wf-section {
        display: flex; align-items: center; gap: 1rem;
        margin: 1.75rem 0 1.1rem; color: var(--brand);
        font-size: 1.15rem; font-weight: 300; text-align: center;
    }
    .wf-section::before, .wf-section::after { content: ""; flex: 1; border-top: 1px solid var(--wf-border); }
    .wf-section-note { display: block; font-size: .8rem; color: var(--wf-muted); font-style: italic; }

    /* ── 4. Forms ──────────────────────────────────────────────────────── */
    .wf-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .wf-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
    .wf-col-2 { grid-column: 1 / -1; }
    @media (max-width: 720px) { .wf-grid, .wf-grid-3 { grid-template-columns: 1fr; } }

    .wf-field { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
    .wf-field label { font-size: .85rem; font-weight: 600; color: var(--wf-label); }
    .wf-req { color: var(--brand); margin-left: .15rem; }

    /* Icon-prefixed input group (WHMCS "input-group addon" look) */
    .wf-ig { display: flex; align-items: stretch; }
    .wf-ig-icon {
        display: inline-flex; align-items: center; justify-content: center; min-width: 40px;
        background: var(--wf-section); border: 1px solid var(--wf-border); border-right: 0;
        border-radius: var(--wf-radius) 0 0 var(--wf-radius);
        color: var(--wf-muted); font-size: .9rem;
    }
    .wf-ig .wf-input, .wf-ig .wf-select { border-radius: 0 var(--wf-radius) var(--wf-radius) 0; }

    .wf-input, .wf-select {
        width: 100%; box-sizing: border-box;
        padding: .6rem .75rem;
        border: 1px solid var(--wf-border); border-radius: var(--wf-radius);
        background: var(--wf-bg); color: var(--wf-text);
        font-size: .95rem; line-height: 1.3;
        transition: border-color .15s, box-shadow .15s;
    }
    .wf-input::placeholder { color: var(--wf-muted); }
    .wf-input:focus, .wf-select:focus {
        outline: none; border-color: var(--brand);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
    }
    .wf-input[aria-invalid="true"] { border-color: var(--brand); }
    .wf-error { color: var(--brand); font-size: .8rem; }

    .wf-check { display: flex; align-items: flex-start; gap: .5rem; font-size: .9rem; color: var(--wf-text); }
    .wf-check input { margin-top: .2rem; }

    /* Password strength meter */
    .wf-meter { height: 6px; background: var(--wf-section); border-radius: 999px; overflow: hidden; }
    .wf-meter-bar { height: 100%; width: 0; background: var(--brand); transition: width .2s, background .2s; }

    /* Brazil-only tax block */
    .wf-br {
        border: 1px solid var(--wf-border); border-left: 3px solid var(--brand);
        background: var(--wf-section); border-radius: var(--wf-radius);
        padding: 1.1rem; margin-top: .5rem;
    }
    .wf-br-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .9rem; font-weight: 600; color: var(--wf-text); }
    .wf-br-flag { font-size: 1.15rem; }

    /* ── 5. Buttons + sidebar panel ────────────────────────────────────── */
    .wf-actions { display: flex; align-items: center; gap: .75rem; margin-top: 1.75rem; flex-wrap: wrap; }
    .wf-btn {
        display: inline-block; padding: .6rem 1.5rem;
        background: var(--brand); color: var(--brand-contrast);
        border: 1px solid var(--brand); border-radius: var(--wf-radius);
        font-size: .95rem; cursor: pointer; text-decoration: none; font-family: inherit;
        transition: background .15s;
    }
    .wf-btn:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
    .wf-btn--ghost { background: transparent; color: var(--brand); }
    .wf-btn--ghost:hover { background: color-mix(in srgb, var(--brand) 10%, transparent); color: var(--brand); }
    .wf-btn--block { width: 100%; text-align: center; }

    .wf-alt { margin-top: 1.25rem; font-size: .9rem; color: var(--wf-muted); text-align: center; }
    .wf-alt a { color: var(--brand); text-decoration: none; }
    .wf-alt a:hover { text-decoration: underline; }

    /* Left sidebar panel with coloured header ("Already Registered?") */
    .wf-split { display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 860px) { .wf-split { grid-template-columns: 1fr; } }
    .wf-aside { border: 1px solid var(--wf-border); border-radius: var(--wf-radius); overflow: hidden; background: var(--wf-bg); }
    .wf-aside-head {
        background: var(--brand); color: var(--brand-contrast);
        padding: .8rem 1rem; font-weight: 600; font-size: .95rem;
        display: flex; align-items: center; gap: .5rem;
    }
    .wf-aside-body { padding: 1rem; font-size: .9rem; color: var(--wf-text); }
    .wf-aside-list { list-style: none; margin: .75rem 0 0; padding: 0; border-top: 1px solid var(--wf-border); }
    .wf-aside-list li { border-bottom: 1px solid var(--wf-border); }
    .wf-aside-list a {
        display: flex; align-items: center; justify-content: space-between;
        padding: .65rem .25rem; color: var(--wf-text); text-decoration: none; font-size: .9rem;
    }
    .wf-aside-list a:hover { color: var(--brand); }

    /* ── 5b. Client-area components (WHMCS "Six" / Bootstrap-3 design language) ──
       Rebuilt from scratch in plain CSS against Paymenter's data. We deliberately
       do NOT ship WHMCS's own template files: they are Smarty .tpl bound to WHMCS
       PHP variables and carry no open-source licence. Bootstrap 3 — the design
       language Six is built on — is MIT, so recreating the look is fine.        */

    /* Page header: big thin title + grey subtitle, underlined */
    .wf-pagehead { margin: 0 0 1.25rem; }
    .wf-pagehead h1 {
        color: var(--brand); font-size: 2.7rem; font-weight: 300; margin: 0 0 .5rem;
        padding-bottom: .6rem; border-bottom: 1px solid var(--wf-border);
        letter-spacing: -.01em; line-height: 1.15;
    }
    @media (max-width: 620px) { .wf-pagehead h1 { font-size: 2rem; } }
    .wf-pagehead p { margin: 0; color: var(--wf-muted); font-size: .95rem; }

    /* Breadcrumb — "Portal Home / Client Area" */
    /* Centred auth form — the reference portal centres login/register in one column
       rather than splitting them beside a sidebar. */
    .wf-form-narrow { max-width: 540px; margin: 0 auto; }
    .wf-actions--center { display: flex; justify-content: center; gap: .75rem; }
    /* Field labels are brand-coloured on the reference. */
    .wf-field label { color: var(--brand); font-weight: 600; font-size: .85rem; display: block; margin-bottom: .3rem; }
    .wf-title h1 { font-weight: 300; }

    .wf-crumb { font-size: .85rem; color: var(--wf-muted); margin: -.25rem 0 1rem; }
    .wf-crumb a { color: var(--brand); text-decoration: none; }
    .wf-crumb a:hover { text-decoration: underline; }
    .wf-crumb span { margin: 0 .4rem; color: var(--wf-border); }

    /* Two-column client area: sidebar + main */
    .wf-layout { display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 900px) { .wf-layout { grid-template-columns: 1fr; } }

    /* Panel — the core Six building block */
    .wf-panel {
        background: var(--wf-bg); border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius); margin-bottom: 1.25rem; overflow: hidden;
    }
    .wf-panel-heading {
        padding: .7rem 1rem; font-size: .95rem; font-weight: 600;
        background: var(--wf-section); border-bottom: 1px solid var(--wf-border);
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    }
    .wf-panel--brand > .wf-panel-heading { background: var(--brand); color: var(--brand-contrast); border-bottom-color: var(--brand); }
    .wf-panel-heading .wf-head-icon { display: inline-flex; margin-inline-end: .45rem; vertical-align: -2px; }
    .wf-panel-heading .wf-head-icon svg { width: 1rem; height: 1rem; }
    .wf-panel-heading .wf-chevron { margin-inline-start: auto; opacity: .8; font-size: .8rem; }
    .wf-panel-body { padding: 1rem; }
    .wf-panel-body > :first-child { margin-top: 0; }
    .wf-panel-body > :last-child { margin-bottom: 0; }
    .wf-panel-footer { padding: .65rem 1rem; border-top: 1px solid var(--wf-border); background: var(--wf-section); }

    /* List group — sidebar menus and simple record lists */
    .wf-list { list-style: none; margin: 0; padding: 0; }
    .wf-list > li + li { border-top: 1px solid var(--wf-border); }
    .wf-list a, .wf-list .wf-list-row {
        display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        padding: .7rem 1rem; color: var(--wf-text); text-decoration: none; font-size: .9rem;
    }
    .wf-list a:hover { background: var(--wf-section); color: var(--brand); }
    .wf-list a.is-active { background: var(--brand); color: var(--brand-contrast); }
    .wf-list-title { font-weight: 600; }
    /* Row with a trailing action button (services / invoices lists) */
    .wf-row-main { display: flex; align-items: center; gap: .75rem; min-width: 0; }
    .wf-row-link { color: var(--brand); text-decoration: none; }
    .wf-row-link:hover { text-decoration: underline; }
    .wf-list .wf-list-row { gap: 1rem; }
    .wf-list-sub { display: block; font-size: .8rem; color: var(--wf-muted); font-weight: 400; }

    /* Tables */
    .wf-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    .wf-table th, .wf-table td { padding: .65rem .75rem; text-align: start; border-bottom: 1px solid var(--wf-border); }
    .wf-table thead th { background: var(--wf-section); font-weight: 600; color: var(--wf-label); white-space: nowrap; }
    .wf-table tbody tr:hover { background: var(--wf-section); }
    .wf-table td a { color: var(--brand); text-decoration: none; }
    .wf-table td a:hover { text-decoration: underline; }
    .wf-table-wrap { overflow-x: auto; }

    /* Status labels */
    .wf-label {
        display: inline-block; padding: .2rem .55rem; border-radius: 999px;
        font-size: .75rem; font-weight: 600; line-height: 1.4; white-space: nowrap;
        background: #6b7280; color: #fff;
    }
    .wf-label--success { background: #2e9e5b; }
    .wf-label--warning { background: #d68102; }
    .wf-label--danger  { background: #c9302c; }
    .wf-label--info    { background: #2f7ecb; }

    /* Alerts / empty states */
    .wf-alert {
        padding: .85rem 1rem; border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius); background: var(--wf-section);
        color: var(--wf-text); font-size: .9rem;
    }
    .wf-alert--info { background: color-mix(in srgb, var(--brand) 7%, #fff); border-color: color-mix(in srgb, var(--brand) 25%, transparent); }
    .wf-empty { padding: 1.5rem 1rem; text-align: center; color: var(--wf-muted); font-size: .9rem; }

    /* Small stat tiles for the dashboard */
    /* Stat strip — noxproxy shows one bordered panel with vertical dividers between
       the figures, each with its own accent bar, rather than four separate cards. */
    .wf-stats {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        background: var(--wf-bg); border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius); margin-bottom: 1.25rem; overflow: hidden;
    }
    .wf-stat {
        position: relative; display: block; text-decoration: none;
        padding: 1.15rem 1.25rem 1.35rem;
        border-inline-start: 1px solid var(--wf-border);
    }
    .wf-stat:first-child { border-inline-start: 0; }
    .wf-stat:hover { background: var(--wf-section); }
    /* Accent rule beneath each figure, as on the reference portal. */
    .wf-stat::after {
        content: ''; position: absolute; left: 1.25rem; right: 1.25rem; bottom: .7rem;
        height: 2px; background: var(--brand); border-radius: 2px;
    }
    .wf-stat-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; }
    .wf-stat-num { font-size: 2.4rem; font-weight: 300; color: var(--wf-text); line-height: 1; }
    .wf-stat-label {
        margin-top: .35rem; font-size: .78rem; color: var(--wf-muted);
        text-transform: uppercase; letter-spacing: .06em;
    }
    .wf-stat-icon { color: var(--brand); flex: none; }
    .wf-stat-icon svg { width: 2rem; height: 2rem; }
    @media (max-width: 620px) {
        .wf-stat { border-inline-start: 0; border-top: 1px solid var(--wf-border); }
        .wf-stat:first-child { border-top: 0; }
    }

    .wf-btn--sm { padding: .35rem .8rem; font-size: .82rem; }
    .wf-btn--danger { background: #c9302c; border-color: #c9302c; color: #fff; }
    .wf-btn--danger:hover { background: #a82824; border-color: #a82824; }

    /* Key/value table — detail pages (service, invoice, ticket summaries) */
    .wf-table--kv th {
        width: 38%; background: transparent; font-weight: 600;
        color: var(--wf-label); white-space: normal;
    }
    .wf-table--kv tbody tr:hover { background: transparent; }
    .wf-table--kv tr:last-child th, .wf-table--kv tr:last-child td { border-bottom: 0; }
    /* Proxy addresses / credentials can be long — keep them readable, not clipped. */
    .wf-kv-value { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85rem; word-break: break-all; }

    /* Tabs inside a panel heading (provisioning module views) */
    .wf-tabs { gap: 0; padding: 0; justify-content: flex-start; flex-wrap: wrap; }
    .wf-tab {
        padding: .7rem 1rem; border: 0; background: transparent; cursor: pointer;
        font: inherit; font-size: .9rem; color: var(--wf-muted); border-bottom: 2px solid transparent;
    }
    .wf-tab:hover { color: var(--brand); }
    .wf-tab--active { color: var(--brand); border-bottom-color: var(--brand); font-weight: 600; }

    /* Product / order cards */
    .wf-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
    .wf-card {
        background: var(--wf-bg); border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius); display: flex; flex-direction: column; overflow: hidden;
    }
    .wf-card-head { padding: .7rem 1rem; background: var(--wf-section); border-bottom: 1px solid var(--wf-border); font-weight: 600; font-size: .95rem; }
    .wf-card-body { padding: 1rem; font-size: .88rem; color: var(--wf-text); flex: 1; }
    .wf-card-foot { padding: .7rem 1rem; border-top: 1px solid var(--wf-border); display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .wf-price { font-size: 1.1rem; font-weight: 600; color: var(--wf-text); }
    .wf-price small { font-size: .75rem; font-weight: 400; color: var(--wf-muted); }

    /* Totals block (cart, checkout, invoice) */
    .wf-total-row { display: flex; align-items: center; justify-content: space-between; padding: .45rem 0; font-size: .9rem; }
    .wf-total-row + .wf-total-row { border-top: 1px solid var(--wf-border); }
    .wf-total-row--grand { font-size: 1.05rem; font-weight: 700; border-top: 2px solid var(--wf-border); margin-top: .25rem; padding-top: .65rem; }

    /* Ticket conversation */
    .wf-msg { border: 1px solid var(--wf-border); border-radius: var(--wf-radius); margin-bottom: .85rem; overflow: hidden; }
    .wf-msg-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .55rem .85rem; background: var(--wf-section); border-bottom: 1px solid var(--wf-border); font-size: .85rem; }
    .wf-msg-who { font-weight: 600; }
    .wf-msg-when { color: var(--wf-muted); font-size: .8rem; }
    .wf-msg-body { padding: .85rem; font-size: .9rem; line-height: 1.55; }
    .wf-msg--staff { border-left: 3px solid var(--brand); }
    .wf-msg--staff .wf-msg-head { background: color-mix(in srgb, var(--brand) 8%, #fff); }
    .wf-thread { max-height: 60vh; overflow-y: auto; }

    /* Main content left, detail sidebar right (ticket detail) */
    .wf-layout--reverse { grid-template-columns: 1fr 280px; }
    @media (max-width: 900px) { .wf-layout--reverse { grid-template-columns: 1fr; } }

    /* Attachment drop zone */
    .wf-drop {
        display: flex; justify-content: center; align-items: center;
        border: 1px dashed var(--wf-border); border-radius: var(--wf-radius);
        background: var(--wf-section); padding: 1rem; margin-top: .35rem;
    }
    .wf-drop.is-over { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 6%, #fff); }
    .wf-drop-cta { color: var(--brand); font-weight: 600; cursor: pointer; font-size: .9rem; }
    .wf-drop-hint { display: block; color: var(--wf-muted); font-size: .8rem; margin-top: .2rem; }

    /* Invoice status bar + totals block */
    .wf-invoice-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .wf-totals { margin-left: auto; width: min(320px, 100%); }

    /* Product card image + order-form intro */
    .wf-card-img { width: 100%; height: auto; border-radius: var(--wf-radius); margin-bottom: .6rem; display: block; }
    .wf-product-intro { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
    .wf-product-intro img { max-width: 140px; height: auto; border-radius: var(--wf-radius); }
    @media (max-width: 620px) { .wf-product-intro { flex-direction: column; } }

    /* Order summary follows the form on long config pages */
    .wf-sticky { position: sticky; top: 1rem; }
    @media (max-width: 900px) { .wf-sticky { position: static; } }

    /* Storefront hero band */
    .wf-hero {
        background: var(--wf-bg); border-bottom: 1px solid var(--wf-border);
        padding: 2.5rem 0;
    }
    .wf-hero h1, .wf-hero h2 { color: var(--brand); font-weight: 300; margin-top: 0; }

    /* Cart quantity stepper + coupon row */
    .wf-qty { display: inline-flex; align-items: center; gap: .4rem; }
    .wf-qty-value { min-width: 2rem; text-align: center; font-weight: 600; }
    .wf-coupon { display: flex; align-items: flex-end; gap: .5rem; margin-bottom: .5rem; }
    .wf-coupon > :first-child { flex: 1; min-width: 0; }

    /* ── 5c. De-dark shim for not-yet-converted default pages ───────────────
       The default theme uses `bg-primary-800` (a dark shade of the brand colour)
       as a *surface* on some detail pages (service/ticket show, invoice blocks),
       with light text on top. Under this light WHMCS design that reads as a dark
       box on a light page. Remap those surfaces to a light Six panel and restore
       readable text, so every page is consistent even before it's individually
       converted to explicit .wf-panel markup. Converted pages don't use these
       classes, so this only affects the remainder. */
    .bg-primary-800,
    .bg-primary-700 {
        background-color: var(--wf-bg) !important;
        border: 1px solid var(--wf-border);
        color: var(--wf-text) !important;
    }
    .bg-primary-600 { background-color: var(--wf-section) !important; }
    /* The default puts near-white text (text-primary-100) on those dark surfaces;
       darken it so it's legible on the new light background. */
    .bg-primary-800 .text-primary-100,
    .bg-primary-700 .text-primary-100,
    .text-primary-100 { color: var(--wf-label) !important; }

    /* ── 6. Footer ─────────────────────────────────────────────────────── */
    /* full width, but never grow: the parent is a COLUMN flex container, so a
       flex-basis of 100% would stretch the footer down the whole page.
       margin-top:auto keeps it pinned to the bottom when content is short. */
    .wf-footer {
        background: var(--wf-bg); border-top: 3px solid var(--brand);
        margin-top: auto; width: 100%; flex: 0 0 auto;
    }
    .wf-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 1.1rem; padding-bottom: 1.1rem; }
    .wf-footer-copy { margin: 0; font-size: .85rem; color: var(--wf-muted); }
    .wf-totop {
        background: var(--brand); color: var(--brand-contrast); border: 0;
        border-radius: var(--wf-radius); width: 34px; height: 34px; cursor: pointer; font-size: .8rem;
    }
    .wf-totop:hover { background: var(--brand-dark); }
</style>
