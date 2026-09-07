<?php

namespace Paymenter\Extensions\Others\AdminOps\Support;

/**
 * WHMCS's General Settings, field by field, tab by tab, in its own order.
 *
 * Taken from Leandro's screenshots of `configgeneral.php` (2026-09-07). Each entry is
 * either bound to a real Paymenter setting, or carries a `why` explaining what this
 * platform does instead — {@see \Paymenter\Extensions\Others\AdminOps\Admin\Pages\GeneralSettings}
 * renders the first as a live control and the second disabled with the reason on it.
 *
 * Keeping the list here rather than in the page keeps the page about behaviour and this
 * about content, and makes it obvious what still needs a home when Paymenter grows a
 * setting: delete the `why`, add the `setting`.
 *
 * `label` overrides the core setting's own label where WHMCS words it differently; the
 * point of the screen is that it reads like the reference.
 */
class SettingsReference
{
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
                ['label' => 'Logo URL', 'why' => 'Logos are uploaded files here, not a URL — set them under Setup → System Settings.'],
                ['setting' => 'bill_to_text', 'label' => 'Pay To Text', 'hint' => 'This text is displayed on the invoice as the Pay To details'],
                ['setting' => 'theme', 'label' => 'System Theme', 'hint' => 'The theme you want the client area to use'],
                ['label' => 'Limit Activity Log', 'why' => 'The activity log is trimmed by the daily cron, not by a row cap.'],
                ['setting' => 'pagination', 'label' => 'Records to Display per Page', 'hint' => 'The number of records shown per page in listings'],
                ['label' => 'Maintenance Mode', 'why' => 'Paymenter has no maintenance mode; take the site down at the web server or container.'],
                ['label' => 'Maintenance Mode Message', 'why' => 'No maintenance mode, so no message to show.'],
                ['label' => 'Maintenance Mode Redirect URL', 'why' => 'No maintenance mode, so nothing to redirect to.'],
                ['label' => 'Friendly URLs', 'why' => 'Paymenter routes are always friendly; there is nothing to switch.'],
                ['setting' => 'tos', 'label' => 'Terms of Service URL', 'hint' => 'The URL to your Terms of Service page'],
            ],

            'localisation' => [
                ['label' => 'System Charset', 'why' => 'Paymenter is UTF-8 throughout and does not offer another charset.'],
                ['setting' => 'timezone', 'label' => 'Timezone', 'hint' => 'The timezone dates are displayed and stored in'],
                ['setting' => 'app_language', 'label' => 'Default Language', 'hint' => 'The language new visitors see before choosing one'],
                ['setting' => 'allowed_languages', 'label' => 'Enable Language Menu', 'hint' => 'The languages offered in the client area language menu'],
                ['setting' => 'default_currency', 'label' => 'Default Currency', 'hint' => 'The currency everything else is priced against'],
                ['label' => 'Global Date Format', 'why' => 'Dates follow the admin area\'s own MM/DD/YYYY format throughout.'],
                ['label' => 'Client Date Format', 'why' => 'The client area formats dates from the visitor\'s language.'],
                ['label' => 'Default Country', 'why' => 'Paymenter has no default country; the checkout asks for one.'],
                ['label' => 'Dynamic Field Translations', 'why' => 'Product and field names are stored once, not per language.'],
                ['label' => 'Remove Extended UTF-8 Characters', 'why' => 'Emoji are stored as sent; nothing strips them.'],
                ['label' => 'Phone Numbers', 'why' => 'The phone field is free text and is not reformatted.'],
            ],

            'ordering' => [
                ['setting' => 'cronjob_order_cancel', 'label' => 'Order Days Grace', 'hint' => 'The number of days to allow for payment of an order before it is cancelled'],
                ['setting' => 'cronjob_invoice', 'label' => 'Invoice Generation Days', 'hint' => 'Raise the renewal invoice this many days before the due date'],
                ['setting' => 'cronjob_order_suspend', 'label' => 'Suspend After', 'hint' => 'Suspend the service once its invoice is this many days overdue'],
                ['setting' => 'cronjob_order_terminate', 'label' => 'Terminate After', 'hint' => 'Terminate the service once its invoice is this many days overdue'],
                ['setting' => 'registration_disabled', 'label' => 'Allow Client Registration', 'hint' => 'Ticked, nobody can register without ordering'],
                ['label' => 'Default Order Form Template', 'why' => 'The order form comes from the active theme, not a per-store template picker.'],
                ['label' => 'On-Demand Renewals', 'why' => 'Renewals are billing-driven here; a client cannot place an early renewal order.'],
                ['label' => 'Enable TOS Acceptance', 'why' => 'The checkout always requires acceptance when a Terms of Service URL is set.'],
                ['label' => 'Auto Redirect on Checkout', 'why' => 'Checkout always lands on the invoice, where the gateway is chosen.'],
                ['label' => 'Allow Notes on Checkout', 'why' => 'Paymenter\'s checkout has no customer notes field.'],
                ['label' => 'Enable Product Cross-selling', 'why' => 'There is no cross-sell engine; related products are set per category.'],
                ['label' => 'Enable Random Usernames', 'why' => 'Usernames come from the provisioning module, not the order.'],
                ['label' => 'Signup Anniversary Prorata', 'why' => 'Paymenter does not prorate to a signup anniversary.'],
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
                ['label' => 'Disable RFC3834 Headers', 'why' => 'Paymenter always sends the auto-response headers; they cannot be turned off.'],
                ['label' => 'BCC Messages', 'why' => 'There is no global blind-copy address; set one on the template instead.'],
                ['label' => 'Presales Form Destination', 'why' => 'Paymenter has no presales contact form.'],
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
                ['label' => 'Support Ticket Mask Format', 'why' => 'Tickets are numbered sequentially; there is no mask.'],
                ['label' => 'Ticket Reply List Order', 'why' => 'Replies always read oldest to newest.'],
                ['label' => 'Support Ticket Rating', 'why' => 'Paymenter has no reply rating.'],
                ['label' => 'Allowed File Attachment Types', 'why' => 'Attachment types are fixed by the upload handler.'],
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
                ['label' => 'Enable PDF Invoices', 'why' => 'PDF invoices are always attached; there is no switch.'],
                ['label' => 'PDF Paper Size', 'why' => 'The invoice PDF is A4.'],
                ['label' => 'Late Fee Type', 'why' => 'Late fees are not part of Paymenter.'],
                ['label' => 'Accepted Credit Card Types', 'why' => 'The card types accepted are the gateway\'s, not the store\'s.'],
                ['label' => 'Enable Mass Payment', 'why' => 'Invoices are paid one at a time.'],
            ],

            'credit' => [
                ['setting' => 'credits_enabled', 'label' => 'Enable/Disable', 'hint' => 'Check to enable adding of funds by clients from the client area'],
                ['setting' => 'credits_minimum_deposit', 'label' => 'Minimum Deposit', 'hint' => 'Enter the minimum amount a client can add in a single transaction'],
                ['setting' => 'credits_maximum_deposit', 'label' => 'Maximum Deposit', 'hint' => 'Enter the maximum amount a client can add in a single transaction'],
                ['setting' => 'credits_maximum_credit', 'label' => 'Maximum Balance', 'hint' => 'Enter the maximum balance that a client can add in credit'],
                ['setting' => 'credits_auto_use', 'label' => 'Automatic Credit Use', 'hint' => 'Check to automatically apply available credit from a users credit balance to recurring invoices upon creation'],
                ['setting' => 'credits_on_downgrade', 'label' => 'Credit On Downgrade', 'hint' => 'Check to provide a prorata refund to clients when downgrading for unused time'],
                ['label' => 'Require Active Order', 'why' => 'Anyone with an account may add funds; there is no active-order gate.'],
            ],

            'affiliates' => [
                ['label' => 'Enable/Disable', 'why' => 'The affiliate system is an extension — turn it on under Setup → Extensions.'],
                ['label' => 'Affiliate Earning Percentage', 'why' => 'Set on the Affiliates extension, and per affiliate on their own screen.'],
                ['label' => 'Affiliate Bonus Deposit', 'why' => 'Paymenter pays no signup bonus.'],
                ['label' => 'Affiliate Payout Amount', 'why' => 'Withdrawals are approved individually rather than gated on a threshold.'],
                ['label' => 'Affiliate Commission Delay', 'why' => 'Commission is credited when the invoice is paid.'],
                ['label' => 'Payout Request Department', 'why' => 'Withdrawal requests have their own screen, not a ticket department.'],
            ],

            'security' => [
                ['setting' => 'captcha', 'label' => 'Captcha Form Protection', 'hint' => 'The captcha shown on public forms'],
                ['setting' => 'captcha_site_key', 'label' => 'Captcha Site Key'],
                ['setting' => 'captcha_secret', 'label' => 'Captcha Secret'],
                ['setting' => 'session_validation', 'label' => 'Disable Session IP Check', 'hint' => 'How strictly a session is tied to the address that created it'],
                ['setting' => 'trusted_proxies', 'label' => 'Trusted Proxies', 'hint' => 'Addresses of proxies that forward traffic to this install'],
                ['label' => 'Minimum User Password Strength', 'why' => 'Password strength follows Laravel\'s own rules and is not tunable here.'],
                ['label' => 'Failed Admin Login Ban Time', 'why' => 'Login throttling is fixed by the framework.'],
                ['label' => 'Whitelisted IPs', 'why' => 'No IP allow-list for login attempts.'],
                ['label' => 'Delete Encrypted Credit Card Data', 'why' => 'Paymenter never stores card data; the gateway holds it.'],
                ['label' => 'API IP Access Restriction', 'why' => 'Restrict per credential instead, under Setup → API Credentials.'],
                ['label' => 'CSRF Tokens', 'why' => 'CSRF protection is always on and cannot be disabled.'],
            ],

            'social' => [
                ['setting' => 'oauth_google', 'label' => 'Google Sign-In'],
                ['setting' => 'oauth_google_client_id', 'label' => 'Google Client ID'],
                ['setting' => 'oauth_google_client_secret', 'label' => 'Google Client Secret'],
                ['setting' => 'oauth_github', 'label' => 'GitHub Sign-In'],
                ['setting' => 'oauth_github_client_id', 'label' => 'GitHub Client ID'],
                ['setting' => 'oauth_github_client_secret', 'label' => 'GitHub Client Secret'],
                ['setting' => 'oauth_discord', 'label' => 'Discord Sign-In'],
                ['setting' => 'oauth_discord_client_id', 'label' => 'Discord Client ID'],
                ['setting' => 'oauth_discord_client_secret', 'label' => 'Discord Client Secret'],
                ['label' => 'Social Profile Links', 'why' => 'The reference lists sixteen social profiles for its footer; this theme takes its links from the footer template instead.'],
            ],

            'other' => [
                ['setting' => 'gravatar_default', 'label' => 'Admin Client Display Format', 'hint' => 'The avatar shown where a client has none'],
                ['setting' => 'debug', 'label' => 'Display Errors', 'hint' => 'Not recommended for production use'],
                ['setting' => 'cronjob_time', 'label' => 'Cron Job Time', 'hint' => 'When the daily automation runs'],
                ['setting' => 'cronjob_delete_email_logs', 'label' => 'Delete Email Logs After', 'hint' => 'Remove email logs older than this many days'],
                ['label' => 'Marketing Emails', 'why' => 'Opt-in is per client on their profile, not a global switch.'],
                ['label' => 'Optional Client Profile Fields', 'why' => 'Which profile fields are required is fixed; add your own under Custom Client Fields.'],
                ['label' => 'Locked Client/User Profile Fields', 'why' => 'Clients may edit their whole profile.'],
                ['label' => 'Banned Subdomain Prefixes', 'why' => 'No subdomain provisioning here.'],
                ['label' => 'SQL Debug Mode', 'why' => 'Query logging is a deployment setting, not a store one.'],
            ],
        ];
    }
}
