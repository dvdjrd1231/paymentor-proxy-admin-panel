<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's Email Campaigns (massmail), to its screenshots: Step 1 of 2 configures the
 * recipients — message type, client criteria, product/service criteria — and Compose
 * Message moves to step 2 only when somebody would actually receive it, otherwise the
 * reference's own blue banner. Step 2 writes the subject and body and sends.
 *
 * Sending is real: one mail per matching client through Laravel's mailer — the same
 * transport every Paymenter notification uses. "Send for each service" mirrors the
 * reference's per-domain switch: one mail per matching service instead of one per client.
 */
class EmailCampaigns extends Page
{
    protected string $view = 'adminops::pages.email-campaigns';

    protected static ?string $slug = 'email-campaigns';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public int $step = 1;

    public string $campaignName = '';

    /** general | product — the reference's Addon/Domain types have no data here. */
    public string $emailType = 'general';

    /** @var array<int, string> */
    public array $countries = [];

    /** @var array<int, string> */
    public array $clientStatuses = [];

    /** @var array<int, string> */
    public array $products = [];

    /** @var array<int, string> */
    public array $serviceStatuses = [];

    public bool $perService = false;

    public bool $noRecipients = false;

    public string $subject = '';

    public string $body = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.users.viewAny');
    }

    public function getTitle(): string
    {
        return 'Email Campaigns';
    }

    /** @return array<int, array{user: User, service: ?\App\Models\Service}> */
    private function recipients(): array
    {
        $users = User::whereNull('role_id')
            ->with(['properties' => fn ($q) => $q->where('key', 'country'), 'services'])
            ->get()
            ->when($this->countries !== [], fn ($list) => $list->filter(
                fn ($u) => in_array($u->properties->first()?->value, $this->countries, true)))
            ->when($this->clientStatuses !== [], fn ($list) => $list->filter(function ($u) {
                $active = $u->services->whereIn('status', ['pending', 'active', 'suspended'])->isNotEmpty();

                return in_array($active ? 'active' : 'inactive', $this->clientStatuses, true);
            }));

        if ($this->emailType !== 'product') {
            return $users->map(fn ($u) => ['user' => $u, 'service' => null])->values()->all();
        }

        $out = [];
        foreach ($users as $user) {
            $matching = $user->services
                ->when($this->products !== [], fn ($list) => $list->filter(
                    fn ($s) => in_array((string) $s->product_id, $this->products, true)))
                ->when($this->serviceStatuses !== [], fn ($list) => $list->filter(
                    fn ($s) => in_array($s->status, $this->serviceStatuses, true)));

            if ($matching->isEmpty()) {
                continue;
            }

            if ($this->perService) {
                foreach ($matching as $service) {
                    $out[] = ['user' => $user, 'service' => $service];
                }
            } else {
                $out[] = ['user' => $user, 'service' => $matching->first()];
            }
        }

        return $out;
    }

    public function compose(): void
    {
        $this->validate(['campaignName' => 'required|string|max:255'], attributes: ['campaignName' => 'campaign name']);

        if ($this->recipients() === []) {
            // The reference's own banner, word for word.
            $this->noRecipients = true;

            return;
        }

        $this->noRecipients = false;
        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function send(): void
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $sent = 0;
        foreach ($this->recipients() as $recipient) {
            try {
                $html = nl2br(e($this->body));
                // The reference's merge data, the two fields every store has.
                $html = str_replace(
                    ['{name}', '{service}'],
                    [e(trim($recipient['user']->first_name . ' ' . $recipient['user']->last_name)),
                        e($recipient['service']?->product?->name ?? '')],
                    $html,
                );

                Mail::send([], [], fn ($message) => $message
                    ->to($recipient['user']->email)
                    ->subject($this->subject)
                    ->html($html));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('EmailCampaigns: send failed', ['to' => $recipient['user']->email, 'error' => $e->getMessage()]);
            }
        }

        Notification::make()->title('Campaign sent')
            ->body($sent . ' email(s) delivered to the matching clients.')->success()->send();
        $this->reset(['step', 'campaignName', 'countries', 'clientStatuses', 'products', 'serviceStatuses', 'perService', 'subject', 'body', 'noRecipients']);
        $this->step = 1;
        $this->emailType = 'general';
    }

    protected function getViewData(): array
    {
        return [
            'countryOptions' => User::whereNull('role_id')
                ->with(['properties' => fn ($q) => $q->where('key', 'country')])->get()
                ->map(fn ($u) => $u->properties->first()?->value)->filter()->unique()->sort()->values()->all(),
            'productOptions' => Product::orderBy('name')->get(['id', 'name']),
            'recipientCount' => count($this->recipients()),
        ];
    }
}
