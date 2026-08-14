-- Paymenter data export (clean — no test data)
-- Generated from the local SQLite database.
-- Import AFTER `php artisan migrate --seed` on the target server.
--
-- mysql -u <user> -p <database> < paymenter-clean.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO,NO_BACKSLASH_ESCAPES';

--
-- categories (1 rows)
--
DELETE FROM `categories`;
INSERT INTO `categories` (`id`, `slug`, `name`, `description`, `image`, `parent_id`, `full_slug`, `sort`, `created_at`, `updated_at`) VALUES
(1, 'proxies', 'Proxies', NULL, NULL, NULL, 'proxies', NULL, '2026-08-12 13:54:50', '2026-08-12 13:54:50');

--
-- currencies (1 rows)
--
DELETE FROM `currencies`;
INSERT INTO `currencies` (`code`, `prefix`, `suffix`, `format`, `name`) VALUES
('USD', '$', '', '1,000.00', 'US Dollar');

--
-- custom_properties (7 rows)
--
DELETE FROM `custom_properties`;
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(1, 'Telegram Chat ID', NULL, 'telegram_chat_id', 'string', 'App\Models\User', 'nullable|string|max:64', NULL, 0, 0, 0);
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(2, 'CPF', NULL, 'cpf', 'string', 'App\Models\User', 'cpf', NULL, 0, 0, 0);
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(3, 'RG', NULL, 'rg', 'string', 'App\Models\User', 'max:20', NULL, 0, 0, 0);
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(4, 'Trade Name / Nome Fantasia', NULL, 'trade_name', 'string', 'App\Models\User', 'max:191', NULL, 0, 0, 0);
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(5, 'CNPJ', NULL, 'cnpj', 'string', 'App\Models\User', 'cnpj', NULL, 0, 0, 1);
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(6, 'State Registration / Inscrição Estadual', NULL, 'state_registration', 'string', 'App\Models\User', 'max:30', NULL, 0, 0, 0);
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(7, 'State Registration Exempt / Isento de IE', NULL, 'state_registration_exempt', 'checkbox', 'App\Models\User', NULL, NULL, 0, 0, 0);

--
-- extensions (7 rows)
--
DELETE FROM `extensions`;
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(5, 'AdminOps', 'AdminOps', 'other', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(6, 'GatewayRules', 'GatewayRules', 'other', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(7, 'PaymentFees', 'PaymentFees', 'other', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(8, 'Notifications', 'Notifications', 'other', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(9, 'BrazilianRegistration', 'BrazilianRegistration', 'other', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(10, 'TicketTools', 'TicketTools', 'other', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(11, 'ProvisioningOps', 'ProvisioningOps', 'other', 1, '2026-08-12 13:44:03', '2026-08-12 13:44:03', NULL);

--
-- gateway_rules (1 rows)
--
DELETE FROM `gateway_rules`;
INSERT INTO `gateway_rules` (`id`, `name`, `gateway`, `mode`, `country`, `currency_code`, `product_id`, `category_id`, `customer_type`, `min_amount`, `max_amount`, `priority`, `active`, `created_at`, `updated_at`) VALUES
(10, 'Hide Binance over 20 USD', 'Binance', 'deny', NULL, 'USD', NULL, NULL, NULL, 20, NULL, 10, 1, '2026-08-13 20:29:23', '2026-08-13 20:29:23');

--
-- notification_templates (12 rows)
--
DELETE FROM `notification_templates`;
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(1, 'new_login_detected', 'New login detected', 1, '# New login detected  
            
A new login was detected on your account.
            
- IP: {{ $ip }}  
- Device: {{ $device }}
- Time: {{ $time }}

**If this was you**  
You can ignore this message, there is no need to take any action.
            
**If this wasn''t you**  
Please reset your password [here]({{ route(''password.request'') }}).', NULL, NULL, NULL, NULL, 'force', 'choice_off', 'New login detected', 'A new login was detected on your account from IP: {{ $ip }} using {{ $device }} at {{ $time }}.', '{{ route("profile.security") }}', 'Alert me about new login attempts');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(2, 'new_invoice_created', 'New invoice created', 1, '# New invoice created  
            
A new invoice was created on your account.
            
Total amount: **{{ $total }}**
            
            
<div class="table">  
            
|   Item   | Quantity |  Price   |  
| :------: | :------: | :------: |
@foreach ($items as $item)
| {{ $item->description }} | {{ $item->quantity }} | {{ $item->price }} |
@endforeach
</div>
            
<div class="action">
	<a class="button button-blue" href="{{ route(''invoices.show'', $invoice) }}">
		Go to invoice
	</a>
</div>
            
@if($has_subscription)
You have a active subscription, the invoice will be automatically paid.
@endif', NULL, NULL, NULL, NULL, 'choice_on', 'choice_on', 'New invoice created', 'A new invoice was created on your account with total amount: {{ $total }}.', '{{ route("invoices.show", $invoice) }}', 'Notify me about new invoices');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(3, 'invoice_paid', 'Invoice paid', 1, '# Invoice paid  
            
Your invoice has been successfully paid.
            
Total amount: **{{ $invoice->formattedTotal }}**
            
You can view your invoice details by clicking the button below.
            
<div class="action">
	<a class="button button-blue" href="{{ route(''invoices.show'', $invoice) }}">
		View Invoice
	</a>
</div>', NULL, NULL, NULL, NULL, 'choice_on', 'choice_on', 'Invoice paid', 'Your invoice #{{ $invoice->id }} has been successfully paid with total amount: {{ $invoice->formattedTotal }}.', '{{ route("invoices.show", $invoice) }}', 'Notify me about successful payments');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(4, 'invoice_payment_failed', 'Invoice payment failed', 1, '# Invoice payment failed  

Your invoice payment has failed.

Total amount: **{{ $invoice->formattedTotal }}**
            
Please pay the invoice to avoid service interruptions.
            
<div class="action">
	<a class="button button-blue" href="{{ route(''invoices.show'', $invoice) }}">
		Pay Invoice
	</a>
</div>', NULL, NULL, NULL, NULL, 'choice_on', 'choice_on', 'Invoice payment failed', 'Your invoice #{{ $invoice->id }} payment has failed. Please pay the invoice to avoid service interruptions.', '{{ route("invoices.show", $invoice) }}', 'Alert me about payment failures');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(5, 'new_order_created', 'New order created', 1, '# New order created

A new order was created on your account.

**Order details**
<div class="table">  
            
|   Item   | Quantity |  Price   |  
| :------: | :------: | :------: |
@foreach ($items as $item)
| {{ $item->product->name }} | {{ $item->quantity }} | {{ $item->formattedPrice }} |
@endforeach
</div>', NULL, NULL, NULL, NULL, 'choice_on', 'choice_on', 'New order created', 'A new order was created on your account.', '{{ route("services") }}', 'Send me order confirmations');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(6, 'new_server_created', 'Service activated', 1, '# Service activated

Your service has been activated.

**Service details**
- Name: {{ $service->product->name }}

@isset($service->product->email_template)
**Service information**  
{!! Str::markdown(Illuminate\View\Compilers\BladeCompiler::render($service->product->email_template, get_defined_vars()[''__data''])) !!}
@endisset', NULL, NULL, NULL, NULL, 'force', 'choice_on', 'Service activated', 'Your service {{ $service->product->name }} has been activated.', '{{ route("services.show", $service) }}', 'Notify me about new service activations');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(7, 'server_suspended', 'Service suspended', 1, '# Service suspended

Your service has been suspended due to a payment failure.

**Service details**
- Name: {{ $service->product->name }}

Please pay the invoice to reactivate the service.', NULL, NULL, NULL, NULL, 'force', 'choice_on', 'Service suspended', 'Your service {{ $service->product->name }} has been suspended due to a payment failure. Please pay the invoice to reactivate the service.', '{{ route("services.show", $service) }}', 'Alert me about service suspensions');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(8, 'server_terminated', 'Service terminated', 1, '# Service terminated

Your service has been terminated.

**Service details**
- Name: {{ $service->product->name }}

Do you consider it a mistake?
<div class="action">
	<a class="button button-blue" href="{{ route(''tickets.create'') }}">
		Contact us
	</a>
</div>', NULL, NULL, NULL, NULL, 'force', 'choice_on', 'Server terminated', 'Your server {{ $service->product->name }} has been terminated.', '{{ route("services.show", $service) }}', 'Alert me about service terminations');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(9, 'new_ticket_message', '[Ticket #{{ $ticketMessage->ticket_id }}] New reply', 1, '# New ticket reply

{{ $ticketMessage->user->name }} replied to your ticket.

**Message**
{!! Str::markdown($ticketMessage->message, [
    ''html_input'' => ''strip'',
    ''allow_unsafe_links'' => false,
]) !!}', NULL, NULL, NULL, NULL, 'choice_on', 'choice_on', 'New ticket reply', 'You have a new reply on your ticket #{{ $ticketMessage->ticket_id }}.', '{{ route("tickets.show", $ticketMessage->ticket_id) }}', 'Notify me about ticket replies');
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(10, 'email_verification', 'Email verification', 1, '# Email verification
Please verify your email address by clicking the link below.
<div class="action">
    <a class="button button-blue" href="{{ $url }}">
        Verify email
    </a>
</div>
This link will expire in 60 minutes.
If you did not create an account, you can ignore this email.', NULL, NULL, NULL, NULL, 'force', 'never', NULL, NULL, NULL, NULL);
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(11, 'password_reset', 'Password reset', 1, '# Password reset
You are receiving this email because we received a password reset request for your account.

**Reset password**
<div class="action">
	<a class="button button-blue" href="{{ $url }}">
		Reset password
	</a>
</div>

This password reset link will expire in 60 minutes.

If you did not request a password reset, no further action is required.', NULL, NULL, NULL, NULL, 'force', 'never', NULL, NULL, NULL, NULL);
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `enabled`, `body`, `cc`, `bcc`, `created_at`, `updated_at`, `mail_enabled`, `in_app_enabled`, `in_app_title`, `in_app_body`, `in_app_url`, `edit_preference_message`) VALUES
(12, 'service_cancellation_received', 'Service cancellation received', 1, '# Server Cancellation Received

We''re sorry to see you go! Your server cancellation has been successfully received.

**Cancellation Details**
- Server: {{ $service->product->name }}
@if($cancellation->reason)
- Reason: {{ $cancellation->reason }}
@endif
- Requested at: {{ $cancellation->created_at->format(''F j, Y, g:i A'') }}

@if($cancellation->type === ''end_of_period'')
Your server will remain active until {{ $service->expires_at->format(''F j, Y'') }} (end of your current billing period).
@else
Your server has been terminated immediately.
@endif
', NULL, NULL, NULL, NULL, 'choice_on', 'choice_on', 'Service cancellation received', 'Your server cancellation has been successfully received.', '{{ route("services.show", $service) }}', 'Notify me about service cancellations');

--
-- payment_fee_rules (1 rows)
--
DELETE FROM `payment_fee_rules`;
INSERT INTO `payment_fee_rules` (`id`, `name`, `gateway`, `fee_type`, `fixed_amount`, `percent_amount`, `country`, `currency_code`, `product_id`, `customer_type`, `min_amount`, `max_amount`, `priority`, `active`, `created_at`, `updated_at`) VALUES
(9, 'Crypto handling fee', 'CoinPayments', 'both', 1.5, 2, NULL, NULL, NULL, NULL, NULL, NULL, 10, 1, '2026-08-13 20:29:23', '2026-08-13 20:29:23');

--
-- plans (2 rows)
--
DELETE FROM `plans`;
INSERT INTO `plans` (`id`, `name`, `priceable_type`, `priceable_id`, `type`, `billing_period`, `billing_unit`, `sort`) VALUES
(1, 'Monthly', 'App\Models\Product', 1, 'recurring', 1, 'month', NULL);
INSERT INTO `plans` (`id`, `name`, `priceable_type`, `priceable_id`, `type`, `billing_period`, `billing_unit`, `sort`) VALUES
(2, 'Annual', 'App\Models\Product', 1, 'recurring', 1, 'year', NULL);

--
-- prices (2 rows)
--
DELETE FROM `prices`;
INSERT INTO `prices` (`id`, `price`, `setup_fee`, `currency_code`, `plan_id`) VALUES
(1, 10, 0, 'USD', 1);
INSERT INTO `prices` (`id`, `price`, `setup_fee`, `currency_code`, `plan_id`) VALUES
(2, 100, 0, 'USD', 2);

--
-- products (1 rows)
--
DELETE FROM `products`;
INSERT INTO `products` (`id`, `category_id`, `name`, `image`, `slug`, `description`, `stock`, `per_user_limit`, `sort`, `allow_quantity`, `server_id`, `email_template`, `created_at`, `updated_at`, `hidden`) VALUES
(1, 1, 'IPv6 Proxy', NULL, 'ipv6-proxy', 'IPv6 proxies', NULL, NULL, NULL, 'disabled', 4, NULL, '2026-08-12 13:54:50', '2026-08-12 13:54:50', 0);

--
-- roles (1 rows)
--
DELETE FROM `roles`;
INSERT INTO `roles` (`id`, `name`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', '["*"]', '2026-08-11 18:43:31', '2026-08-11 18:43:31');

--
-- settings (85 rows)
--
DELETE FROM `settings`;
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(1, 'invoice_number', '9', 'string', 0, NULL, NULL, '2026-08-11 18:43:29', '2026-08-13 20:29:23');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(2, 'company_name', 'Paymenter', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(3, 'timezone', 'UTC', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(4, 'app_language', 'en', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(5, 'allowed_languages', '["ar","bn","da","de","en","es","fi","fr","he","hi","hu","id","it","ko","lv","nl","no","pl","pt","sr","sv","tr","uk","zh"]', 'array', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(6, 'app_url', 'http://127.0.0.1:8080', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:52:23');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(7, 'captcha', 'disabled', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(8, 'session_validation', 'none', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(9, 'oauth_google', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(10, 'oauth_github', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(11, 'oauth_discord', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(12, 'tax_enabled', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(13, 'tax_type', 'inclusive', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(14, 'mail_disable', '1', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(15, 'mail_must_verify', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(16, 'mail_encryption', 'tls', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(17, 'mail_header', '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>{{ config(''app.name'') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
{!! config(''settings.mail_css'') !!}
</style>
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@if(config(''settings.logo''))    
<tr>
<td class="header">
<a href="{{ url(''/'') }}" style="display: inline-block;">
<img src="{{ url(Storage::url(config(''settings.logo''))) }}" class="logo" alt="{{ config(''app.name'') }}">
</a>
</td>
</tr>
@endif


<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(18, 'mail_footer', '<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
© {{ date(''Y'') }} {{ config(''app.name'') }}. {{ __(''All rights reserved.'') }}
</td>
</tr>
</table>
</td>
</tr>
', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(19, 'mail_css', '/* Base */

body,
body *:not(html):not(style):not(br):not(tr):not(code) {
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, ''Segoe UI'', Roboto, Helvetica, Arial, sans-serif,
        ''Apple Color Emoji'', ''Segoe UI Emoji'', ''Segoe UI Symbol'';
    position: relative;
}

body {
    -webkit-text-size-adjust: none;
    background-color: #ffffff;
    color: #718096;
    height: 100%;
    line-height: 1.4;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

p,
ul,
ol,
blockquote {
    line-height: 1.4;
    text-align: left;
}

a {
    color: #3869d4;
}

a img {
    border: none;
}

/* Typography */

h1 {
    color: #3d4852;
    font-size: 18px;
    font-weight: bold;
    margin-top: 0;
    text-align: left;
}

h2 {
    font-size: 16px;
    font-weight: bold;
    margin-top: 0;
    text-align: left;
}

h3 {
    font-size: 14px;
    font-weight: bold;
    margin-top: 0;
    text-align: left;
}

p {
    font-size: 16px;
    line-height: 1.5em;
    margin-top: 0;
    text-align: left;
}

p.sub {
    font-size: 12px;
}

img {
    max-width: 100%;
}

/* Layout */

.wrapper {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #edf2f7;
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 0;
    padding: 0;
    width: 100%;
}

/* Header */

.header {
    padding: 25px 0;
    text-align: center;
}

.header a {
    color: #3d4852;
    font-size: 19px;
    font-weight: bold;
    text-decoration: none;
}

/* Logo */

.logo {
    height: 75px;
    max-height: 75px;
}

/* Body */

.body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    background-color: #edf2f7;
    border-bottom: 1px solid #edf2f7;
    border-top: 1px solid #edf2f7;
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    background-color: #ffffff;
    border-color: #e8e5ef;
    border-radius: 2px;
    border-width: 1px;
    box-shadow: 0 2px 0 rgba(0, 0, 150, 0.025), 2px 4px 0 rgba(0, 0, 150, 0.015);
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

/* Subcopy */

.subcopy {
    border-top: 1px solid #e8e5ef;
    margin-top: 25px;
    padding-top: 25px;
}

.subcopy p {
    font-size: 14px;
}

/* Footer */

.footer {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 570px;
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 570px;
}

.footer p {
    color: #b0adc5;
    font-size: 12px;
    text-align: center;
}

.footer a {
    color: #b0adc5;
    text-decoration: underline;
}

/* Tables */

.table table {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid #edeff2;
    margin: 0;
    padding-bottom: 8px;
}

.table td {
    color: #74787e;
    font-size: 15px;
    line-height: 18px;
    margin: 0;
    padding: 10px 0;
}

.content-cell {
    max-width: 100vw;
    padding: 32px;
}

/* Buttons */

.action {
    -premailer-cellpadding: 0;
    -premailer-cellspacing: 0;
    -premailer-width: 100%;
    margin: 30px auto;
    padding: 0;
    text-align: center;
    width: 100%;
}

.button {
    -webkit-text-size-adjust: none;
    border-radius: 4px;
    color: #fff;
    display: inline-block;
    overflow: hidden;
    text-decoration: none;
}

.button-blue,
.button-primary {
    background-color: #2d3748;
    border-bottom: 8px solid #2d3748;
    border-left: 18px solid #2d3748;
    border-right: 18px solid #2d3748;
    border-top: 8px solid #2d3748;
}

.button-green,
.button-success {
    background-color: #48bb78;
    border-bottom: 8px solid #48bb78;
    border-left: 18px solid #48bb78;
    border-right: 18px solid #48bb78;
    border-top: 8px solid #48bb78;
}

.button-red,
.button-error {
    background-color: #e53e3e;
    border-bottom: 8px solid #e53e3e;
    border-left: 18px solid #e53e3e;
    border-right: 18px solid #e53e3e;
    border-top: 8px solid #e53e3e;
}

/* Panels */

.panel {
    border-left: #2d3748 solid 4px;
    margin: 21px 0;
}

.panel-content {
    background-color: #edf2f7;
    color: #718096;
    padding: 16px;
}

.panel-content p {
    color: #718096;
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

/* Utilities */

.break-all {
    word-break: break-all;
}', 'string', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(20, 'tickets_disabled', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(21, 'ticket_departments', '["Support","Sales"]', 'array', 0, NULL, NULL, '2026-08-11 18:43:31', '2026-08-11 18:43:31');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(22, 'ticket_client_closing_disabled', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(23, 'ticket_mail_piping', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(24, 'ticket_mail_port', '993', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(25, 'cronjob_time', '00:00', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(26, 'cronjob_invoice', '7', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(27, 'cronjob_invoice_reminder', '3', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(28, 'cronjob_order_cancel', '7', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(29, 'cronjob_order_suspend', '2', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(30, 'cronjob_order_terminate', '14', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(31, 'cronjob_delete_email_logs', '90', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(32, 'cronjob_close_ticket', '7', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(33, 'credits_enabled', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(34, 'credits_minimum_deposit', '5', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(35, 'credits_maximum_deposit', '100', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(36, 'credits_maximum_credit', '300', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(37, 'credits_auto_use', '1', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(38, 'credits_on_downgrade', '1', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(39, 'theme', 'proxy', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:44:54');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(40, 'theme_default_direct_checkout', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(41, 'theme_default_small_images', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(42, 'theme_default_show_category_description', '1', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(43, 'theme_default_logo_display', 'logo-and-name', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(44, 'theme_default_home_page_text', 'Welcome to Paymenter!', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(45, 'theme_default_primary', 'hsl(229, 100%, 64%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(46, 'theme_default_secondary', 'hsl(237, 33%, 60%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(47, 'theme_default_neutral', 'hsl(220, 25%, 85%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(48, 'theme_default_base', 'hsl(0, 0%, 0%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(49, 'theme_default_muted', 'hsl(220, 0%, 53%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(50, 'theme_default_inverted', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(51, 'theme_default_background', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(52, 'theme_default_background-secondary', 'hsl(0, 0%, 97%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(53, 'theme_default_dark-primary', 'hsl(229, 100%, 64%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(54, 'theme_default_dark-secondary', 'hsl(237, 33%, 60%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(55, 'theme_default_dark-neutral', 'hsl(0, 0%, 17%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(56, 'theme_default_dark-base', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(57, 'theme_default_dark-muted', 'hsl(0, 0%, 40%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(58, 'theme_default_dark-inverted', 'hsl(220, 14%, 60%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(59, 'theme_default_dark-background', 'hsl(240, 18%, 9%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(60, 'theme_default_dark-background-secondary', 'hsl(240, 13%, 11%)', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(61, 'bill_to_text', '', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(62, 'invoice_number_padding', '1', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(63, 'invoice_number_format', 'INV-{number}', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(64, 'invoice_proforma', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(65, 'invoice_snapshot', '1', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(66, 'gravatar_default', 'wavatar', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(67, 'default_currency', 'USD', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(68, 'registration_disabled', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(69, 'pagination', '10', 'string', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(70, 'debug', '0', 'boolean', 0, NULL, NULL, '2026-08-11 18:43:32', '2026-08-11 18:43:32');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(71, 'telemetry_uuid', '2d2ce49c-ce27-480a-b63b-0773ebebbd6f', 'string', 0, NULL, NULL, '2026-08-11 18:43:47', '2026-08-11 18:43:47');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(75, 'amount', '2', 'string', 0, 'App\Models\Product', 1, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(76, 'plan', 'rot-100', 'string', 0, 'App\Models\Product', 1, '2026-08-12 13:54:50', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(77, 'region', 'eu-west', 'string', 0, 'App\Models\Product', 1, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(90, 'plan_tag', 'rot-100', 'string', 0, 'App\Models\Product', 1, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(91, 'allow_manual_rotate', '1', 'string', 0, 'App\Models\Product', 1, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(92, 'allow_change_rotate', '1', 'string', 0, 'App\Models\Product', 1, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(93, 'auth_ips_count', '3', 'string', 0, 'App\Models\Product', 1, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(94, 'rotations_per_period', '10', 'string', 0, 'App\Models\Product', 1, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(95, 'protocol', 'socks5', 'string', 0, 'App\Models\Product', 1, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(96, 'allow_rotation', 'yes', 'string', 0, 'App\Models\Product', 1, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(97, 'change_rotation', 'yes', 'string', 0, 'App\Models\Product', 1, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(98, 'auth_ips', '3', 'string', 0, 'App\Models\Product', 1, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(99, 'amount_rotations', '10', 'string', 0, 'App\Models\Product', 1, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(100, 'bwlimit', '0', 'string', 0, 'App\Models\Product', 1, '2026-08-13 01:34:37', '2026-08-13 01:34:37');

SET FOREIGN_KEY_CHECKS=1;
