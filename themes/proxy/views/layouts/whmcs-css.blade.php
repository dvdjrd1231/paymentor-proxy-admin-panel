{{--
    WHMCS-style form styling for the Proxy theme.

    Hand-written CSS (not Tailwind) on purpose: this child theme reuses the default
    theme's *pre-compiled* Vite bundle, so any new Tailwind class we invented would
    not exist in that build. Plain CSS always works and needs no build step.

    BRANDING: change --brand (and optionally --brand-dark) to your brand colour.
--}}
<style>
    :root {
        --brand: #e8365d;
        --brand-dark: #c72b4c;
        --brand-contrast: #ffffff;
        --wf-border: #e3e6ea;
        --wf-label: #4a5260;
        --wf-muted: #7b8494;
        --wf-bg: #ffffff;
        --wf-section: #f7f8fa;
        --wf-text: #2b3038;
        --wf-radius: 6px;
    }

    /* Dark mode: follow the app's .dark class so we don't fight the base theme. */
    .dark {
        --wf-border: #333a45;
        --wf-label: #c9cfd8;
        --wf-muted: #97a0ad;
        --wf-bg: #1c2027;
        --wf-section: #232830;
        --wf-text: #eef1f5;
    }

    .wf-page { max-width: 960px; margin: 0 auto; padding: 2rem 1rem 3rem; }
    .wf-page--narrow { max-width: 520px; }

    .wf-card {
        background: var(--wf-bg);
        border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius);
        padding: 1.75rem;
        color: var(--wf-text);
    }

    /* Page heading: "Register  Create an account with us..." */
    .wf-title { display: flex; align-items: baseline; gap: .6rem; flex-wrap: wrap; margin-bottom: .35rem; }
    .wf-title h1 { color: var(--brand); font-size: 2rem; font-weight: 400; line-height: 1.1; margin: 0; }
    .wf-title span { color: var(--wf-muted); font-size: 1rem; }
    .wf-title-rule { border: 0; border-top: 2px solid var(--brand); opacity: .25; margin: .5rem 0 1.5rem; }

    /* Section divider: centered label with rules either side (WHMCS look) */
    .wf-section {
        display: flex; align-items: center; gap: 1rem;
        margin: 1.75rem 0 1.1rem; color: var(--brand);
        font-size: 1.05rem; text-align: center;
    }
    .wf-section::before, .wf-section::after {
        content: ""; flex: 1; border-top: 1px solid var(--wf-border);
    }
    .wf-section-note { display: block; font-size: .8rem; color: var(--wf-muted); font-style: italic; }

    /* Grid */
    .wf-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .wf-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
    .wf-col-2 { grid-column: 1 / -1; }
    @media (max-width: 720px) {
        .wf-grid, .wf-grid-3 { grid-template-columns: 1fr; }
    }

    /* Fields */
    .wf-field { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
    .wf-field label { font-size: .85rem; font-weight: 600; color: var(--wf-label); }
    .wf-req { color: var(--brand); margin-left: .15rem; }

    .wf-input, .wf-select {
        width: 100%;
        box-sizing: border-box;
        padding: .6rem .75rem;
        border: 1px solid var(--wf-border);
        border-radius: var(--wf-radius);
        background: var(--wf-bg);
        color: var(--wf-text);
        font-size: .95rem;
        line-height: 1.3;
        transition: border-color .15s, box-shadow .15s;
    }
    .wf-input::placeholder { color: var(--wf-muted); }
    .wf-input:focus, .wf-select:focus {
        outline: none;
        border-color: var(--brand);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent);
    }
    .wf-input[aria-invalid="true"] { border-color: var(--brand); }
    .wf-error { color: var(--brand); font-size: .8rem; }

    .wf-check { display: flex; align-items: flex-start; gap: .5rem; font-size: .9rem; color: var(--wf-text); }
    .wf-check input { margin-top: .2rem; }

    /* Brazil block */
    .wf-br {
        border: 1px solid var(--wf-border);
        border-left: 3px solid var(--brand);
        background: var(--wf-section);
        border-radius: var(--wf-radius);
        padding: 1.1rem;
        margin-top: .5rem;
    }
    .wf-br-head { display: flex; align-items: center; gap: .5rem; margin-bottom: .9rem; font-weight: 600; color: var(--wf-text); }
    .wf-br-flag { font-size: 1.15rem; }

    /* Buttons */
    .wf-actions { display: flex; align-items: center; gap: .75rem; margin-top: 1.75rem; flex-wrap: wrap; }
    .wf-btn {
        display: inline-block; padding: .6rem 1.35rem;
        background: var(--brand); color: var(--brand-contrast);
        border: 1px solid var(--brand); border-radius: var(--wf-radius);
        font-size: .95rem; cursor: pointer; text-decoration: none;
        transition: background .15s;
    }
    .wf-btn:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
    .wf-btn--ghost { background: transparent; color: var(--brand); }
    .wf-btn--ghost:hover { background: color-mix(in srgb, var(--brand) 10%, transparent); color: var(--brand); }
    .wf-btn--block { width: 100%; text-align: center; }

    .wf-alt { margin-top: 1.25rem; font-size: .9rem; color: var(--wf-muted); text-align: center; }
    .wf-alt a { color: var(--brand); text-decoration: none; }
    .wf-alt a:hover { text-decoration: underline; }

    /* Sidebar card used on login ("Already Registered?") */
    .wf-split { display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 860px) { .wf-split { grid-template-columns: 1fr; } }
    .wf-aside { border: 1px solid var(--wf-border); border-radius: var(--wf-radius); overflow: hidden; }
    .wf-aside-head { background: var(--brand); color: var(--brand-contrast); padding: .75rem 1rem; font-weight: 600; }
    .wf-aside-body { padding: 1rem; font-size: .9rem; color: var(--wf-text); background: var(--wf-bg); }
</style>
