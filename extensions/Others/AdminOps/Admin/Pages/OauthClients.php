<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OauthClientResource;
use App\Models\OauthClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #51 ("switch to the new window standard") — WHMCS's OpenID Connect screen as
 * the navy list: Name, Client ID, Redirect URIs, with Create leading to core's form
 * (which is where the secret is minted and shown once) and Delete confirmed in place.
 * The secret never renders here: a list page must not print credentials, and core's
 * own edit screen already masks it.
 */
class OauthClients extends Page
{
    protected string $view = 'adminops::pages.oauth-clients';

    protected static ?string $slug = 'openid-connect';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public ?int $confirming = null;

    public static function canAccess(): bool
    {
        return OauthClientResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'OpenID Connect';
    }

    /** The reference's intro line for this screen, in Paymenter's words. */
    public function getSubheading(): ?string
    {
        return 'OpenID Connect clients allow external applications to authenticate your '
            . 'users via OAuth. The client secret is shown once, when it is created or '
            . 'regenerated on the client\'s own page.';
    }

    public function runDelete(): void
    {
        $id = $this->confirming;
        $this->reset('confirming');

        $client = OauthClient::find($id);

        if (!$client || !OauthClientResource::canDelete($client)) {
            Notification::make()->title('Not allowed')->danger()->send();

            return;
        }

        $client->delete();
        Notification::make()->title('OAuth client deleted')
            ->body('Anything still using it stops authenticating immediately.')->success()->send();
    }

    protected function getViewData(): array
    {
        $url = function (string $page, array $params = []): ?string {
            try {
                return OauthClientResource::getUrl($page, $params);
            } catch (\Throwable $e) {
                return null;
            }
        };

        return [
            'clients' => OauthClient::orderBy('id')->get(),
            'createUrl' => OauthClientResource::canCreate() ? $url('create') : null,
            'editUrl' => fn (OauthClient $client) => OauthClientResource::canEdit($client)
                ? $url('edit', ['record' => $client])
                : null,
        ];
    }
}
