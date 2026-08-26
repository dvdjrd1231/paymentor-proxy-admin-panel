<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Auth;

use App\Actions\Auth\Login as PaymenterLogin;
use App\Traits\Captchable;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Timebox;

/**
 * The admin panel's sign-in page, on Paymenter's own auth stack.
 *
 * Paymenter does not authenticate with the session guard alone: `ResolveUserSession` treats
 * the `user_sessions` row as the authority and signs out anyone without one. That row is
 * created only by {@see PaymenterLogin}, which Filament knows nothing about — so
 * the stock page authenticated, redirected, and was undone on the next request, showing a
 * login form that reappears with no error and nothing logged.
 *
 * Keeps Filament's form, validation, rate limiting and events; replaces only the sign-in.
 *
 * Also enforces Paymenter's own `tfa_secret`, which Filament's separate multi-factor system
 * does not know about — without it an admin with 2FA enabled would skip it entirely. The
 * handover matches the client login: stash the user, redirect to `/2fa`, let that component
 * finish through the same action.
 *
 * And it carries the same CAPTCHA as the client login — same setting, same provider, same
 * verification — because the admin sign-in is the more valuable of the two to guess at.
 *
 * @link docs/CORE-TOUCHPOINTS.md — "Admin panel: own login, renameable path"
 */
class Login extends BaseLogin
{
    use Captchable;

    /**
     * Core's three fields, plus the challenge last — below *Remember me*, immediately above
     * the button, which is where both the client login and the reference put it.
     *
     * It is a plain view rather than a field: the token is written straight onto `$captcha`
     * by the provider's JavaScript, which is what {@see Captchable} reads. Routing it
     * through form state would put a value the user cannot type into `getState()`, and into
     * validation that has nothing to say about it.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
            View::make('adminops::captcha')->visible(fn (): bool => $this->captchaEnforced()),
        ]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        // After the rate limiter on purpose: an unsolved challenge should still count as an
        // attempt, or a bot that never answers one gets unlimited free guesses at a password.
        if ($this->captchaEnforced()) {
            $this->captcha();
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = (bool) ($data['remember'] ?? false);

        // Core's constant-time envelope: a wrong address and a wrong password must not be
        // distinguishable by how long the answer takes.
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

        // `attemptWhen()` did this before; doing the sign-in ourselves means doing the panel
        // check ourselves too, or any customer could sign in at the admin URL.
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

        // The line this class exists for: issues the `user_sessions` row, signs the user in,
        // sets the remember cookie and fires Paymenter's login event.
        app(PaymenterLogin::class)->execute($user, $remember);

        return app(LoginResponse::class);
    }

    /**
     * Whether a challenge is both switched on and usable.
     *
     * The key check is not belt-and-braces. `Captchable::captcha()` fails a submission with
     * no token, and with no site key no widget can render one — so enabling the setting
     * while a key is missing would make the admin panel impossible to sign in to, from a
     * screen inside the admin panel. It stays open instead, exactly as the client login
     * stays usable, and Settings already marks both keys required once a provider is picked.
     */
    private function captchaEnforced(): bool
    {
        $provider = config('settings.captcha');

        return filled($provider)
            && $provider !== 'disabled'
            && filled(config('settings.captcha_site_key'))
            && filled(config('settings.captcha_secret'));
    }
}
