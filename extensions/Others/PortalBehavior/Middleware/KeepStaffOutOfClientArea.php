<?php

namespace Paymenter\Extensions\Others\PortalBehavior\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Staff belong in the admin panel, not the client area — the WHMCS split the client asked
 * for: administrators sign in at the panel, and reach a customer's account only by
 * impersonating them, never by browsing the client area as themselves.
 *
 * A staff account is one with a role (`role_id`); customers have none.
 *
 * Deliberately narrow, because the cost of a false positive here is locking someone out:
 *
 *  - Impersonation passes straight through. That is the supported way in, and
 *    ImpersonateMiddleware has already swapped the authenticated user by this point, so
 *    Auth::user() is the customer and the role check does not fire anyway. The explicit
 *    session check keeps that true even if middleware order changes.
 *  - Guests and customers are untouched.
 *  - Anything outside the client area — the admin panel, extension routes, assets,
 *    webhooks, Livewire's own endpoints — is untouched.
 *  - Redirects rather than aborts, so an admin who lands on `/` or an emailed invoice link
 *    arrives at the panel instead of a dead end.
 */
class KeepStaffOutOfClientArea
{
    /** Client-area paths staff are redirected away from. */
    private const CLIENT_PATHS = [
        'dashboard', 'services', 'services/*', 'invoices', 'invoices/*',
        'account', 'account/*', 'cart', 'cart/*', 'addons', 'quotes',
        'billing/*', 'products/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (session()->has('impersonating')) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user || !$user->role_id) {
            return $next($request);
        }

        if (!$request->isMethod('GET') || !$request->is(...self::CLIENT_PATHS)) {
            return $next($request);
        }

        return redirect()->to('/' . (config('settings.admin_path') ?: 'admin'));
    }
}
