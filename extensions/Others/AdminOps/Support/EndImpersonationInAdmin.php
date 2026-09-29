<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep impersonation out of the admin panel's own Livewire requests.
 *
 * Core's ImpersonateMiddleware drops the session flag when the path is under the admin
 * panel, which covers a page load. Livewire does not post to that path — it posts to
 * `/<prefix>/update` — so while impersonation is active an admin page's own interactions
 * authenticate as the customer instead of the admin. Verified on the server: a POST to
 * `admin/client-summary/5` ends up as user 1, the same session's POST to `paymenter/update`
 * as user 5.
 *
 * That matters because Login as Owner can open the client area in a window of its own, as
 * the reference does, leaving the admin panel open behind it. The referring page says which
 * window a Livewire request belongs to; one that came from an admin page ends impersonation
 * the same way loading an admin page does. Where no referrer is sent, nothing changes and
 * core's behaviour stands.
 */
class EndImpersonationInAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonating') && $this->isAdminLivewireRequest($request)) {
            session()->forget('impersonating');
        }

        return $next($request);
    }

    private function isAdminLivewireRequest(Request $request): bool
    {
        if (!$request->hasHeader('X-Livewire')) {
            return false;
        }

        $referer = $request->headers->get('referer');

        if (!$referer) {
            return false;
        }

        $path = ltrim((string) parse_url($referer, PHP_URL_PATH), '/');
        $admin = trim(config('settings.admin_path') ?: 'admin', '/');

        return $path === $admin || str_starts_with($path, $admin . '/');
    }
}
