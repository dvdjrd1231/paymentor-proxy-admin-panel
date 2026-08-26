<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Auth;

use App\Actions\Auth\Login as PaymenterLogin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Timebox;

/**
 * The admin panel's sign-in page, on Paymenter's own auth stack.
 *
 * ## Why this exists — the panel could not be signed into at all
 *
 * Paymenter does not authenticate with the session guard alone. Every request runs
 * {@see \App\Http\Middleware\ResolveUserSession}, which treats the `user_sessions` row named
 * by `session('user_session')` as the authority: **an authenticated user with no such row is
 * logged straight back out** and bounced to `/`. That row is created in exactly one place,
 * {@see \App\Actions\Auth\Login}, which the client login calls and Filament knows nothing
 * about.
 *
 * So Filament's stock login page "worked" — it validated the password, called
 * `attemptWhen()`, regenerated the session and redirected to the panel — and the very next
 * request threw the session away again. The visible symptom was a login form that reappears
 * after every successful sign-in, with no error and nothing in the log, because from the
 * middleware's point of view nothing went wrong: it saw a session it had never issued.
 *
 * This page therefore keeps Filament's form, validation, rate limiting and events, and
 * replaces only the sign-in itself with the action the rest of Paymenter uses.
 *
 * ## Two-factor
 *
 * Paymenter stores its own TOTP secret on the user (`tfa_secret`) and challenges for it in
 * `App\Livewire\Auth\Tfa`; Filament has a separate multi-factor system that Paymenter never
 * registers a provider for. Signing in here without checking `tfa_secret` would therefore
 * have let an administrator who had enabled two-factor skip it entirely — a weaker admin
 * login than the customer one. Instead the same handover the client login performs is done
 * here: stash the pending user, send them to `/2fa`, and let that component finish the
 * sign-in through the same action. It redirects to the client dashboard afterwards, where
 * `KeepStaffOutOfClientArea` returns staff to the panel.
 *
 * @link docs/CORE-TOUCHPOINTS.md — "Admin panel: own login, renameable path"
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = (bool) ($data['remember'] ?? false);

        // Same constant-time envelope core uses, so a wrong address and a wrong password
        // still cannot be told apart by how long the answer takes.
        $user = app(Timebox::class)->call(function (Timebox $timebox) use ($authProvider, $authGuard, $credentials, $remember): Authenticatable {
            $this->fireAttemptingEvent($authGuard, $credentials, $remember);

            $user = $authProvider->retrieveByCredentials($credentials);

            if ((!$user) || (!$authProvider->validateCredentials($user, $credentials))) {
                $this->fireFailedEvent($authGuard, $user, $credentials);
                $this->throwFailureValidationException();
            }

            $timebox->returnEarly();

            return $user;
        }, (int) config('auth.timebox_duration', 200_000));

        // `attemptWhen()` did this for us before; doing the sign-in ourselves means doing
        // the panel check ourselves too, or any customer could sign in at the admin URL.
        if (($user instanceof FilamentUser) && (!$user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (filled($user->tfa_secret ?? null)) {
            Session::put('2fa', [
                'user_id' => $user->getAuthIdentifier(),
                'remember' => $remember,
                'expires' => now()->addMinutes(5),
            ]);

            $this->redirect(route('2fa'));

            return null;
        }

        // The one line this whole class exists for: issues the `user_sessions` row, puts its
        // ulid in the session, signs the user in, sets the remember cookie and fires
        // Paymenter's own login event (which is what writes the authentication log).
        app(PaymenterLogin::class)->execute($user, $remember);

        return app(LoginResponse::class);
    }
}
