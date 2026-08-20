<?php

namespace Paymenter\Extensions\Others\PortalBehavior\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * `/` is an entry point, not a page: guests go to login, customers to their dashboard —
 * the reference portal's behaviour. Everything that is not a plain GET of `/` passes
 * through untouched, so Livewire posts, assets and every other route are unaffected.
 */
class RedirectPortalHome
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && trim($request->path(), '/') === '') {
            return redirect()->to(
                $request->user() ? route('dashboard') : route('login')
            );
        }

        return $next($request);
    }
}
