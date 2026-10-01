<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep impersonation out of the admin panel's own Livewire requests.
 *
 * Core's ImpersonateMiddleware drops the session flag when the path is under the admin
 * panel, which covers a page load. Livewire does not post to that path — it posts to
 * `/<prefix>/update` — so while impersonation is active an admin page's own interactions
 * authenticate as the customer instead of the admin. Measured on the server: in one
 * session a POST to `admin/client-summary/5` ran as user 1 and a POST to
 * `paymenter/update` as user 5.
 *
 * This puts the real admin back for the duration of such a request, and deliberately does
 * **not** touch the session:
 *
 * - It runs *after* core's middleware, not before. Registered ahead of it the guard sat
 *   before StartSession, where there is no session to read, and did nothing whatsoever —
 *   it only looked like it worked because it was tested by calling it directly with a
 *   session already attached.
 * - Forgetting the flag would end the impersonation for the tab that is using it. That is
 *   what made Login as Owner intermittently answer 403: the admin tab cleared a flag the
 *   client tab still needed. Suppressing per request leaves that tab alone.
 */
class EndImpersonationInAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonating') && $this->isAdminLivewireRequest($request)) {
            $this->restoreRealUser();
        }

        return $next($request);
    }

    /**
     * Re-authenticate this request as whoever is actually signed in.
     *
     * The session still holds their id: core impersonates with `Auth::onceUsingId()`, which
     * changes the user for one request and leaves the session's own login key untouched.
     * Reading it back and using it the same way undoes the swap for this request only.
     */
    private function restoreRealUser(): void
    {
        $guard = Auth::guard();

        // getName() is SessionGuard's key for the signed-in id. A guard without it (a token
        // guard, say) was never impersonated by that mechanism, so there is nothing to undo.
        if (!$guard instanceof Guard || !method_exists($guard, 'getName')) {
            return;
        }

        $realId = session()->get($guard->getName());

        if ($realId && $realId !== Auth::id()) {
            Auth::onceUsingId($realId);
        }
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
