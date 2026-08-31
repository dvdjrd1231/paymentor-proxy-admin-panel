<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/** WHMCS's Domain Resolver: live DNS — A, AAAA, CNAME, MX, NS, TXT — for any hostname. */
class DomainResolver extends Page
{
    protected string $view = 'adminops::pages.domain-resolver';

    protected static ?string $slug = 'domain-resolver';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public string $host = '';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    public array $records = [];

    public bool $searched = false;

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'Domain Resolver';
    }

    public function resolve(): void
    {
        $this->validate(['host' => ['required', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i']],
            attributes: ['host' => 'hostname']);

        $this->records = [];
        $this->searched = true;
        $host = strtolower(trim($this->host));

        foreach ([DNS_A => 'A', DNS_AAAA => 'AAAA', DNS_CNAME => 'CNAME', DNS_MX => 'MX', DNS_NS => 'NS', DNS_TXT => 'TXT'] as $type => $name) {
            foreach ((array) @dns_get_record($host, $type) as $record) {
                $this->records[] = [$name, $record['host'] ?? $host, match ($name) {
                    'A' => $record['ip'] ?? '',
                    'AAAA' => $record['ipv6'] ?? '',
                    'CNAME', 'NS' => $record['target'] ?? '',
                    'MX' => ($record['pri'] ?? '') . ' ' . ($record['target'] ?? ''),
                    'TXT' => $record['txt'] ?? '',
                    default => '',
                }];
            }
        }
    }
}
