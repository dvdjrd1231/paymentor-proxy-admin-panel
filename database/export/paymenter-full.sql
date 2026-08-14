-- Paymenter data export (full — includes local test data)
-- Generated from the local SQLite database.
-- Import AFTER `php artisan migrate --seed` on the target server.
--
-- mysql -u <user> -p <database> < paymenter-full.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO,NO_BACKSLASH_ESCAPES';

--
-- cart_items (2 rows)
--
DELETE FROM `cart_items`;
INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `plan_id`, `config_options`, `checkout_config`, `quantity`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '[]', '[]', 1, '2026-08-12 14:12:44', '2026-08-12 14:12:44');
INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `plan_id`, `config_options`, `checkout_config`, `quantity`, `created_at`, `updated_at`) VALUES
(2, 2, 1, 1, '[]', '[]', 1, '2026-08-12 14:13:05', '2026-08-12 14:13:05');

--
-- carts (2 rows)
--
DELETE FROM `carts`;
INSERT INTO `carts` (`id`, `ulid`, `user_id`, `coupon_id`, `currency_code`, `created_at`, `updated_at`) VALUES
(1, '01kzv530q4492ked7wmmj7zm55', 1, NULL, 'USD', '2026-08-12 14:12:44', '2026-08-12 14:12:44');
INSERT INTO `carts` (`id`, `ulid`, `user_id`, `coupon_id`, `currency_code`, `created_at`, `updated_at`) VALUES
(2, '01kzv53n2y9w6efzkk5vaz8pmc', 1, NULL, 'USD', '2026-08-12 14:13:05', '2026-08-12 14:13:05');

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
-- extensions (11 rows)
--
DELETE FROM `extensions`;
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'CoinPayments', 'CoinPayments', 'gateway', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 'Binance', 'Binance', 'gateway', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(3, 'Cryptomus', 'Cryptomus', 'gateway', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(4, 'ProxyPanel', 'ProxyPanel', 'server', 1, '2026-08-11 18:44:54', '2026-08-11 18:44:54', NULL);
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
-- invoice_items (16 rows)
--
DELETE FROM `invoice_items`;
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(4, 2, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-12 14:05:04', '2026-08-12 14:05:04');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(6, 2, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-12 14:05:04', '2026-08-12 14:05:04');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(7, 3, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-12 14:19:14', '2026-08-12 14:19:14');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(9, 3, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-12 14:19:14', '2026-08-12 14:19:14');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(10, 4, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-12 14:19:29', '2026-08-12 14:19:29');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(12, 4, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-12 14:19:29', '2026-08-12 14:19:29');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(13, 5, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-12 14:19:39', '2026-08-12 14:19:39');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(15, 5, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-12 14:19:39', '2026-08-12 14:19:39');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(16, 6, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-12 14:19:54', '2026-08-12 14:19:54');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(18, 6, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-12 14:19:54', '2026-08-12 14:19:54');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(19, 7, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-12 20:46:51', '2026-08-12 20:46:51');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(21, 7, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-12 20:46:51', '2026-08-12 20:46:51');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(22, 8, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-13 01:09:24', '2026-08-13 01:09:24');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(24, 8, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-13 01:09:24', '2026-08-13 01:09:24');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(25, 9, 100, 1, 'IPv6 Proxy', NULL, NULL, '2026-08-13 20:29:24', '2026-08-13 20:29:24');
INSERT INTO `invoice_items` (`id`, `invoice_id`, `price`, `quantity`, `description`, `reference_type`, `reference_id`, `created_at`, `updated_at`) VALUES
(27, 9, 3.5, 1, '__payment_fee__Crypto handling fee (CoinPayments)', NULL, NULL, '2026-08-13 20:29:24', '2026-08-13 20:29:24');

--
-- invoice_transactions (6 rows)
--
DELETE FROM `invoice_transactions`;
INSERT INTO `invoice_transactions` (`id`, `invoice_id`, `gateway_id`, `amount`, `fee`, `transaction_id`, `created_at`, `updated_at`, `status`, `is_credit_transaction`) VALUES
(2, 2, 1, 25, NULL, 'TXN-TEST-1', '2026-08-12 14:05:04', '2026-08-12 14:05:04', 'succeeded', 0);
INSERT INTO `invoice_transactions` (`id`, `invoice_id`, `gateway_id`, `amount`, `fee`, `transaction_id`, `created_at`, `updated_at`, `status`, `is_credit_transaction`) VALUES
(3, 3, 1, 25, NULL, 'TXN-TEST-1', '2026-08-12 14:19:15', '2026-08-12 14:19:15', 'succeeded', 0);
INSERT INTO `invoice_transactions` (`id`, `invoice_id`, `gateway_id`, `amount`, `fee`, `transaction_id`, `created_at`, `updated_at`, `status`, `is_credit_transaction`) VALUES
(4, 6, 1, 25, NULL, 'TXN-TEST-6', '2026-08-12 14:19:54', '2026-08-12 14:19:54', 'succeeded', 0);
INSERT INTO `invoice_transactions` (`id`, `invoice_id`, `gateway_id`, `amount`, `fee`, `transaction_id`, `created_at`, `updated_at`, `status`, `is_credit_transaction`) VALUES
(5, 7, 1, 25, NULL, 'TXN-TEST-7', '2026-08-12 20:46:51', '2026-08-12 20:46:51', 'succeeded', 0);
INSERT INTO `invoice_transactions` (`id`, `invoice_id`, `gateway_id`, `amount`, `fee`, `transaction_id`, `created_at`, `updated_at`, `status`, `is_credit_transaction`) VALUES
(6, 8, 1, 25, NULL, 'TXN-TEST-8', '2026-08-13 01:09:24', '2026-08-13 01:09:24', 'succeeded', 0);
INSERT INTO `invoice_transactions` (`id`, `invoice_id`, `gateway_id`, `amount`, `fee`, `transaction_id`, `created_at`, `updated_at`, `status`, `is_credit_transaction`) VALUES
(7, 9, 1, 25, NULL, 'TXN-TEST-9', '2026-08-13 20:29:24', '2026-08-13 20:29:24', 'succeeded', 0);

--
-- invoices (8 rows)
--
DELETE FROM `invoices`;
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(2, 'pending', NULL, 'USD', 1, '2026-08-12 14:05:04', '2026-08-12 14:05:04', 'INV-2');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(3, 'pending', NULL, 'USD', 1, '2026-08-12 14:19:14', '2026-08-12 14:19:14', 'INV-3');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(4, 'pending', NULL, 'USD', 1, '2026-08-12 14:19:29', '2026-08-12 14:19:29', 'INV-4');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(5, 'pending', NULL, 'USD', 1, '2026-08-12 14:19:39', '2026-08-12 14:19:39', 'INV-5');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(6, 'pending', NULL, 'USD', 1, '2026-08-12 14:19:54', '2026-08-12 14:19:54', 'INV-6');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(7, 'pending', NULL, 'USD', 1, '2026-08-12 20:46:51', '2026-08-12 20:46:51', 'INV-7');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(8, 'pending', NULL, 'USD', 1, '2026-08-13 01:09:24', '2026-08-13 01:09:24', 'INV-8');
INSERT INTO `invoices` (`id`, `status`, `due_at`, `currency_code`, `user_id`, `created_at`, `updated_at`, `number`) VALUES
(9, 'pending', NULL, 'USD', 1, '2026-08-13 20:29:24', '2026-08-13 20:29:24', 'INV-9');

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
-- properties (286 rows)
--
DELETE FROM `properties`;
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(3, NULL, NULL, 'proxy_username', 'svc1', 'App\Models\Service', 1, '2026-08-12 13:54:52', '2026-08-12 13:54:52');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(4, NULL, NULL, 'proxy_password', '9c6d4ac8', 'App\Models\Service', 1, '2026-08-12 13:54:52', '2026-08-12 13:54:52');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(5, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:7f78::1, 2a01:4f8:1000:91bc::2', 'App\Models\Service', 1, '2026-08-12 13:54:52', '2026-08-12 13:54:52');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(6, NULL, NULL, 'proxy_host', 'gw-eu-west.mock-panel.test', 'App\Models\Service', 1, '2026-08-12 13:54:52', '2026-08-12 13:54:52');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(7, NULL, NULL, 'proxy_synced_at', '2026-08-12 13:54:52', 'App\Models\Service', 1, '2026-08-12 13:54:52', '2026-08-12 13:54:52');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(11, NULL, NULL, 'proxy_username', 'svc2', 'App\Models\Service', 2, '2026-08-12 13:54:53', '2026-08-12 13:54:53');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(12, NULL, NULL, 'proxy_password', '87dc3dbf', 'App\Models\Service', 2, '2026-08-12 13:54:53', '2026-08-12 13:54:53');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(13, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:2379::1, 2a01:4f8:1001:4cab::2', 'App\Models\Service', 2, '2026-08-12 13:54:53', '2026-08-12 13:54:53');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(14, NULL, NULL, 'proxy_host', 'gw-eu-west.mock-panel.test', 'App\Models\Service', 2, '2026-08-12 13:54:53', '2026-08-12 13:54:53');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(15, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:45:19', 'App\Models\Service', 2, '2026-08-12 13:54:53', '2026-08-12 20:45:19');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(18, NULL, NULL, 'proxy_status', 'wibble', 'App\Models\Service', 2, '2026-08-12 13:55:12', '2026-08-12 13:55:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(22, NULL, NULL, 'proxy_username', 'svc4', 'App\Models\Service', 4, '2026-08-12 14:19:02', '2026-08-12 14:19:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(23, NULL, NULL, 'proxy_password', '0fa52a9b', 'App\Models\Service', 4, '2026-08-12 14:19:02', '2026-08-12 14:19:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(24, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:e92b::1, 2a01:4f8:1000:2ed2::2', 'App\Models\Service', 4, '2026-08-12 14:19:02', '2026-08-12 14:19:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(25, NULL, NULL, 'proxy_host', 'gw-eu-west.mock-panel.test', 'App\Models\Service', 4, '2026-08-12 14:19:02', '2026-08-12 14:19:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(26, NULL, NULL, 'proxy_synced_at', '2026-08-12 14:19:02', 'App\Models\Service', 4, '2026-08-12 14:19:02', '2026-08-12 14:19:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(30, NULL, NULL, 'proxy_username', 'svc5', 'App\Models\Service', 5, '2026-08-12 14:19:03', '2026-08-12 14:19:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(31, NULL, NULL, 'proxy_password', '56fe8ae7', 'App\Models\Service', 5, '2026-08-12 14:19:03', '2026-08-12 14:19:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(32, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:cfd4::1, 2a01:4f8:1001:b715::2', 'App\Models\Service', 5, '2026-08-12 14:19:03', '2026-08-12 14:19:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(33, NULL, NULL, 'proxy_host', 'gw-eu-west.mock-panel.test', 'App\Models\Service', 5, '2026-08-12 14:19:03', '2026-08-12 14:19:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(34, NULL, NULL, 'proxy_synced_at', '2026-08-12 14:19:03', 'App\Models\Service', 5, '2026-08-12 14:19:03', '2026-08-12 14:19:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(39, NULL, NULL, 'proxy_username', 'svc6', 'App\Models\Service', 6, '2026-08-12 14:19:04', '2026-08-12 14:19:04');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(40, NULL, NULL, 'proxy_password', 'f72b149c', 'App\Models\Service', 6, '2026-08-12 14:19:04', '2026-08-12 14:19:04');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(41, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:eddd::1, 2a01:4f8:1000:41eb::2', 'App\Models\Service', 6, '2026-08-12 14:19:04', '2026-08-12 14:19:04');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(42, NULL, NULL, 'proxy_host', 'gw-eu-west.mock-panel.test', 'App\Models\Service', 6, '2026-08-12 14:19:04', '2026-08-12 14:19:04');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(43, NULL, NULL, 'proxy_synced_at', '2026-08-12 14:19:04', 'App\Models\Service', 6, '2026-08-12 14:19:04', '2026-08-12 14:19:04');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(47, NULL, NULL, 'proxy_username', 'svc7', 'App\Models\Service', 7, '2026-08-12 14:19:05', '2026-08-12 14:19:05');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(48, NULL, NULL, 'proxy_password', '4c0a36de', 'App\Models\Service', 7, '2026-08-12 14:19:05', '2026-08-12 14:19:05');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(49, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:d89b::1, 2a01:4f8:1001:7f6a::2', 'App\Models\Service', 7, '2026-08-12 14:19:05', '2026-08-12 14:19:05');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(50, NULL, NULL, 'proxy_host', 'gw-eu-west.mock-panel.test', 'App\Models\Service', 7, '2026-08-12 14:19:05', '2026-08-12 14:19:05');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(51, NULL, NULL, 'proxy_synced_at', '2026-08-12 14:19:05', 'App\Models\Service', 7, '2026-08-12 14:19:05', '2026-08-12 14:19:05');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(54, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 8, '2026-08-12 20:44:10', '2026-08-12 20:44:10');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(57, NULL, NULL, 'proxy_username', 'svc8', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(58, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(59, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(60, NULL, NULL, 'proxy_api_key', '61f0299295c29c6f33438a04', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(61, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:5588::1:10000, 2a01:4f8:1000:9d4c::2:10001, 2a01:4f8:1000:1b18::3:10002', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(62, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(63, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(64, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(65, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:44:12', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(69, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 8, '2026-08-12 20:44:12', '2026-08-12 20:44:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(72, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 9, '2026-08-12 20:44:13', '2026-08-12 20:44:13');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(76, NULL, NULL, 'proxy_username', 'svc9', 'App\Models\Service', 9, '2026-08-12 20:44:13', '2026-08-12 20:44:13');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(77, NULL, NULL, 'proxy_password', '02dd837c801b1a00', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(78, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(79, NULL, NULL, 'proxy_api_key', '61c832c6366f024e52e86647', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(80, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:d14a::1:10000, 2a01:4f8:1001:a72e::2:10001', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(81, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(82, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(83, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(84, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:44:14', 'App\Models\Service', 9, '2026-08-12 20:44:14', '2026-08-12 20:44:14');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(87, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(90, NULL, NULL, 'proxy_username', 'svc10', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(91, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(92, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(93, NULL, NULL, 'proxy_api_key', '3d46a6478db6112ac7df0765', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(94, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:8187::1:10000, 2a01:4f8:1000:ac37::2:10001, 2a01:4f8:1000:9d8c::3:10002', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(95, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(96, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(97, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(98, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:44:45', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(102, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 10, '2026-08-12 20:44:45', '2026-08-12 20:44:45');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(105, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(109, NULL, NULL, 'proxy_username', 'svc11', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(110, NULL, NULL, 'proxy_password', '7e786dc92f037794', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(111, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(112, NULL, NULL, 'proxy_api_key', '1a4be70a3a4836331ec0abcd', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(113, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:a70f::1:10000, 2a01:4f8:1001:2b14::2:10001', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(114, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(115, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(116, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(117, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:44:46', 'App\Models\Service', 11, '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(120, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 12, '2026-08-12 20:44:57', '2026-08-12 20:44:57');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(123, NULL, NULL, 'proxy_username', 'svc12', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(124, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(125, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(126, NULL, NULL, 'proxy_api_key', '705fe58c44b60a50fad3aec9', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(127, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:972e::1:10000, 2a01:4f8:1000:6e93::2:10001, 2a01:4f8:1000:d6c2::3:10002', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(128, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(129, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(130, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(131, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:44:58', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(135, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 12, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(138, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 13, '2026-08-12 20:44:58', '2026-08-12 20:44:58');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(141, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(142, NULL, NULL, 'proxy_username', 'svc13', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(143, NULL, NULL, 'proxy_password', 'BrandNewPass1', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-13 01:07:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(144, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(145, NULL, NULL, 'proxy_api_key', '7de290950efb4aeb2269e2e2', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(146, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:90e5::1:10000, 2a01:4f8:1001:3be5::2:10001', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(147, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(148, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(149, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:44:59');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(150, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:46:34', 'App\Models\Service', 13, '2026-08-12 20:44:59', '2026-08-12 20:46:34');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(153, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(156, NULL, NULL, 'proxy_username', 'svc14', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(157, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(158, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(159, NULL, NULL, 'proxy_api_key', 'e93342b824d1af1582fab712', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(160, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:6439::1:10000, 2a01:4f8:1000:1340::2:10001, 2a01:4f8:1000:37f5::3:10002', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(161, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(162, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(163, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(164, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:46:48', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(168, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 14, '2026-08-12 20:46:48', '2026-08-12 20:46:48');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(171, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(174, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(175, NULL, NULL, 'proxy_username', 'svc15', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(176, NULL, NULL, 'proxy_password', 'ac1b4ce9727685a8', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(177, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(178, NULL, NULL, 'proxy_api_key', '6c0037fa79a7f7c8e8269efd', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(179, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:1586::1:10000, 2a01:4f8:1001:3cb2::2:10001', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(180, NULL, NULL, 'proxy_expiration', '1789171200', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(181, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(182, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(183, NULL, NULL, 'proxy_synced_at', '2026-08-12 20:46:49', 'App\Models\Service', 15, '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(186, NULL, NULL, 'proxy_auth_ips', '203.0.113.20, 198.51.100.30', 'App\Models\Service', 13, '2026-08-13 01:07:04', '2026-08-13 01:07:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(187, NULL, NULL, 'proxy_rotation_time', '15', 'App\Models\Service', 13, '2026-08-13 01:07:49', '2026-08-13 01:07:49');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(188, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(191, NULL, NULL, 'proxy_username', 'svc16', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(192, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(193, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(194, NULL, NULL, 'proxy_api_key', 'c7862c27bc3b68d235831aa0', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(195, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:67f8::1:10000, 2a01:4f8:1000:7343::2:10001, 2a01:4f8:1000:0bd5::3:10002', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(196, NULL, NULL, 'proxy_expiration', '1789257600', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(197, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(198, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(199, NULL, NULL, 'proxy_synced_at', '2026-08-13 01:09:22', 'App\Models\Service', 16, '2026-08-13 01:09:21', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(203, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 16, '2026-08-13 01:09:22', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(206, NULL, NULL, 'region', 'us-nyc', 'App\Models\Service', 17, '2026-08-13 01:09:22', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(209, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 17, '2026-08-13 01:09:22', '2026-08-13 01:09:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(210, NULL, NULL, 'proxy_username', 'svc17', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(211, NULL, NULL, 'proxy_password', '3fa402e8d5383157', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(212, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(213, NULL, NULL, 'proxy_api_key', '5c23c59dbde62b66f0cbf29d', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(214, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:0040::1:10000, 2a01:4f8:1001:04ad::2:10001', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(215, NULL, NULL, 'proxy_expiration', '1789257600', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(216, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(217, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(218, NULL, NULL, 'proxy_synced_at', '2026-08-13 01:09:23', 'App\Models\Service', 17, '2026-08-13 01:09:23', '2026-08-13 01:09:23');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(221, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(224, NULL, NULL, 'proxy_username', 'svc18', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(225, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(226, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(227, NULL, NULL, 'proxy_api_key', '1093406245c139220ff1158f', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(228, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:e339::1:10000, 2a01:4f8:1000:cc40::2:10001, 2a01:4f8:1000:eede::3:10002', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(229, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(230, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(231, NULL, NULL, 'proxy_synced_at', '2026-08-13 01:34:38', 'App\Models\Service', 18, '2026-08-13 01:34:37', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(235, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 18, '2026-08-13 01:34:38', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(238, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 19, '2026-08-13 01:34:38', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(241, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 19, '2026-08-13 01:34:38', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(242, NULL, NULL, 'proxy_username', 'svc19', 'App\Models\Service', 19, '2026-08-13 01:34:38', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(243, NULL, NULL, 'proxy_password', '463cf356', 'App\Models\Service', 19, '2026-08-13 01:34:38', '2026-08-13 01:34:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(244, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 19, '2026-08-13 01:34:39', '2026-08-13 01:34:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(245, NULL, NULL, 'proxy_api_key', '08e168a92f60a29651390248', 'App\Models\Service', 19, '2026-08-13 01:34:39', '2026-08-13 01:34:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(246, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:4c09::1:10000, 2a01:4f8:1001:610b::2:10001', 'App\Models\Service', 19, '2026-08-13 01:34:39', '2026-08-13 01:34:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(247, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 19, '2026-08-13 01:34:39', '2026-08-13 01:34:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(248, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 19, '2026-08-13 01:34:39', '2026-08-13 01:34:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(249, NULL, NULL, 'proxy_synced_at', '2026-08-13 01:34:39', 'App\Models\Service', 19, '2026-08-13 01:34:39', '2026-08-13 01:34:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(252, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(255, NULL, NULL, 'proxy_username', 'svc20', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(256, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(257, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(258, NULL, NULL, 'proxy_api_key', 'd051e660fd34a5302b2dbc9a', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(259, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:02c7::1:10000, 2a01:4f8:1000:8d83::2:10001, 2a01:4f8:1000:7d75::3:10002', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(260, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(261, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(262, NULL, NULL, 'proxy_synced_at', '2026-08-13 01:35:11', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(266, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 20, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(269, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 21, '2026-08-13 01:35:11', '2026-08-13 01:35:11');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(272, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(273, NULL, NULL, 'proxy_username', 'svc21', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(274, NULL, NULL, 'proxy_password', '9cc0b51a', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(275, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(276, NULL, NULL, 'proxy_api_key', '12f42b268620f3242c1a917c', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(277, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:12e0::1:10000, 2a01:4f8:1001:bf7b::2:10001', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(278, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(279, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(280, NULL, NULL, 'proxy_synced_at', '2026-08-13 01:35:12', 'App\Models\Service', 21, '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(283, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(286, NULL, NULL, 'proxy_username', 'svc22', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(287, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(288, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(289, NULL, NULL, 'proxy_api_key', '0d20c67fe4fa9d393e6bab11', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(290, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:509d::1:10000, 2a01:4f8:1000:2fa6::2:10001, 2a01:4f8:1000:d6cb::3:10002', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(291, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(292, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(293, NULL, NULL, 'proxy_synced_at', '2026-08-13 18:23:39', 'App\Models\Service', 22, '2026-08-13 18:23:38', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(297, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 22, '2026-08-13 18:23:39', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(300, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 23, '2026-08-13 18:23:39', '2026-08-13 18:23:39');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(303, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(304, NULL, NULL, 'proxy_username', 'svc23', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(305, NULL, NULL, 'proxy_password', 'c3ceaf06', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(306, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(307, NULL, NULL, 'proxy_api_key', '747a687c8ea5c79029d61f02', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(308, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:8612::1:10000, 2a01:4f8:1001:e3d5::2:10001', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(309, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(310, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(311, NULL, NULL, 'proxy_synced_at', '2026-08-13 18:23:40', 'App\Models\Service', 23, '2026-08-13 18:23:40', '2026-08-13 18:23:40');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(314, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 24, '2026-08-13 20:25:34', '2026-08-13 20:25:34');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(316, NULL, NULL, 'proxypanel_service_id', '1000', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(317, NULL, NULL, 'proxy_username', 'svc24', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(318, NULL, NULL, 'proxy_password', 'a7d3ea00', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(319, NULL, NULL, 'proxy_amount', '0', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(320, NULL, NULL, 'proxy_api_key', 'fa8f7d5ea31d2971434cfa45', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(321, NULL, NULL, 'proxy_ips', '', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(322, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 24, '2026-08-13 20:25:36', '2026-08-13 20:25:36');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(323, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 24, '2026-08-13 20:25:37', '2026-08-13 20:25:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(324, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:25:37', 'App\Models\Service', 24, '2026-08-13 20:25:37', '2026-08-13 20:25:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(325, NULL, NULL, 'proxy_confirmed_at', '2026-08-13 20:25:37', 'App\Models\Service', 24, '2026-08-13 20:25:37', '2026-08-13 20:25:37');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(326, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(328, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(329, NULL, NULL, 'proxy_username', 'svc25', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(330, NULL, NULL, 'proxy_password', 'd82483f1', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(331, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(332, NULL, NULL, 'proxy_api_key', 'ae1f7142f81ff5444bdb3a0f', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(333, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:9044::1:10000, 2a01:4f8:1001:e116::2:10001', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(334, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(335, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(336, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:25:38', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(337, NULL, NULL, 'proxy_confirmed_at', '2026-08-13 20:25:38', 'App\Models\Service', 25, '2026-08-13 20:25:38', '2026-08-13 20:25:38');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(338, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(340, NULL, NULL, 'proxypanel_service_id', '1000', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(341, NULL, NULL, 'proxy_username', 'svc26', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(342, NULL, NULL, 'proxy_password', '225421c2', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(343, NULL, NULL, 'proxy_amount', '0', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(344, NULL, NULL, 'proxy_api_key', '4ed8b46daccade3059d3bcec', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(345, NULL, NULL, 'proxy_ips', '', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(346, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(347, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:01');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(348, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:29:02', 'App\Models\Service', 26, '2026-08-13 20:29:01', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(349, NULL, NULL, 'proxy_confirmed_at', '2026-08-13 20:29:02', 'App\Models\Service', 26, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(350, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(352, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(353, NULL, NULL, 'proxy_username', 'svc27', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(354, NULL, NULL, 'proxy_password', 'e16668b1', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(355, NULL, NULL, 'proxy_amount', '2', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(356, NULL, NULL, 'proxy_api_key', '4188dbb89be6c23facf19a06', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(357, NULL, NULL, 'proxy_ips', '2a01:4f8:1001:86d5::1:10000, 2a01:4f8:1001:1ca9::2:10001', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(358, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(359, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:02');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(360, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:29:03', 'App\Models\Service', 27, '2026-08-13 20:29:02', '2026-08-13 20:29:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(361, NULL, NULL, 'proxy_confirmed_at', '2026-08-13 20:29:03', 'App\Models\Service', 27, '2026-08-13 20:29:03', '2026-08-13 20:29:03');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(362, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:20');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(365, NULL, NULL, 'proxy_username', 'svc28', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:20');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(366, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(367, NULL, NULL, 'proxy_amount', '0', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(368, NULL, NULL, 'proxy_api_key', '79d0839d91bc8ee4ae17b684', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:20');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(369, NULL, NULL, 'proxy_ips', '', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:20');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(370, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(371, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:20');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(372, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:29:21', 'App\Models\Service', 28, '2026-08-13 20:29:20', '2026-08-13 20:29:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(376, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 28, '2026-08-13 20:29:21', '2026-08-13 20:29:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(379, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 29, '2026-08-13 20:29:21', '2026-08-13 20:29:21');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(382, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(383, NULL, NULL, 'proxy_username', 'svc29', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(384, NULL, NULL, 'proxy_password', 'c7c3a6a3', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(385, NULL, NULL, 'proxy_amount', '0', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(386, NULL, NULL, 'proxy_api_key', '0e5cbbc28b3a2600790ae0e5', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(387, NULL, NULL, 'proxy_ips', '', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(388, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(389, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(390, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:29:22', 'App\Models\Service', 29, '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(393, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 30, '2026-08-13 20:31:30', '2026-08-13 20:31:30');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(396, NULL, NULL, 'proxy_username', 'svc30', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(397, NULL, NULL, 'proxy_password', 'NewPass123', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(398, NULL, NULL, 'proxy_amount', '3', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(399, NULL, NULL, 'proxy_api_key', 'bcaf9fa1117465165f6099b5', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(400, NULL, NULL, 'proxy_ips', '2a01:4f8:1000:2ce7::1:10000, 2a01:4f8:1000:6e24::2:10001, 2a01:4f8:1000:2ab6::3:10002', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(401, NULL, NULL, 'proxy_rotation_counter', '1', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(402, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(403, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:31:31', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(404, NULL, NULL, 'proxy_confirmed_at', '2026-08-13 20:31:31', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(408, NULL, NULL, 'proxy_auth_ips', '203.0.113.10, 198.51.100.7', 'App\Models\Service', 30, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(411, NULL, NULL, 'Region', 'us-nyc', 'App\Models\Service', 31, '2026-08-13 20:31:31', '2026-08-13 20:31:31');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(414, NULL, NULL, 'proxypanel_service_id', '1001', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(415, NULL, NULL, 'proxy_username', 'svc31', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(416, NULL, NULL, 'proxy_password', '614f0b2e', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(417, NULL, NULL, 'proxy_amount', '0', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(418, NULL, NULL, 'proxy_api_key', '49e4cfc51002d1ec75070f61', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(419, NULL, NULL, 'proxy_ips', '', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(420, NULL, NULL, 'proxy_rotation_counter', '0', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(421, NULL, NULL, 'proxy_max_rotate', '10', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');
INSERT INTO `properties` (`id`, `custom_property_id`, `name`, `key`, `value`, `model_type`, `model_id`, `created_at`, `updated_at`) VALUES
(422, NULL, NULL, 'proxy_synced_at', '2026-08-13 20:31:32', 'App\Models\Service', 31, '2026-08-13 20:31:32', '2026-08-13 20:31:32');

--
-- provisioning_operations (16 rows)
--
DELETE FROM `provisioning_operations`;
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'ProxyPanel', 'create', 'failed', 2, 'no capacity in region', '{"payload":{"id":"1001","status":"error","description":"no capacity in region"},"via":"callback"}', NULL, '2026-08-12 20:45:19', '2026-08-12 13:54:53', '2026-08-12 20:45:19');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(2, 2, 'ProxyPanel', 'callback', 'failed', 1, 'Unrecognised callback state: "wibble"', '{"payload":{"service_id":2,"status":"wibble"}}', NULL, '2026-08-12 13:55:31', '2026-08-12 13:55:31', '2026-08-12 13:55:31');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(3, 3, 'ProxyPanel', 'create', 'failed', 1, 'ProxyPanel API error (HTTP 500): Mock panel is in forced-failure mode', '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', NULL, '2026-08-12 14:00:43', '2026-08-12 14:00:43', '2026-08-12 14:00:43');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(4, 5, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-12 14:19:03', '2026-08-12 14:19:03', '2026-08-12 14:19:03', '2026-08-12 14:19:03');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(5, 7, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-12 14:19:05', '2026-08-12 14:19:05', '2026-08-12 14:19:05', '2026-08-12 14:19:05');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(6, 9, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-12 20:44:14', '2026-08-12 20:44:14', '2026-08-12 20:44:13', '2026-08-12 20:44:14');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(7, 11, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-12 20:44:46', '2026-08-12 20:44:46', '2026-08-12 20:44:46', '2026-08-12 20:44:46');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(8, 13, 'ProxyPanel', 'create', 'failed', 2, 'no capacity in region', '{"payload":{"id":"1001","status":"error","description":"no capacity in region"},"via":"callback"}', NULL, '2026-08-12 20:46:34', '2026-08-12 20:44:59', '2026-08-12 20:46:34');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(9, 15, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-12 20:46:49', '2026-08-12 20:46:49', '2026-08-12 20:46:49', '2026-08-12 20:46:49');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(10, 17, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-13 01:09:23', '2026-08-13 01:09:23', '2026-08-13 01:09:22', '2026-08-13 01:09:23');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(11, 19, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-13 01:34:39', '2026-08-13 01:34:39', '2026-08-13 01:34:38', '2026-08-13 01:34:39');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(12, 21, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-13 01:35:12', '2026-08-13 01:35:12', '2026-08-13 01:35:12', '2026-08-13 01:35:12');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(13, 23, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-13 18:23:40', '2026-08-13 18:23:40', '2026-08-13 18:23:39', '2026-08-13 18:23:40');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(14, 28, 'ProxyPanel', 'upgrade', 'failed', 1, 'ProxyPanel: Cannot shrink below one proxy', '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', NULL, '2026-08-13 20:29:21', '2026-08-13 20:29:21', '2026-08-13 20:29:21');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(15, 29, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-13 20:29:22', '2026-08-13 20:29:22', '2026-08-13 20:29:22', '2026-08-13 20:29:22');
INSERT INTO `provisioning_operations` (`id`, `service_id`, `extension`, `action`, `status`, `attempts`, `error`, `context`, `resolved_at`, `last_attempt_at`, `created_at`, `updated_at`) VALUES
(16, 31, 'ProxyPanel', 'create', 'succeeded', 1, NULL, '{"api_url":"http:\/\/127.0.0.1:9000\/v0\/services"}', '2026-08-13 20:31:32', '2026-08-13 20:31:32', '2026-08-13 20:31:32', '2026-08-13 20:31:32');

--
-- roles (1 rows)
--
DELETE FROM `roles`;
INSERT INTO `roles` (`id`, `name`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', '["*"]', '2026-08-11 18:43:31', '2026-08-11 18:43:31');

--
-- services (31 rows)
--
DELETE FROM `services`;
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(1, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, NULL, NULL, '2026-08-12 13:54:50', '2026-08-12 13:58:29', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(2, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, NULL, NULL, '2026-08-12 13:54:52', '2026-08-12 20:45:19', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(3, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, NULL, NULL, '2026-08-12 14:00:43', '2026-08-12 14:00:43', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(4, 'pending', NULL, 1, 1, 'USD', 1, 10, NULL, NULL, NULL, NULL, '2026-08-12 14:19:02', '2026-08-12 14:19:02', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(5, 'pending', NULL, 1, 1, 'USD', 1, 10, NULL, NULL, NULL, NULL, '2026-08-12 14:19:02', '2026-08-12 14:19:03', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(6, 'pending', NULL, 1, 1, 'USD', 1, 10, NULL, NULL, NULL, NULL, '2026-08-12 14:19:04', '2026-08-12 14:19:04', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(7, 'pending', NULL, 1, 1, 'USD', 1, 10, NULL, NULL, NULL, NULL, '2026-08-12 14:19:04', '2026-08-12 14:19:05', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(8, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-12 20:44:13', NULL, '2026-08-12 20:44:09', '2026-08-12 20:44:13', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(9, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-12 20:44:13', NULL, '2026-08-12 20:44:13', '2026-08-12 20:44:13', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(10, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-12 20:44:45', NULL, '2026-08-12 20:44:45', '2026-08-12 20:44:45', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(11, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-12 20:44:46', NULL, '2026-08-12 20:44:46', '2026-08-12 20:44:46', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(12, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-12 20:44:58', NULL, '2026-08-12 20:44:57', '2026-08-12 20:44:58', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(13, 'active', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-12 20:44:58', NULL, '2026-08-12 20:44:58', '2026-08-13 01:04:44', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(14, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-12 20:46:48', NULL, '2026-08-12 20:46:48', '2026-08-12 20:46:48', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(15, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-12 20:46:49', NULL, '2026-08-12 20:46:49', '2026-08-12 20:46:49', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(16, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-13 01:09:22', NULL, '2026-08-13 01:09:21', '2026-08-13 01:09:22', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(17, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 01:09:22', NULL, '2026-08-13 01:09:22', '2026-08-13 01:09:22', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(18, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-13 01:34:38', NULL, '2026-08-13 01:34:37', '2026-08-13 01:34:38', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(19, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 01:34:38', NULL, '2026-08-13 01:34:38', '2026-08-13 01:34:38', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(20, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-13 01:35:11', NULL, '2026-08-13 01:35:11', '2026-08-13 01:35:11', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(21, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 01:35:11', NULL, '2026-08-13 01:35:11', '2026-08-13 01:35:12', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(22, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-13 18:23:39', NULL, '2026-08-13 18:23:38', '2026-08-13 18:23:39', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(23, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 18:23:39', NULL, '2026-08-13 18:23:39', '2026-08-13 18:23:39', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(24, 'active', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 20:25:37', NULL, '2026-08-13 20:25:34', '2026-08-13 20:25:37', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(25, 'active', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 20:25:38', NULL, '2026-08-13 20:25:38', '2026-08-13 20:25:38', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(26, 'active', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 20:29:01', NULL, '2026-08-13 20:29:01', '2026-08-13 20:29:02', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(27, 'active', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 20:29:02', NULL, '2026-08-13 20:29:02', '2026-08-13 20:29:03', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(28, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-13 20:29:21', NULL, '2026-08-13 20:29:20', '2026-08-13 20:29:21', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(29, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 20:29:21', NULL, '2026-08-13 20:29:21', '2026-08-13 20:29:22', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(30, 'active', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-10-13 20:31:31', NULL, '2026-08-13 20:31:30', '2026-08-13 20:31:31', NULL, NULL);
INSERT INTO `services` (`id`, `status`, `order_id`, `product_id`, `user_id`, `currency_code`, `quantity`, `price`, `plan_id`, `coupon_id`, `expires_at`, `subscription_id`, `created_at`, `updated_at`, `billing_agreement_id`, `label`) VALUES
(31, 'pending', NULL, 1, 1, 'USD', 1, 10, 1, NULL, '2026-09-13 20:31:31', NULL, '2026-08-13 20:31:31', '2026-08-13 20:31:32', NULL, NULL);

--
-- settings (100 rows)
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
(72, 'api_url', 'http://127.0.0.1:9000/v0/services', 'string', 0, 'App\Models\Server', 4, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(73, 'api_token', 'test-token', 'string', 0, 'App\Models\Server', 4, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(74, 'callback_secret', 'callback-secret-123', 'string', 0, 'App\Models\Server', 4, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(75, 'amount', '2', 'string', 0, 'App\Models\Product', 1, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(76, 'plan', 'rot-100', 'string', 0, 'App\Models\Product', 1, '2026-08-12 13:54:50', '2026-08-13 01:34:37');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(77, 'region', 'eu-west', 'string', 0, 'App\Models\Product', 1, '2026-08-12 13:54:50', '2026-08-12 13:54:50');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(78, 'merchant_id', 'M1', 'string', 0, 'App\Models\Gateway', 1, '2026-08-12 14:04:01', '2026-08-12 14:04:01');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(79, 'public_key', 'pk', 'string', 0, 'App\Models\Gateway', 1, '2026-08-12 14:04:01', '2026-08-12 14:04:01');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(80, 'private_key', 'sk', 'string', 0, 'App\Models\Gateway', 1, '2026-08-12 14:04:01', '2026-08-12 14:04:01');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(81, 'ipn_secret', 'ipn', 'string', 0, 'App\Models\Gateway', 1, '2026-08-12 14:04:01', '2026-08-12 14:04:01');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(82, 'receive_currency', 'LTCT', 'string', 0, 'App\Models\Gateway', 1, '2026-08-12 14:04:01', '2026-08-12 14:04:01');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(83, 'test_mode', '1', 'string', 0, 'App\Models\Gateway', 1, '2026-08-12 14:04:02', '2026-08-12 14:04:02');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(84, 'api_key', 'ak', 'string', 0, 'App\Models\Gateway', 2, '2026-08-12 14:04:02', '2026-08-12 14:04:02');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(85, 'api_secret', 'as', 'string', 0, 'App\Models\Gateway', 2, '2026-08-12 14:04:02', '2026-08-12 14:04:02');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(86, 'pay_currency', 'USDT', 'string', 0, 'App\Models\Gateway', 2, '2026-08-12 14:04:02', '2026-08-12 14:04:02');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(87, 'test_mode', '1', 'string', 0, 'App\Models\Gateway', 2, '2026-08-12 14:04:02', '2026-08-12 14:04:02');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(88, 'suspend_strategy', 'expire', 'string', 0, 'App\Models\Server', 4, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(89, 'regions', 'us-nyc|United States - New York
eu-ams|Netherlands - Amsterdam', 'string', 0, 'App\Models\Server', 4, '2026-08-12 20:44:09', '2026-08-12 20:44:09');
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

--
-- ticket_messages (1 rows)
--
DELETE FROM `ticket_messages`;
INSERT INTO `ticket_messages` (`id`, `ticket_id`, `user_id`, `message`, `created_at`, `updated_at`, `ticket_mail_log_id`) VALUES
(1, 1, 1, 'My proxies stopped working this morning.', '2026-08-12 14:08:44', '2026-08-12 14:08:44', NULL);

--
-- tickets (1 rows)
--
DELETE FROM `tickets`;
INSERT INTO `tickets` (`id`, `subject`, `status`, `priority`, `department`, `user_id`, `assigned_to`, `service_id`, `created_at`, `updated_at`) VALUES
(1, 'Proxy not responding', 'open', 'high', 'Support', 1, NULL, NULL, '2026-08-12 14:08:44', '2026-08-12 14:08:44');

--
-- users (2 rows)
--
DELETE FROM `users`;
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `role_id`, `email_verified_at`, `password`, `tfa_secret`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Local', 'admin@local.test', 1, '2026-08-11 18:49:54', '$2y$10$afh1O8bD75OgxYPVwvekJuMtHV/0LW5tMNQ0xIzLOZ/BrBMhKEGeG', NULL, '2026-08-11 18:43:47', '2026-08-11 18:49:54');
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `role_id`, `email_verified_at`, `password`, `tfa_secret`, `created_at`, `updated_at`) VALUES
(2, 'Mal', 'Ory', 'intruder@local.test', NULL, '2026-08-13 01:07:49', '$2y$04$8FnRTjtLkT2NUTwAIyNXg.ZVamIlF3wanPhvflyssblycOv8ToppW', NULL, '2026-08-13 01:07:49', '2026-08-13 01:07:49');

SET FOREIGN_KEY_CHECKS=1;
