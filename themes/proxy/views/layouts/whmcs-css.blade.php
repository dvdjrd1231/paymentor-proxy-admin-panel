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

    /* ── 6. Footer ─────────────────────────────────────────────────────── */
    /* width/flex-basis so the footer always spans the page, even if a parent is flex */
    .wf-footer { background: var(--wf-bg); border-top: 3px solid var(--brand); margin-top: auto; width: 100%; flex: 1 1 100%; }
    .wf-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-top: 1.1rem; padding-bottom: 1.1rem; }
    .wf-footer-copy { margin: 0; font-size: .85rem; color: var(--wf-muted); }
    .wf-totop {
        background: var(--brand); color: var(--brand-contrast); border: 0;
        border-radius: var(--wf-radius); width: 34px; height: 34px; cursor: pointer; font-size: .8rem;
    }
    .wf-totop:hover { background: var(--brand-dark); }
</style>
