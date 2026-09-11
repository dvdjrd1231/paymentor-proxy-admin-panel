<?php

namespace Paymenter\Extensions\Others\PortalBehavior\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Staff belong in the admin panel, not the client area — the WHMCS split the client asked
 * for: administrators sign in at the panel, and reach a customer's account only by
 * impersonating them, never by browsing the client area as themselves.
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
