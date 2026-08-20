---
name: testing-proxy-theme
description: How to bring up the Paymenter client area locally (Docker + sqlite) and put it in the state needed to test the `proxy` theme's public pages (login, register, dashboard chrome, webfonts).
---

# Testing the proxy theme client area locally

## Run the app
PHP 8.3 is required; use a container. An image `paymenter-dev` (php:8.3-cli-alpine + ext + composer)
is normally already built on the box.

```bash
cd <repo>
docker run -d --name paymenter-serve -p 8000:8000 -v $PWD:/app -w /app paymenter-dev \
  php artisan serve --host=0.0.0.0 --port=8000
# one-off artisan:
docker run --rm -v $PWD:/app -w /app paymenter-dev php artisan <cmd>
```
`.env` uses sqlite at `database/database.sqlite`. Theme selection lives in the settings table
(`config('settings.theme')` must be `proxy`); change with `php artisan app:settings:change theme proxy`.

## Gotcha: extension-served webfonts 404 unless the extension row exists
The theme's Open Sans / Raleway `@font-face` rules are wrapped in
`@if (Route::has('extensions.others.portal.font...'))`, and those routes are only registered by
`PortalBehavior::boot()`, which only runs for rows in the `extensions` table. On a fresh DB the
table is empty, so the fonts silently fall back to system fonts and the pages *look* almost right —
easy to mistake for "font works". `php artisan app:extension:install other PortalBehavior` does NOT
create the row (it only calls `installed()`); normally you enable it in Admin → Extensions. For
headless testing, insert the row directly:

```bash
docker run --rm -v $PWD:/app -w /app paymenter-dev php artisan tinker --execute="\
DB::table('extensions')->insert(['name'=>'PortalBehavior','extension'=>'PortalBehavior','type'=>'other','enabled'=>1,'created_at'=>now(),'updated_at'=>now()]);"
```
Verify: `curl -I http://127.0.0.1:8000/extensions/portal/raleway.woff2` → `200`, `Content-Type: font/woff2`,
and in the page console `document.fonts.check('16px Raleway') === true`.
Note enabling PortalBehavior also makes `/` redirect (guests → /login, users → /dashboard).

## Register page prerequisites
The register view renders billing fields only for existing custom properties:
`php artisan db:seed --class=CustomPropertySeeder --force` (adds phone, company_name, address,
address2, city, state, zip, country for `App\Models\User`).
The Terms-of-Service panel and its `accepted` rule appear only when a TOS URL is set:
`php artisan app:settings:change tos https://example.com/terms`.

## Users
`php artisan app:user:create First Last email@example.com 'Password123' 1` (last arg = admin).
Note `/login` and `/register` redirect to `/dashboard` while signed in — log out before retesting them.

## Responsive testing
Chrome's minimum window width is ~532px, so `wmctrl`/`xdotool` resizing cannot reach 375px.
Use DevTools device toolbar (F12 then Ctrl+Shift+M) and type 375 into the width field.

## Devin secrets needed
None — everything runs locally against sqlite.
