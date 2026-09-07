<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

/**
 * WHMCS's General Settings, field by field, tab by tab, in its own order.
 *
 * Taken from Leandro's screenshots of `configgeneral.php` (2026-09-07). Every entry is
 * bound to a real Paymenter setting, and every control on the page saves.
 *
 * An earlier version also listed the reference's fields that this platform has no setting
 * behind, drawn disabled with the reason on each. Leandro saw the result and asked for
 * them gone ("contain too many disabled edit text and fields ... if there is unnecessary,
 * please sort"), and he is right: a screen of greyed boxes is noise, and those reasons
 * belong in documentation rather than in a form. Fifty-two such rows were removed, along
 * with the Affiliates tab, which held nothing else — affiliate settings live on the
 * Affiliates extension.
 *
 * Keeping the list here rather than in the page keeps the page about behaviour and this
 * about content.
 *
 * `label` overrides the core setting's own label where WHMCS words it differently; the
 * point of the screen is that it reads like the reference.
 */
class SettingsReference
{
    /**
     * Settings this extension owns, for fields Paymenter has no setting behind.
     *
     * Core's `Setting` rows all land in `config('settings.*')` whether or not core
     * declares them, so a key added here is readable everywhere a core one is — which is
     * what lets the client footer render these without a second storage mechanism.
     *
     * Only added where the value is actually used. The reference's Social tab also
     * carries Announcements Tweet / Facebook Recommend / Facebook Comments; those need
     * third-party embeds on the announcements page, so they are left out rather than
     * stored and ignored.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function own(): array
    {
        $networks = [
            'bitbucket' => 'BitBucket', 'discord' => 'Discord', 'facebook' => 'Facebook',
            'flickr' => 'Flickr', 'github' => 'GitHub', 'gitter' => 'Gitter',
            'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'reddit' => 'Reddit',
            'skype' => 'Skype', 'slack' => 'Slack', 'twitter' => 'Twitter',
            'viber' => 'Viber', 'vimeo' => 'Vimeo', 'whatsapp' => 'WhatsApp',
            'youtube' => 'YouTube',
        ];

        $own = [];

        foreach ($networks as $key => $label) {
            $own['social_' . $key] = [
                'name' => 'social_' . $key,
                'label' => $label,
                'type' => 'text',
                'database_type' => 'string',
                'default' => null,
            ];
        }

        return $own;
    }

    /**
     * @return array<string, array<int, array{label: string, setting?: string, hint?: string, why?: string}>>
     */
    public static function all(): array
    {
        return [
            'general' => [
                ['setting' => 'company_name', 'label' => 'Company Name', 'hint' => 'Your Company Name as you want it to appear throughout the system'],
                ['setting' => 'system_email_address', 'label' => 'Email Address', 'hint' => 'The default sender address used for emails sent by the system'],
                ['setting' => 'app_url', 'label' => 'Domain', 'hint' => 'The URL to your website homepage'],
                ['setting' => 'bill_to_text', 'label' => 'Pay To Text', 'hint' => 'This text is displayed on the invoice as the Pay To details'],
                ['setting' => 'theme', 'label' => 'System Theme', 'hint' => 'The theme you want the client area to use'],
                ['setting' => 'pagination', 'label' => 'Records to Display per Page', 'hint' => 'The number of records shown per page in listings'],
                ['setting' => 'tos', 'label' => 'Terms of Service URL', 'hint' => 'The URL to your Terms of Service page'],
            ],

            'localisation' => [
                ['setting' => 'timezone', 'label' => 'Timezone', 'hint' => 'The timezone dates are displayed and stored in'],
                ['setting' => 'app_language', 'label' => 'Default Language', 'hint' => 'The language new visitors see before choosing one'],
                ['setting' => 'allowed_languages', 'label' => 'Enable Language Menu', 'hint' => 'The languages offered in the client area language menu'],
                ['setting' => 'default_currency', 'label' => 'Default Currency', 'hint' => 'The currency everything else is priced against'],
            ],

            'ordering' => [
                ['setting' => 'cronjob_order_cancel', 'label' => 'Order Days Grace', 'hint' => 'The number of days to allow for payment of an order before it is cancelled'],
                ['setting' => 'cronjob_invoice', 'label' => 'Invoice Generation Days', 'hint' => 'Raise the renewal invoice this many days before the due date'],
                ['setting' => 'cronjob_order_suspend', 'label' => 'Suspend After', 'hint' => 'Suspend the service once its invoice is this many days overdue'],
                ['setting' => 'cronjob_order_terminate', 'label' => 'Terminate After', 'hint' => 'Terminate the service once its invoice is this many days overdue'],
                ['setting' => 'registration_disabled', 'label' => 'Allow Client Registration', 'hint' => 'Ticked, nobody can register without ordering'],
            ],

            'mail' => [
                ['setting' => 'mail_disable', 'label' => 'Disable Email Sending', 'hint' => 'Disables all outgoing emails'],
                ['setting' => 'mail_from_name', 'label' => 'System Emails From Name'],
                ['setting' => 'mail_from_address', 'label' => 'System Emails From Email'],
                ['setting' => 'mail_host', 'label' => 'Mail Host'],
                ['setting' => 'mail_port', 'label' => 'Mail Port'],
                ['setting' => 'mail_username', 'label' => 'Mail Username'],
                ['setting' => 'mail_password', 'label' => 'Mail Password'],
                ['setting' => 'mail_encryption', 'label' => 'Mail Encryption'],
                ['setting' => 'mail_must_verify', 'label' => 'Email Verification', 'hint' => 'Users must verify their email address before buying'],
                ['setting' => 'mail_header', 'label' => 'Client Email Header Content', 'hint' => 'Prefixed to the top of all client email templates'],
                ['setting' => 'mail_footer', 'label' => 'Client Email Footer Content', 'hint' => 'Added to the bottom of all client email templates'],
                ['setting' => 'mail_css', 'label' => 'Global Email CSS Styling'],
            ],

            'support' => [
                ['setting' => 'tickets_disabled', 'label' => 'Disable Support Tickets', 'hint' => 'Ticked, the support system is hidden from clients'],
                ['setting' => 'ticket_departments', 'label' => 'Support Departments', 'hint' => 'The departments a client can open a ticket in'],
                ['setting' => 'ticket_client_closing_disabled', 'label' => 'Client Tickets Require Login', 'hint' => 'Ticked, clients cannot close their own tickets'],
                ['setting' => 'ticket_mail_piping', 'label' => 'Email Piping', 'hint' => 'Read replies from a mailbox into their tickets'],
                ['setting' => 'ticket_mail_host', 'label' => 'Email Host'],
                ['setting' => 'ticket_mail_port', 'label' => 'Email Port'],
                ['setting' => 'ticket_mail_email', 'label' => 'Email Address'],
                ['setting' => 'ticket_mail_password', 'label' => 'Email Password'],
                ['setting' => 'cronjob_close_ticket', 'label' => 'Auto Close After', 'hint' => 'Close tickets with no response for this many days'],
            ],

            'invoices' => [
                ['setting' => 'invoice_number', 'label' => 'Invoice Starting #', 'hint' => 'The next invoice number that will be assigned'],
                ['setting' => 'invoice_number_format', 'label' => 'Invoice Number Format', 'hint' => 'Available tags: {YEAR} {MONTH} {DAY} {NUMBER}'],
                ['setting' => 'invoice_number_padding', 'label' => 'Invoice Number Padding', 'hint' => 'Pad the number to this many digits'],
                ['setting' => 'invoice_proforma', 'label' => 'Enable Proforma Invoicing', 'hint' => 'Number invoices only once they are paid'],
                ['setting' => 'invoice_snapshot', 'label' => 'Store Client Data Snapshot', 'hint' => 'Preserve client details on the invoice so later profile changes do not alter it'],
                ['setting' => 'tax_enabled', 'label' => 'Enable Tax', 'hint' => 'Apply tax rules to invoices'],
                ['setting' => 'tax_type', 'label' => 'Tax Type', 'hint' => 'Whether prices are shown inclusive or exclusive of tax'],
                ['setting' => 'cronjob_invoice_reminder', 'label' => 'Invoice Reminder Days', 'hint' => 'Send a reminder this many days before the due date'],
            ],

            'credit' => [
                ['setting' => 'credits_enabled', 'label' => 'Enable/Disable', 'hint' => 'Check to enable adding of funds by clients from the client area'],
                ['setting' => 'credits_minimum_deposit', 'label' => 'Minimum Deposit', 'hint' => 'Enter the minimum amount a client can add in a single transaction'],
                ['setting' => 'credits_maximum_deposit', 'label' => 'Maximum Deposit', 'hint' => 'Enter the maximum amount a client can add in a single transaction'],
                ['setting' => 'credits_maximum_credit', 'label' => 'Maximum Balance', 'hint' => 'Enter the maximum balance that a client can add in credit'],
                ['setting' => 'credits_auto_use', 'label' => 'Automatic Credit Use', 'hint' => 'Check to automatically apply available credit from a users credit balance to recurring invoices upon creation'],
                ['setting' => 'credits_on_downgrade', 'label' => 'Credit On Downgrade', 'hint' => 'Check to provide a prorata refund to clients when downgrading for unused time'],
            ],


            'security' => [
                ['setting' => 'captcha', 'label' => 'Captcha Form Protection', 'hint' => 'The captcha shown on public forms'],
                ['setting' => 'captcha_site_key', 'label' => 'Captcha Site Key'],
                ['setting' => 'captcha_secret', 'label' => 'Captcha Secret'],
                ['setting' => 'session_validation', 'label' => 'Disable Session IP Check', 'hint' => 'How strictly a session is tied to the address that created it'],
                ['setting' => 'trusted_proxies', 'label' => 'Trusted Proxies', 'hint' => 'Addresses of proxies that forward traffic to this install'],
                // WHMCS keeps these on its own Sign-In Integrations page, not on Social,
                // whose fields are profile links. They sit here so they stay reachable.
                ['setting' => 'oauth_google', 'label' => 'Google Sign-In'],
                ['setting' => 'oauth_google_client_id', 'label' => 'Google Client ID'],
                ['setting' => 'oauth_google_client_secret', 'label' => 'Google Client Secret'],
                ['setting' => 'oauth_github', 'label' => 'GitHub Sign-In'],
                ['setting' => 'oauth_github_client_id', 'label' => 'GitHub Client ID'],
                ['setting' => 'oauth_github_client_secret', 'label' => 'GitHub Client Secret'],
                ['setting' => 'oauth_discord', 'label' => 'Discord Sign-In'],
                ['setting' => 'oauth_discord_client_id', 'label' => 'Discord Client ID'],
                ['setting' => 'oauth_discord_client_secret', 'label' => 'Discord Client Secret'],
            ],

            'social' => [
                ['setting' => 'social_bitbucket', 'label' => 'BitBucket'],
                ['setting' => 'social_discord', 'label' => 'Discord', 'hint' => 'Since Discord is invite based, you must generate a permanent invite URL and enter the part after https://discord.gg/ here'],
                ['setting' => 'social_facebook', 'label' => 'Facebook'],
                ['setting' => 'social_flickr', 'label' => 'Flickr'],
                ['setting' => 'social_github', 'label' => 'GitHub'],
                ['setting' => 'social_gitter', 'label' => 'Gitter'],
                ['setting' => 'social_instagram', 'label' => 'Instagram'],
                ['setting' => 'social_linkedin', 'label' => 'LinkedIn', 'hint' => 'Requires a named company page - does not support individuals'],
                ['setting' => 'social_reddit', 'label' => 'Reddit'],
                ['setting' => 'social_skype', 'label' => 'Skype'],
                ['setting' => 'social_slack', 'label' => 'Slack', 'hint' => 'Enter Slack workspace ID'],
                ['setting' => 'social_twitter', 'label' => 'Twitter'],
                ['setting' => 'social_viber', 'label' => 'Viber'],
                ['setting' => 'social_vimeo', 'label' => 'Vimeo'],
                ['setting' => 'social_whatsapp', 'label' => 'WhatsApp', 'hint' => 'Enter phone number registered for WhatsApp including country prefix'],
                ['setting' => 'social_youtube', 'label' => 'YouTube'],
            ],

            'other' => [
                ['setting' => 'gravatar_default', 'label' => 'Admin Client Display Format', 'hint' => 'The avatar shown where a client has none'],
                ['setting' => 'debug', 'label' => 'Display Errors', 'hint' => 'Not recommended for production use'],
                ['setting' => 'cronjob_time', 'label' => 'Cron Job Time', 'hint' => 'When the daily automation runs'],
                ['setting' => 'cronjob_delete_email_logs', 'label' => 'Delete Email Logs After', 'hint' => 'Remove email logs older than this many days'],
            ],
        ];
    }
}
