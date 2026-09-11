<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Session;

/** Issue a Paymenter session token for anyone signed in without one. */
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
            // A sign-in that cannot be recorded is still a sign-in; throwing would turn a
            // missing safety net into a 500 on the login page.
            report($e);
        }
    }
}
