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

    /* --- Shortcuts: the handful of things staff start from --- */

    .ao-shortcuts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
        gap: 0.5rem;
    }

    .ao-shortcut {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.6rem 0.5rem;
        text-align: center;
        font-weight: 500;
        border: 1px solid hsl(var(--color-neutral));
        border-radius: 0.5rem;
        transition: border-color 120ms ease, color 120ms ease;
    }

    .ao-shortcut:hover {
        border-color: hsl(var(--color-primary));
        color: hsl(var(--color-primary));
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
</style>
