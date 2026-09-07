<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OauthClientResource;
use App\Models\OauthClient as Client;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Str;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's "Manage Existing API Credentials" screen (Leandro, 2026-09-07, screenshots of
 * `configopenid.php?action=manage&id=1`): Name and Description over the boxed Client API
 * Credentials — Client ID, Client Secret with Reset, Creation Date — then Logo URL and
 * the repeatable Authorized Redirect URIs, closed by Save / Cancel / Delete Credential Set.
 *
 * One page for create and manage, as the reference uses one form for both.
 *
 * ## The secret
 *
 * Passport stores the secret hashed on a hashed install and in clear otherwise; either
 * way it is written once at creation. The reference shows the value in a read-only box,
 * so this does too **when it is readable**, and offers Reset Client Secret — which mints
 * a new one and shows it — when it is not. What it never does is invent a value: an empty
 * box says the secret is not recoverable and points at Reset, which is the truth.
 */
class OauthClient extends Page
{
    protected string $view = 'adminops::pages.oauth-client';

    protected static ?string $slug = 'openid-client';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?Client $client = null;

    public string $name = '';

    public string $description = '';

    public string $logoUrl = '';

    /** @var array<int, string> The reference's repeatable Authorized Redirect URIs. */
    public array $redirects = [''];

    /** Shown once, right after create or reset — never re-read from the database. */
    public ?string $freshSecret = null;

    public bool $confirmingDelete = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{record?}';
    }

    public static function canAccess(): bool
    {
        return OauthClientResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'OpenID Connect';
    }

    public function getSubheading(): ?string
    {
        return $this->client ? 'Manage Existing API Credentials' : 'Generate New Client API Credentials';
    }

    public function mount(?string $record = null): void
    {
        abort_unless(static::canAccess(), 403);

        if ($record === null) {
            abort_unless(OauthClientResource::canCreate(), 403);

            return;
        }

        $this->client = Client::findOrFail($record);
        $this->name = (string) $this->client->name;
        $this->description = (string) ($this->client->description ?? '');
        $this->logoUrl = (string) ($this->client->logo_url ?? '');
        $this->redirects = $this->splitRedirects((string) $this->client->redirect);
    }

    /** @return array<int, string> */
    private function splitRedirects(string $stored): array
    {
        $list = array_values(array_filter(array_map('trim', explode(',', $stored))));

        // The reference always shows at least one empty box to type into.
        return $list === [] ? [''] : $list;
    }

    public function addRedirect(): void
    {
        $this->redirects[] = '';
    }

    public function removeRedirect(int $index): void
    {
        unset($this->redirects[$index]);
        $this->redirects = array_values($this->redirects) ?: [''];
    }

    public function save()
    {
        $creating = $this->client === null;

        abort_unless($creating ? OauthClientResource::canCreate() : static::canAccess(), 403);

        $this->redirects = array_values(array_filter(array_map('trim', $this->redirects), fn ($u) => $u !== ''));

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            // The reference's own rule, verbatim: "Must have a protocol. Cannot contain URL
            // fragments or relative paths. Cannot be a public IP address."
            'logoUrl' => 'nullable|string|max:255',
            'redirects' => 'required|array|min:1',
            'redirects.*' => 'url',
        ], attributes: [
            'logoUrl' => 'logo URL',
            'redirects' => 'authorized redirect URIs',
            'redirects.*' => 'redirect URI',
        ]);

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'logo_url' => $this->logoUrl ?: null,
            'redirect' => implode(',', $this->redirects),
        ];

        if ($creating) {
            $secret = Str::random(40);

            $this->client = Client::create($attributes + [
                'id' => (string) Str::uuid(),
                'secret' => $secret,
                'personal_access_client' => false,
                'password_client' => false,
                'revoked' => false,
            ]);

            $this->freshSecret = $secret;

            Notification::make()->title('New API Credential Set Generated Successfully')
                ->body('Copy the client secret now — it is shown once.')->persistent()->success()->send();

            return redirect()->to(static::getUrl(['record' => $this->client->id]))
                ->with('adminops.fresh-secret', $secret);
        }

        // description and logo_url are ours, added by migration; Passport's model does not
        // list them as fillable, so they are written directly rather than dropped in silence.
        $this->client->forceFill($attributes)->save();

        Notification::make()->title('Changes saved')->success()->send();

        return null;
    }

    /** The reference's Reset Client Secret — a new secret, shown once. */
    public function resetSecret(): void
    {
        abort_unless($this->client && static::canAccess(), 403);

        $secret = Str::random(40);
        $this->client->forceFill(['secret' => $secret])->save();
        $this->freshSecret = $secret;

        Notification::make()->title('Client secret reset')
            ->body('Copy it now — anything using the old secret stops authenticating immediately.')
            ->persistent()->warning()->send();
    }

    public function delete()
    {
        abort_unless($this->client && OauthClientResource::canDelete($this->client), 403);

        $this->client->delete();

        Notification::make()->title('Credential set deleted')->success()->send();

        return redirect()->to(OauthClients::getUrl());
    }
}
