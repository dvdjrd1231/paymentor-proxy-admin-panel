<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * Issue #34 — WHMCS organises its settings as a landing grid of named areas; core's
 * Settings page is one long form. This page is the reference's organisation over
 * Paymenter's real surfaces: every tile is a link to the screen that owns that area.
 */
class SystemSettings extends Page
{
    protected string $view = 'adminops::pages.system-settings';

    protected static ?string $slug = 'system-settings';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.settings.view');
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    /** The reference's line under the title, in this store's name. */
    public function getSubheading(): ?string
    {
        return 'Set up and configure your Paymenter installation.';
    }

    /** The left rail's areas, matching the section map in {@see getViewData()}. */
    private const AREAS = ['General', 'Billing', 'Products', 'Communications', 'Extensions'];

    /** Which category the left rail has picked; 'All' is the reference's default. */
    public string $area = 'All';

    /** The reference's sort control: its curated order, or plain A → Z. */
    public string $sort = 'popularity';

    /** Whether the setup-tasks list under the progress bar is open. */
    public bool $tasksOpen = false;

    /**
     * The reference's "Click here to view the setup tasks" checklist, over real state —
     * each line is something this store either has or does not, with the screen that
     * fixes it. Every probe is guarded: this renders on a landing page, and a broken
     * count must read as "not done", not take the page down.
     *
     * @return array<int, array{label: string, done: bool, url: ?string}>
     */
    private function setupTasks(): array
    {
        $probe = function (\Closure $check): bool {
            try {
                return (bool) $check();
            } catch (\Throwable) {
                return false;
            }
        };

        $url = fn (string $class) => $this->safeUrl($class);

        return [
            [
                'label' => 'Add your first product',
                'done' => $probe(fn () => \App\Models\Product::query()->exists()),
                'url' => $url(Catalogue::class),
            ],
            [
                'label' => 'Configure a payment gateway',
                'done' => $probe(fn () => \App\Models\Gateway::query()->exists()),
                'url' => $url(PaymentGateways::class),
            ],
            [
                'label' => 'Set up a currency',
                'done' => $probe(fn () => \App\Models\Currency::query()->exists()),
                'url' => $url(\App\Admin\Resources\CurrencyResource::class),
            ],
            [
                'label' => 'Connect a provisioning server',
                'done' => $probe(fn () => \App\Models\Server::query()->exists()),
                'url' => $url(\App\Admin\Resources\ServerResource::class),
            ],
            [
                'label' => 'Get the automation running',
                'done' => $probe(function (): bool {
                    $value = \App\Models\Setting::query()
                        ->where('key', 'last_scheduler_run')
                        ->where('settingable_type', \App\Models\CronStat::class)
                        ->value('value');

                    return $value && \Carbon\Carbon::parse($value)->diffInMinutes(now()) <= 10;
                }),
                'url' => $url(AutomationStatus::class),
            ],
        ];
    }

    /** {@see getViewData()}'s URL resolver, shared with the setup tasks. */
    private function safeUrl(string $class, string $page = 'index'): ?string
    {
        try {
            if (!class_exists($class)) {
                return null;
            }

            return method_exists($class, 'getUrl')
                ? (is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl($page) : $class::getUrl())
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getViewData(): array
    {
        $url = function (string $class, string $page = 'index'): ?string {
            try {
                if (!class_exists($class)) {
                    return null;
                }

                return method_exists($class, 'getUrl')
                    ? (is_subclass_of($class, \Filament\Resources\Resource::class) ? $class::getUrl($page) : $class::getUrl())
                    : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        // [label, url, icon, description] — the reference's own card shape: a glyph, a
        // title, one line saying what is behind it. The icon set is the same Remix Icon
        // family used everywhere else in this skin, not a new one for this page alone.
        $sections = [
            'General' => [
                // Issue #39's tabbed page when it exists; core's raw form otherwise.
                ['General Settings', $url(GeneralSettings::class) ?? $url(\App\Admin\Pages\Settings::class), 'ri-settings-3-line', 'Company name, timezone, language and the store\'s core configuration'],
                ['Administrators', $url(\App\Admin\Resources\UserResource::class), 'ri-shield-user-line', 'Staff accounts and what each one can do'],
                ['API Credentials', $url(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ApiCredentials::class), 'ri-key-2-line', 'Keys for talking to this store from the outside'],
                ['Audit Log', $url(\App\Admin\Resources\AuditResource::class), 'ri-history-line', 'Every change made in the admin, who made it and when'],
            ],
            'Billing' => [
                ['Payment Gateways', $url(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\PaymentGateways::class), 'ri-bank-card-line', 'Which gateways can take a payment, and their credentials'],
                ['Currencies', $url(\App\Admin\Resources\CurrencyResource::class), 'ri-money-dollar-circle-line', 'What this store charges in, and the exchange rates behind it'],
                ['Tax Rates', $url(\App\Admin\Resources\TaxRateResource::class), 'ri-percent-line', 'Rates applied to invoices by country or region'],
                ['Coupons', $url(\App\Admin\Resources\CouponResource::class), 'ri-coupon-3-line', 'Discount codes clients can redeem at checkout'],
                ['Payment Fee Rules', $url(\Paymenter\Extensions\Others\PaymentFees\Admin\Resources\PaymentFeeRuleResource::class), 'ri-price-tag-3-line', 'Surcharges added for a given gateway or amount'],
                ['Gateway Rules', $url(\Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource::class), 'ri-git-branch-line', 'Which gateways are offered for a given country or currency'],
            ],
            'Products' => [
                ['Products/Services', $url(Catalogue::class), 'ri-shopping-bag-3-line', 'The catalogue itself — every product this store sells'],
                ['Configurable Options', $url(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\ConfigOptionGroups::class), 'ri-equalizer-line', 'Choices a client makes at checkout, like region or plan size'],
                ['Servers', $url(\App\Admin\Resources\ServerResource::class), 'ri-server-line', 'The provisioning modules a product can be delivered through'],
                ['Client Fields', $url(\App\Admin\Resources\CustomPropertyResource::class), 'ri-list-settings-line', 'Extra fields collected on a client\'s profile'],
            ],
            'Communications' => [
                ['Email Templates', $url(\Paymenter\Extensions\Others\AdminOps\Admin\Pages\EmailTemplates::class), 'ri-mail-settings-line', 'What every automatic email says, per event'],
                ['Email Log', $url(\App\Admin\Resources\EmailLogResource::class), 'ri-mail-line', 'Every email this store has sent, and whether it went out'],
            ],
            'Extensions' => [
                ['Manage Extensions', $url(\App\Admin\Pages\Extension::class), 'ri-puzzle-line', 'Every extension this store has, enabled or not'],
            ],
        ];

        // A tile with nowhere to go helps nobody; the grid shows what this install has.
        $sections = collect($sections)
            ->map(fn (array $tiles) => array_values(array_filter($tiles, fn ($t) => $t[1] !== null)))
            ->filter(fn (array $tiles) => $tiles !== [])
            ->all();

        // The left rail's pick narrows the grid; A → Z reorders inside each area.
        if ($this->area !== 'All' && isset($sections[$this->area])) {
            $sections = [$this->area => $sections[$this->area]];
        }

        if ($this->sort === 'alphabetical') {
            $sections = array_map(function (array $tiles): array {
                usort($tiles, fn ($a, $b) => strcasecmp($a[0], $b[0]));

                return $tiles;
            }, $sections);
        }

        $tasks = $this->setupTasks();
        $done = count(array_filter($tasks, fn ($t) => $t['done']));

        return [
            'sections' => $sections,
            'areas' => self::AREAS,
            'tasks' => $tasks,
            'tasksDone' => $done,
            'tasksPct' => count($tasks) > 0 ? (int) round($done / count($tasks) * 100) : 0,
        ];
    }
}
