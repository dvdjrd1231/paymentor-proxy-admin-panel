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
 * @link docs/CORE-TOUCHPOINTS.md — "Admin panel: own login, renameable path"
 */
class Login extends BaseLogin
{
    use Captchable;

    /**
     * Core's three fields, plus the challenge last — below *Remember me*, immediately above
     * the button, which is where both the client login and the reference put it.
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

    /** Whether a challenge is both switched on and usable. */
    private function captchaEnforced(): bool
    {
        $provider = config('settings.captcha');

        return filled($provider)
            && $provider !== 'disabled'
            && filled(config('settings.captcha_site_key'))
            && filled(config('settings.captcha_secret'));
    }
}
