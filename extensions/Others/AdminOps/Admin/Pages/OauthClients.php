<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Admin\Resources\OauthClientResource;
use App\Models\OauthClient;
use Paymenter\Extensions\Others\AdminOps\Admin\Pages\OauthClient as OauthClientPage;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #51 — WHMCS's OpenID Connect screen, to its screenshots (Leandro, 2026-09-07):
 * the intro, the green Generate button, the records line with Jump to Page, and the navy
 * Name / Description / Last Updated grid with a Manage button on each row.
 *
 * Both Generate and Manage lead to {@see OauthClient}, which is the reference's own
 * create-and-manage form. Core's resource screens are retired behind redirects — the
 * secret is minted there and shown once, and this list never prints a credential.
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

    /** The reference's intro line, verbatim. */
    public function getSubheading(): ?string
    {
        return "Create and manage credentials that are able to access and use the API's.";
    }

    /** The reference paginates this list; the page is part of the URL, as its is. */
    #[\Livewire\Attributes\Url]
    public int $page = 1;

    public const PER_PAGE = 25;

    public function jump(int $page): void
    {
        $this->page = max(1, $page);
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

        // Ordered by name, as the reference's own sorted-by-Name column shows it.
        $clients = OauthClient::orderBy('name')->paginate(self::PER_PAGE, page: $this->page);

        return [
            'clients' => $clients,
            'createUrl' => OauthClientResource::canCreate() ? OauthClientPage::getUrl() : null,
            'manageUrl' => fn (OauthClient $client) => OauthClientPage::getUrl(['record' => $client->id]),
        ];
    }
}
