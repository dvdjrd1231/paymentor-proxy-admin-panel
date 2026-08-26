<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Session;

/**
 * Issue a Paymenter session token for anyone signed in without one.
 *
 * The belt to {@see \Paymenter\Extensions\Others\AdminOps\Admin\Auth\Login}'s braces, and it
 * is here because of *how* that failure presents. {@see \App\Http\Middleware\ResolveUserSession}
 * requires a `user_sessions` row for every authenticated request and silently logs out anyone
 * who lacks one — no error, no log line, just the login form again. Any sign-in path that
 * does not go through {@see \App\Actions\Auth\Login} therefore looks like it worked and is
 * undone one request later, which is a genuinely hard fault to read.
 *
 * The admin login page is one such path, and it is fixed properly. But the panel's login page
 * is set in `AdminPanelProvider` — vendored core, listed in `docs/CORE-TOUCHPOINTS.md` — so an
 * upstream merge that reverts that one line brings the whole fault straight back. This makes
 * the panel survive that: whatever signs a user in, if there is no valid token by the time the
 * `Login` event fires, one is issued here.
 *
 * In normal operation this does nothing. Both {@see \App\Actions\Auth\Login} and
 * `ResolveUserSession` put the ulid in the session *before* calling `Auth::login()`, so by the
 * time the event arrives the token is already there and this returns immediately. It is not a
 * second way to log in — it only records the session that the guard has already granted.
 */
class PanelSession
{
    public static function issueMissingToken(Login $event): void
    {
        try {
            $existing = Session::get('user_session');

            if ($existing && UserSession::findValid($existing)) {
                return;
            }

            $token = UserSession::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'ip_address' => request()->ip(),
                'user_agent' => substr(request()->userAgent() ?? '', 0, 512),
                'last_activity' => Carbon::now(),
                // Never a remembered session: this path cannot tell whether the user asked
                // to be remembered, and a 30-day token nobody requested is the wrong guess.
                'expires_at' => null,
            ]);

            Session::put('user_session', $token->ulid);
        } catch (\Throwable $e) {
            // A sign-in that cannot be recorded is still a sign-in. Throwing here would turn
            // a missing safety net into a 500 on the login page, which is strictly worse.
            report($e);
        }
    }
}
