<?php

namespace Paymenter\Extensions\Others\AdminOps\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Others\AdminOps\Support\WhmcsNavigation;

/**
 * WHMCS's WHOIS Lookup: a real port-43 query — IANA first for the TLD's registry, then
 * the registry itself — with the raw answer shown as WHOIS answers are.
 */
class WhoisLookup extends Page
{
    protected string $view = 'adminops::pages.whois-lookup';

    protected static ?string $slug = 'whois-lookup';

    /** Navigation is built by {@see WhmcsNavigation}. */
    protected static bool $shouldRegisterNavigation = false;

    public string $domain = '';

    public string $result = '';

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->hasPermission('admin.invoices.viewAny');
    }

    public function getTitle(): string
    {
        return 'WHOIS Lookup';
    }

    public function lookup(): void
    {
        $this->validate(['domain' => ['required', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i']],
            attributes: ['domain' => 'domain']);

        $domain = strtolower(trim($this->domain));

        try {
            $referral = self::query('whois.iana.org', substr($domain, strrpos($domain, '.') + 1));
            preg_match('/^whois:\s*(\S+)/mi', $referral, $match);

            $this->result = $match[1] ?? null
                ? self::query($match[1], $domain)
                : "No WHOIS server is published for this TLD.\n\n" . $referral;
        } catch (\Throwable $e) {
            $this->result = 'Lookup failed: ' . $e->getMessage()
                . "\n(Outbound port 43 may be blocked on this server.)";
        }
    }

    private static function query(string $server, string $subject): string
    {
        $socket = @fsockopen($server, 43, $errno, $error, 8);

        if (!$socket) {
            throw new \RuntimeException($error ?: ('could not reach ' . $server));
        }

        stream_set_timeout($socket, 8);
        fwrite($socket, $subject . "\r\n");
        $answer = '';
        while (!feof($socket) && strlen($answer) < 65536) {
            $answer .= fgets($socket, 1024);
        }
        fclose($socket);

        return trim($answer);
    }
}
