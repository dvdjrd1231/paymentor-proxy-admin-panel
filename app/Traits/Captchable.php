<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

trait Captchable
{
    public string $captcha = '';

    // CAPTCHA is deliberately checked only by submit methods. Livewire updates happen while
    // users type and must not turn a solved widget into a validation error mid-form.
    private function captcha()
    {
        $provider = config('settings.captcha');

        if (!$provider || $provider === 'disabled') {
            return;
        }

        if (!$this->captcha) {
            throw ValidationException::withMessages(['captcha' => 'The CAPTCHA is required.']);
        }

        if ($provider === 'turnstile') {
            $this->turnstile($this->captcha);
        } elseif ($provider === 'hcaptcha') {
            $this->hcaptcha($this->captcha);
        } elseif (in_array($provider, ['recaptcha-v2', 'recaptcha-v3'], true)) {
            $this->recaptcha($this->captcha, $provider);
        }
    }

    // Turnstile
    private function turnstile($value)
    {
        $response = Http::asForm()->acceptJson()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('settings.captcha_secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        if ($response->successful() && $response->json('success') === true) {
            return;
        }

        throw ValidationException::withMessages(['captcha' => 'The CAPTCHA was invalid.']);
    }

    // Google Recaptcha
    private function recaptcha($value, string $provider)
    {
        $response = Http::asForm()->acceptJson()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('settings.captcha_secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        if ($response->successful() && $response->json('success') === true) {
            if ($provider !== 'recaptcha-v3' || $this->recaptchaActionMatches($response->json('action'))) {
                return;
            }
        }

        throw ValidationException::withMessages(['captcha' => 'The CAPTCHA was invalid.']);
    }

    // hCaptcha
    private function hcaptcha($value)
    {
        $response = Http::asForm()->acceptJson()->timeout(10)->post('https://api.hcaptcha.com/siteverify', [
            'secret' => config('settings.captcha_secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        if ($response->successful() && $response->json('success') === true) {
            return;
        }

        throw ValidationException::withMessages(['captcha' => 'The CAPTCHA was invalid.']);
    }

    private function recaptchaActionMatches(?string $action): bool
    {
        return $action === (class_basename(static::class) === 'Register' ? 'register' : 'login');
    }
}
