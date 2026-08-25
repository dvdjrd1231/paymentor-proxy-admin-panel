-- Paymenter bootstrap export (clean).
-- Catalogue and configuration only: no customers, no services, no invoices,
-- no credentials. Generated from the live database on 2026-08-25.
--
-- Import AFTER `php artisan migrate --seed`:
--   mysql -u <user> -p <database> < paymenter-clean.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

--
-- categories (5 rows)
--
DELETE FROM `categories`;
INSERT INTO `categories` (`id`, `slug`, `name`, `description`, `image`, `parent_id`, `full_slug`, `sort`, `created_at`, `updated_at`) VALUES
(1, 'vps-hosting', 'VPS Hosting', '<blockquote><p style=\"text-align: center;\">vps</p></blockquote>', NULL, NULL, 'vps-hosting', NULL, '2026-07-24 20:13:31', '2026-07-24 20:13:31'),
(5, 'ipv6-proxy-monthly-plans', 'IPv6 Proxy Monthly Plans', 'Monthly subscription renewable IPv6 residential proxy plans.', NULL, NULL, 'ipv6-proxy-monthly-plans', NULL, '2026-08-15 15:25:14', '2026-08-21 11:24:23'),
(6, 'ipv6-proxy-weekly-plans', 'IPv6 Proxy Weekly Plans', 'One time payment IPv6 residential proxy plans for seven days.', NULL, NULL, 'ipv6-proxy-weekly-plans', NULL, '2026-08-21 11:21:54', '2026-08-21 11:21:54'),
(7, 'ipv6-proxy-daily-plans', 'IPv6 Proxy Daily Plans', 'One time payment IPv6 residential proxy plans for one day.', NULL, NULL, 'ipv6-proxy-daily-plans', NULL, '2026-08-21 11:21:54', '2026-08-21 11:21:54'),
(8, 'ipv4-proxy-monthly-plans', 'IPv4 Proxy Monthly Plans', 'Dedicated IPv4 residential proxy plans billed monthly.', NULL, NULL, 'ipv4-proxy-monthly-plans', NULL, '2026-08-21 11:21:54', '2026-08-21 11:21:54');

--
-- currencies (2 rows)
--
DELETE FROM `currencies`;
INSERT INTO `currencies` (`code`, `name`, `prefix`, `suffix`, `format`) VALUES
('BRL', 'Brazilian Real', 'R$', '', '1.000,00'),
('USD', 'US Dollar', '$', '', '1,000.00');

--
-- custom_properties (15 rows)
--
DELETE FROM `custom_properties`;
INSERT INTO `custom_properties` (`id`, `name`, `description`, `key`, `type`, `model`, `validation`, `allowed_values`, `non_editable`, `required`, `show_on_invoice`) VALUES
(1, 'CPF', NULL, 'cpf', 'string', 'App\\Models\\User', 'cpf', NULL, 0, 0, 0),
(2, 'RG', NULL, 'rg', 'string', 'App\\Models\\User', 'max:20', NULL, 0, 0, 0),
(3, 'Trade Name / Nome Fantasia', NULL, 'trade_name', 'string', 'App\\Models\\User', 'max:191', NULL, 0, 0, 0),
(4, 'CNPJ', NULL, 'cnpj', 'string', 'App\\Models\\User', 'cnpj', NULL, 0, 0, 1),
(5, 'State Registration / Inscrição Estadual', NULL, 'state_registration', 'string', 'App\\Models\\User', 'max:30', NULL, 0, 0, 0),
(6, 'State Registration Exempt / Isento de IE', NULL, 'state_registration_exempt', 'checkbox', 'App\\Models\\User', NULL, NULL, 0, 0, 0),
(7, 'Telegram Chat ID', NULL, 'telegram_chat_id', 'string', 'App\\Models\\User', 'nullable|string|max:64', NULL, 0, 0, 0),
(8, 'Phone', NULL, 'phone', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 1, 0),
(9, 'Company Name', NULL, 'company_name', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 0, 1),
(10, 'Address', NULL, 'address', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 1, 1),
(11, 'Address 2', NULL, 'address2', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 0, 0),
(12, 'City', NULL, 'city', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 1, 1),
(13, 'State', NULL, 'state', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 1, 1),
(14, 'ZIP', NULL, 'zip', 'string', 'App\\Models\\User', 'string|max:255', NULL, 0, 1, 1),
(15, 'Country', NULL, 'country', 'select', 'App\\Models\\User', 'string|max:255', '[\"Afghanistan\",\"Aland Islands\",\"Albania\",\"Algeria\",\"American Samoa\",\"Andorra\",\"Angola\",\"Anguilla\",\"Antarctica\",\"Antigua And Barbuda\",\"Argentina\",\"Armenia\",\"Aruba\",\"Australia\",\"Austria\",\"Azerbaijan\",\"Bahamas\",\"Bahrain\",\"Bangladesh\",\"Barbados\",\"Belarus\",\"Belgium\",\"Belize\",\"Benin\",\"Bermuda\",\"Bhutan\",\"Bolivia\",\"Bosnia And Herzegovina\",\"Botswana\",\"Brazil\",\"British Indian Ocean Territory\",\"Brunei Darussalam\",\"Bulgaria\",\"Burkina Faso\",\"Burundi\",\"Cambodia\",\"Cameroon\",\"Canada\",\"Canary Islands\",\"Cape Verde\",\"Cayman Islands\",\"Central African Republic\",\"Chad\",\"Chile\",\"China\",\"Christmas Island\",\"Cocos (Keeling) Islands\",\"Colombia\",\"Comoros\",\"Congo\",\"Congo, Democratic Republic\",\"Cook Islands\",\"Costa Rica\",\"Cote D\'Ivoire\",\"Croatia\",\"Cuba\",\"Curacao\",\"Cyprus\",\"Czech Republic\",\"Denmark\",\"Djibouti\",\"Dominica\",\"Dominican Republic\",\"Ecuador\",\"Egypt\",\"El Salvador\",\"Equatorial Guinea\",\"Eritrea\",\"Estonia\",\"Ethiopia\",\"Falkland Islands (Malvinas)\",\"Faroe Islands\",\"Fiji\",\"Finland\",\"France\",\"French Guiana\",\"French Polynesia\",\"French Southern Territories\",\"Gabon\",\"Gambia\",\"Georgia\",\"Germany\",\"Ghana\",\"Gibraltar\",\"Greece\",\"Greenland\",\"Grenada\",\"Guadeloupe\",\"Guam\",\"Guatemala\",\"Guernsey\",\"Guinea\",\"Guinea-Bissau\",\"Guyana\",\"Haiti\",\"Heard Island & Mcdonald Islands\",\"Holy See (Vatican City State)\",\"Honduras\",\"Hong Kong\",\"Hungary\",\"Iceland\",\"India\",\"Indonesia\",\"Iran, Islamic Republic Of\",\"Iraq\",\"Ireland\",\"Isle Of Man\",\"Israel\",\"Italy\",\"Jamaica\",\"Japan\",\"Jersey\",\"Jordan\",\"Kazakhstan\",\"Kenya\",\"Kiribati\",\"Korea\",\"Kosovo\",\"Kuwait\",\"Kyrgyzstan\",\"Lao People\'s Democratic Republic\",\"Latvia\",\"Lebanon\",\"Lesotho\",\"Liberia\",\"Libyan Arab Jamahiriya\",\"Liechtenstein\",\"Lithuania\",\"Luxembourg\",\"Macao\",\"Macedonia\",\"Madagascar\",\"Malawi\",\"Malaysia\",\"Maldives\",\"Mali\",\"Malta\",\"Marshall Islands\",\"Martinique\",\"Mauritania\",\"Mauritius\",\"Mayotte\",\"Mexico\",\"Micronesia, Federated States Of\",\"Moldova\",\"Monaco\",\"Mongolia\",\"Montenegro\",\"Montserrat\",\"Morocco\",\"Mozambique\",\"Myanmar\",\"Namibia\",\"Nauru\",\"Nepal\",\"Netherlands\",\"Netherlands Antilles\",\"New Caledonia\",\"New Zealand\",\"Nicaragua\",\"Niger\",\"Nigeria\",\"Niue\",\"Norfolk Island\",\"Northern Mariana Islands\",\"Norway\",\"Oman\",\"Pakistan\",\"Palau\",\"Palestine, State of\",\"Panama\",\"Papua New Guinea\",\"Paraguay\",\"Peru\",\"Philippines\",\"Pitcairn\",\"Poland\",\"Portugal\",\"Puerto Rico\",\"Qatar\",\"Reunion\",\"Romania\",\"Russian Federation\",\"Rwanda\",\"Saint Barthelemy\",\"Saint Helena\",\"Saint Kitts And Nevis\",\"Saint Lucia\",\"Saint Martin\",\"Saint Pierre And Miquelon\",\"Saint Vincent And Grenadines\",\"Samoa\",\"San Marino\",\"Sao Tome And Principe\",\"Saudi Arabia\",\"Senegal\",\"Serbia\",\"Seychelles\",\"Sierra Leone\",\"Singapore\",\"Slovakia\",\"Slovenia\",\"Solomon Islands\",\"Somalia\",\"South Africa\",\"South Georgia And Sandwich Isl.\",\"Spain\",\"Sri Lanka\",\"Sudan\",\"South Sudan\",\"Suriname\",\"Svalbard And Jan Mayen\",\"Swaziland\",\"Sweden\",\"Switzerland\",\"Syrian Arab Republic\",\"Taiwan\",\"Tajikistan\",\"Tanzania\",\"Thailand\",\"Timor-Leste\",\"Togo\",\"Tokelau\",\"Tonga\",\"Trinidad And Tobago\",\"Tunisia\",\"Turkey\",\"Turkmenistan\",\"Turks And Caicos Islands\",\"Tuvalu\",\"Uganda\",\"Ukraine\",\"United Arab Emirates\",\"United Kingdom\",\"United States\",\"United States Outlying Islands\",\"Uruguay\",\"Uzbekistan\",\"Vanuatu\",\"Venezuela\",\"Viet Nam\",\"Virgin Islands, British\",\"Virgin Islands, U.S.\",\"Wallis And Futuna\",\"Western Sahara\",\"Yemen\",\"Zambia\",\"Zimbabwe\"]', 0, 1, 1);

--
-- roles (1 rows)
--
DELETE FROM `roles`;
INSERT INTO `roles` (`id`, `name`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', '[\"*\"]', '2026-07-18 17:46:59', '2026-07-18 17:46:59');

--
-- notification_templates (12 rows)
--
DELETE FROM `notification_templates`;
INSERT INTO `notification_templates` (`id`, `key`, `subject`, `in_app_title`, `enabled`, `mail_enabled`, `in_app_enabled`, `body`, `in_app_body`, `edit_preference_message`, `in_app_url`, `cc`, `bcc`, `created_at`, `updated_at`) VALUES
(1, 'new_login_detected', 'New login detected', 'New login detected', 1, 'force', 'choice_off', '# New login detected  \n            \nA new login was detected on your account.\n            \n- IP: {{ $ip }}  \n- Device: {{ $device }}\n- Time: {{ $time }}\n\n**If this was you**  \nYou can ignore this message, there is no need to take any action.\n            \n**If this wasn\'t you**  \nPlease reset your password [here]({{ route(\'password.request\') }}).', 'A new login was detected on your account from IP: {{ $ip }} using {{ $device }} at {{ $time }}.', 'Alert me about new login attempts', '{{ route(\"profile.security\") }}', NULL, NULL, NULL, NULL),
(2, 'new_invoice_created', 'New invoice created', 'New invoice created', 1, 'choice_on', 'choice_on', '# New invoice created  \n            \nA new invoice was created on your account.\n            \nTotal amount: **{{ $total }}**\n            \n            \n<div class=\"table\">  \n            \n|   Item   | Quantity |  Price   |  \n| :------: | :------: | :------: |\n@foreach ($items as $item)\n| {{ $item->description }} | {{ $item->quantity }} | {{ $item->price }} |\n@endforeach\n</div>\n            \n<div class=\"action\">\n	<a class=\"button button-blue\" href=\"{{ route(\'invoices.show\', $invoice) }}\">\n		Go to invoice\n	</a>\n</div>\n            \n@if($has_subscription)\nYou have a active subscription, the invoice will be automatically paid.\n@endif', 'A new invoice was created on your account with total amount: {{ $total }}.', 'Notify me about new invoices', '{{ route(\"invoices.show\", $invoice) }}', NULL, NULL, NULL, NULL),
(3, 'invoice_paid', 'Invoice paid', 'Invoice paid', 1, 'choice_on', 'choice_on', '# Invoice paid  \n            \nYour invoice has been successfully paid.\n            \nTotal amount: **{{ $invoice->formattedTotal }}**\n            \nYou can view your invoice details by clicking the button below.\n            \n<div class=\"action\">\n	<a class=\"button button-blue\" href=\"{{ route(\'invoices.show\', $invoice) }}\">\n		View Invoice\n	</a>\n</div>', 'Your invoice #{{ $invoice->id }} has been successfully paid with total amount: {{ $invoice->formattedTotal }}.', 'Notify me about successful payments', '{{ route(\"invoices.show\", $invoice) }}', NULL, NULL, NULL, NULL),
(4, 'invoice_payment_failed', 'Invoice payment failed', 'Invoice payment failed', 1, 'choice_on', 'choice_on', '# Invoice payment failed  \n\nYour invoice payment has failed.\n\nTotal amount: **{{ $invoice->formattedTotal }}**\n            \nPlease pay the invoice to avoid service interruptions.\n            \n<div class=\"action\">\n	<a class=\"button button-blue\" href=\"{{ route(\'invoices.show\', $invoice) }}\">\n		Pay Invoice\n	</a>\n</div>', 'Your invoice #{{ $invoice->id }} payment has failed. Please pay the invoice to avoid service interruptions.', 'Alert me about payment failures', '{{ route(\"invoices.show\", $invoice) }}', NULL, NULL, NULL, NULL),
(5, 'new_order_created', 'New order created', 'New order created', 1, 'choice_on', 'choice_on', '# New order created\n\nA new order was created on your account.\n\n**Order details**\n<div class=\"table\">  \n            \n|   Item   | Quantity |  Price   |  \n| :------: | :------: | :------: |\n@foreach ($items as $item)\n| {{ $item->product->name }} | {{ $item->quantity }} | {{ $item->formattedPrice }} |\n@endforeach\n</div>', 'A new order was created on your account.', 'Send me order confirmations', '{{ route(\"services\") }}', NULL, NULL, NULL, NULL),
(6, 'new_server_created', 'Service activated', 'Service activated', 1, 'force', 'choice_on', '# Service activated\n\nYour service has been activated.\n\n**Service details**\n- Name: {{ $service->product->name }}\n\n@isset($service->product->email_template)\n**Service information**  \n{!! Str::markdown(Illuminate\\View\\Compilers\\BladeCompiler::render($service->product->email_template, get_defined_vars()[\'__data\'])) !!}\n@endisset', 'Your service {{ $service->product->name }} has been activated.', 'Notify me about new service activations', '{{ route(\"services.show\", $service) }}', NULL, NULL, NULL, NULL),
(7, 'server_suspended', 'Service suspended', 'Service suspended', 1, 'force', 'choice_on', '# Service suspended\n\nYour service has been suspended due to a payment failure.\n\n**Service details**\n- Name: {{ $service->product->name }}\n\nPlease pay the invoice to reactivate the service.', 'Your service {{ $service->product->name }} has been suspended due to a payment failure. Please pay the invoice to reactivate the service.', 'Alert me about service suspensions', '{{ route(\"services.show\", $service) }}', NULL, NULL, NULL, NULL),
(8, 'server_terminated', 'Service terminated', 'Server terminated', 1, 'force', 'choice_on', '# Service terminated\n\nYour service has been terminated.\n\n**Service details**\n- Name: {{ $service->product->name }}\n\nDo you consider it a mistake?\n<div class=\"action\">\n	<a class=\"button button-blue\" href=\"{{ route(\'tickets.create\') }}\">\n		Contact us\n	</a>\n</div>', 'Your server {{ $service->product->name }} has been terminated.', 'Alert me about service terminations', '{{ route(\"services.show\", $service) }}', NULL, NULL, NULL, NULL),
(9, 'new_ticket_message', '[Ticket #{{ $ticketMessage->ticket_id }}] New reply', 'New ticket reply', 1, 'choice_on', 'choice_on', '# New ticket reply\n\n{{ $ticketMessage->user->name }} replied to your ticket.\n\n**Message**\n{!! Str::markdown($ticketMessage->message, [\n    \'html_input\' => \'strip\',\n    \'allow_unsafe_links\' => false,\n]) !!}', 'You have a new reply on your ticket #{{ $ticketMessage->ticket_id }}.', 'Notify me about ticket replies', '{{ route(\"tickets.show\", $ticketMessage->ticket_id) }}', NULL, NULL, NULL, NULL),
(10, 'email_verification', 'Email verification', NULL, 1, 'force', 'never', '# Email verification\nPlease verify your email address by clicking the link below.\n<div class=\"action\">\n    <a class=\"button button-blue\" href=\"{{ $url }}\">\n        Verify email\n    </a>\n</div>\nThis link will expire in 60 minutes.\nIf you did not create an account, you can ignore this email.', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'password_reset', 'Password reset', NULL, 1, 'force', 'never', '# Password reset\nYou are receiving this email because we received a password reset request for your account.\n\n**Reset password**\n<div class=\"action\">\n	<a class=\"button button-blue\" href=\"{{ $url }}\">\n		Reset password\n	</a>\n</div>\n\nThis password reset link will expire in 60 minutes.\n\nIf you did not request a password reset, no further action is required.', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'service_cancellation_received', 'Service cancellation received', 'Service cancellation received', 1, 'choice_on', 'choice_on', '# Server Cancellation Received\n\nWe\'re sorry to see you go! Your server cancellation has been successfully received.\n\n**Cancellation Details**\n- Server: {{ $service->product->name }}\n@if($cancellation->reason)\n- Reason: {{ $cancellation->reason }}\n@endif\n- Requested at: {{ $cancellation->created_at->format(\'F j, Y, g:i A\') }}\n\n@if($cancellation->type === \'end_of_period\')\nYour server will remain active until {{ $service->expires_at->format(\'F j, Y\') }} (end of your current billing period).\n@else\nYour server has been terminated immediately.\n@endif\n', 'Your server cancellation has been successfully received.', 'Notify me about service cancellations', '{{ route(\"services.show\", $service) }}', NULL, NULL, NULL, NULL);

--
-- extensions (21 rows)
--
DELETE FROM `extensions`;
INSERT INTO `extensions` (`id`, `name`, `extension`, `type`, `enabled`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'BrazilianRegistration', 'BrazilianRegistration', 'other', 1, '2026-07-23 15:12:18', '2026-07-23 15:12:26', NULL),
(2, 'PaymentFees', 'PaymentFees', 'other', 1, '2026-07-27 16:28:44', '2026-07-27 16:29:14', NULL),
(3, 'GatewayRules', 'GatewayRules', 'other', 1, '2026-07-27 16:28:57', '2026-07-27 16:29:03', NULL),
(4, 'DiscordNotifications', 'DiscordNotifications', 'other', 0, '2026-07-27 16:29:30', '2026-07-27 16:29:30', NULL),
(5, 'Notifications', 'Notifications', 'other', 1, '2026-07-27 16:29:51', '2026-08-14 23:51:38', NULL),
(6, 'Stripe', 'Stripe', 'gateway', 1, '2026-07-28 12:16:01', '2026-07-28 16:40:35', NULL),
(7, 'proxyPanel', 'ProxyPanel', 'server', 1, '2026-08-13 18:12:04', '2026-08-13 18:12:04', NULL),
(8, 'CoinPayments', 'CoinPayments', 'gateway', 1, '2026-08-14 18:31:56', '2026-08-14 18:31:56', NULL),
(9, 'Cryptomus', 'Cryptomus', 'gateway', 1, '2026-08-14 19:40:46', '2026-08-14 20:11:42', NULL),
(10, 'Binance Pay', 'Binance', 'gateway', 1, '2026-08-14 21:37:20', '2026-08-14 21:37:20', NULL),
(11, 'Ticket Tools', 'TicketTools', 'other', 1, '2026-08-14 23:51:38', '2026-08-14 23:51:38', NULL),
(12, 'Provisioning Ops', 'ProvisioningOps', 'other', 1, '2026-08-15 15:14:48', '2026-08-15 15:14:48', NULL),
(13, 'Admin Ops', 'AdminOps', 'other', 1, '2026-08-16 04:26:50', '2026-08-16 04:26:50', NULL),
(14, 'Currency Rates', 'CurrencyRates', 'other', 1, '2026-08-17 14:06:06', '2026-08-17 14:06:06', NULL),
(15, 'Portal Behavior', 'PortalBehavior', 'other', 1, '2026-08-19 18:13:06', '2026-08-19 18:13:06', NULL),
(16, 'Local Dev Overrides', 'LocalDevOverrides', 'other', 1, '2026-08-19 19:56:10', '2026-08-19 19:56:10', NULL),
(17, 'Announcements', 'Announcements', 'other', 1, '2026-08-19 21:00:18', '2026-08-19 21:00:18', NULL),
(18, 'Affiliates', 'Affiliates', 'other', 1, '2026-08-19 21:00:18', '2026-08-19 21:00:18', NULL),
(19, 'Knowledgebase', 'Knowledgebase', 'other', 1, '2026-08-19 21:16:07', '2026-08-19 21:16:07', NULL),
(20, 'Site Pages', 'SitePages', 'other', 1, '2026-08-19 23:47:10', '2026-08-19 23:47:10', NULL),
(21, 'Client Tools', 'ClientTools', 'other', 1, '2026-08-21 11:18:43', '2026-08-21 11:18:43', NULL);

--
-- gateway_rules (0 rows)
--
DELETE FROM `gateway_rules`;

--
-- payment_fee_rules (4 rows)
--
DELETE FROM `payment_fee_rules`;
INSERT INTO `payment_fee_rules` (`id`, `name`, `gateway`, `fee_type`, `fixed_amount`, `percent_amount`, `country`, `currency_code`, `product_id`, `customer_type`, `min_amount`, `max_amount`, `priority`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Stripe processing cost', 'Stripe', 'both', '0.30', '2.9000', NULL, NULL, NULL, NULL, NULL, NULL, 100, 0, '2026-08-16 14:43:45', '2026-08-17 19:03:37'),
(2, 'CoinPayments processing cost', 'CoinPayments', 'percent', '0.00', '0.5000', NULL, NULL, NULL, NULL, NULL, NULL, 100, 0, '2026-08-16 14:43:45', '2026-08-16 14:43:45'),
(3, 'Cryptomus processing cost', 'Cryptomus', 'percent', '0.00', '0.4000', NULL, NULL, NULL, NULL, NULL, NULL, 100, 0, '2026-08-16 14:43:45', '2026-08-16 14:43:45'),
(4, 'Binance processing cost', 'Binance Pay', 'percent', '0.00', '0.0000', NULL, NULL, NULL, NULL, NULL, NULL, 100, 0, '2026-08-16 14:43:45', '2026-08-16 14:43:45');

--
-- products (33 rows)
--
DELETE FROM `products`;
INSERT INTO `products` (`id`, `category_id`, `name`, `image`, `slug`, `description`, `stock`, `per_user_limit`, `sort`, `allow_quantity`, `server_id`, `email_template`, `created_at`, `updated_at`, `hidden`) VALUES
(11, 5, 'IPv6 Residential Amethyst - HTTP Proxy - M', NULL, 'ipv6-residential-amethyst-http-proxy-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">1.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(12, 5, 'IPv6 Residential Amethyst - Socks5h - M', NULL, 'ipv6-residential-amethyst-socks5h-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">1.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(13, 5, 'IPv6 Residential Emerald - HTTP Proxy - M', NULL, 'ipv6-residential-emerald-http-proxy-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">4.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 7 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(14, 5, 'IPv6 Residential Emerald - Socks5h - M', NULL, 'ipv6-residential-emerald-socks5h-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">4.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 7 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(15, 5, 'IPv6 Residential Jade - HTTP Proxy - M', NULL, 'ipv6-residential-jade-http-proxy-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">13.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 10 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(16, 5, 'IPv6 Residential Jade - Socks5h - M', NULL, 'ipv6-residential-jade-socks5h-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">13.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 10 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(17, 5, 'IPv6 Residential Onyx - HTTP Proxy - M', NULL, 'ipv6-residential-onyx-http-proxy-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">22.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 15 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(18, 5, 'IPv6 Residential Onyx - Socks5h - M', NULL, 'ipv6-residential-onyx-socks5h-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">22.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 15 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(19, 5, 'IPv6 Residential Ruby - HTTP Proxy - M', NULL, 'ipv6-residential-ruby-http-proxy-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">31.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 20 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(20, 5, 'IPv6 Residential Ruby - Socks5h - M', NULL, 'ipv6-residential-ruby-socks5h-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">31.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 20 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-16 10:30:14', '2026-08-21 12:47:59', 0),
(21, 6, 'IPv6 Residential Amethyst - HTTP Proxy - W', NULL, 'ipv6-residential-amethyst-http-proxy-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">1.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:47:59', 0),
(22, 6, 'IPv6 Residential Amethyst - Socks5h - W', NULL, 'ipv6-residential-amethyst-socks5h-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">1.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:47:59', 0),
(23, 6, 'IPv6 Residential Emerald - HTTP Proxy - W', NULL, 'ipv6-residential-emerald-http-proxy-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">4.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 7 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(24, 6, 'IPv6 Residential Emerald - Socks5h - W', NULL, 'ipv6-residential-emerald-socks5h-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">4.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 7 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(25, 6, 'IPv6 Residential Jade - HTTP Proxy - W', NULL, 'ipv6-residential-jade-http-proxy-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">13.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 10 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(26, 6, 'IPv6 Residential Jade - Socks5h - W', NULL, 'ipv6-residential-jade-socks5h-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">13.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 10 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(27, 6, 'IPv6 Residential Onyx - HTTP Proxy - W', NULL, 'ipv6-residential-onyx-http-proxy-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">22.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 15 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(28, 6, 'IPv6 Residential Onyx - Socks5h - W', NULL, 'ipv6-residential-onyx-socks5h-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">22.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 15 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(29, 6, 'IPv6 Residential Ruby - HTTP Proxy - W', NULL, 'ipv6-residential-ruby-http-proxy-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">31.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 20 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(30, 6, 'IPv6 Residential Ruby - Socks5h - W', NULL, 'ipv6-residential-ruby-socks5h-w', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">31.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 20 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(31, 7, 'IPv6 Residential Amethyst - HTTP Proxy - D', NULL, 'ipv6-residential-amethyst-http-proxy-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">1.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(32, 7, 'IPv6 Residential Amethyst - Socks5h - D', NULL, 'ipv6-residential-amethyst-socks5h-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">1.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(33, 7, 'IPv6 Residential Emerald - HTTP Proxy - D', NULL, 'ipv6-residential-emerald-http-proxy-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">4.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 7 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(34, 7, 'IPv6 Residential Emerald - Socks5h - D', NULL, 'ipv6-residential-emerald-socks5h-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">4.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 7 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(35, 7, 'IPv6 Residential Jade - HTTP Proxy - D', NULL, 'ipv6-residential-jade-http-proxy-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">13.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 10 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(36, 7, 'IPv6 Residential Jade - Socks5h - D', NULL, 'ipv6-residential-jade-socks5h-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">13.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 10 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(37, 7, 'IPv6 Residential Onyx - HTTP Proxy - D', NULL, 'ipv6-residential-onyx-http-proxy-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">22.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 15 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(38, 7, 'IPv6 Residential Onyx - Socks5h - D', NULL, 'ipv6-residential-onyx-socks5h-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">22.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 15 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(39, 7, 'IPv6 Residential Ruby - HTTP Proxy - D', NULL, 'ipv6-residential-ruby-http-proxy-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">31.500 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 20 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(40, 7, 'IPv6 Residential Ruby - Socks5h - D', NULL, 'ipv6-residential-ruby-socks5h-d', '<ul><li class=\"f-shield\">Anonymous Residential IPv6 Proxy</li><li class=\"f-ports\">31.500 Socks5h Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 20 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(41, 8, 'IPv4 Residential /26 (Dedicated) - M', NULL, 'ipv4-residential-26-dedicated-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv4 Proxy</li><li class=\"f-ports\">64 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(42, 8, 'IPv4 Residential /25 (Dedicated) - M', NULL, 'ipv4-residential-25-dedicated-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv4 Proxy</li><li class=\"f-ports\">128 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0),
(43, 8, 'IPv4 Residential /24 (Dedicated) - M', NULL, 'ipv4-residential-24-dedicated-m', '<ul><li class=\"f-shield\">Anonymous Residential IPv4 Proxy</li><li class=\"f-ports\">256 HTTP Proxy Ports</li><li class=\"f-server\">Private Proxy Server</li><li class=\"f-sync\">Rotating Proxies or Static Proxies</li><li class=\"f-check\">IP Whitelist Authentication</li><li class=\"f-check\">User/Password Authentication</li><li class=\"f-check\">Up-To 5 IP whitelist</li><li class=\"f-history\">Configurable IP Proxies rotation time</li></ul>', NULL, NULL, NULL, 'combined', 7, NULL, '2026-08-21 11:23:16', '2026-08-21 12:48:00', 0);

--
-- plans (33 rows)
--
DELETE FROM `plans`;
INSERT INTO `plans` (`id`, `name`, `priceable_type`, `priceable_id`, `type`, `billing_period`, `billing_unit`, `sort`) VALUES
(14, 'Monthly', 'App\\Models\\Product', 11, 'recurring', 1, 'month', 0),
(15, 'Monthly', 'App\\Models\\Product', 12, 'recurring', 1, 'month', 0),
(16, 'Monthly', 'App\\Models\\Product', 13, 'recurring', 1, 'month', 0),
(17, 'Monthly', 'App\\Models\\Product', 14, 'recurring', 1, 'month', 0),
(18, 'Monthly', 'App\\Models\\Product', 15, 'recurring', 1, 'month', 0),
(19, 'Monthly', 'App\\Models\\Product', 16, 'recurring', 1, 'month', 0),
(20, 'Monthly', 'App\\Models\\Product', 17, 'recurring', 1, 'month', 0),
(21, 'Monthly', 'App\\Models\\Product', 18, 'recurring', 1, 'month', 0),
(22, 'Monthly', 'App\\Models\\Product', 19, 'recurring', 1, 'month', 0),
(23, 'Monthly', 'App\\Models\\Product', 20, 'recurring', 1, 'month', 0),
(24, 'Week', 'App\\Models\\Product', 21, 'one-time', 1, 'week', 0),
(25, 'Week', 'App\\Models\\Product', 22, 'one-time', 1, 'week', 0),
(26, 'Week', 'App\\Models\\Product', 23, 'one-time', 1, 'week', 0),
(27, 'Week', 'App\\Models\\Product', 24, 'one-time', 1, 'week', 0),
(28, 'Week', 'App\\Models\\Product', 25, 'one-time', 1, 'week', 0),
(29, 'Week', 'App\\Models\\Product', 26, 'one-time', 1, 'week', 0),
(30, 'Week', 'App\\Models\\Product', 27, 'one-time', 1, 'week', 0),
(31, 'Week', 'App\\Models\\Product', 28, 'one-time', 1, 'week', 0),
(32, 'Week', 'App\\Models\\Product', 29, 'one-time', 1, 'week', 0),
(33, 'Week', 'App\\Models\\Product', 30, 'one-time', 1, 'week', 0),
(34, 'Day', 'App\\Models\\Product', 31, 'one-time', 1, 'day', 0),
(35, 'Day', 'App\\Models\\Product', 32, 'one-time', 1, 'day', 0),
(36, 'Day', 'App\\Models\\Product', 33, 'one-time', 1, 'day', 0),
(37, 'Day', 'App\\Models\\Product', 34, 'one-time', 1, 'day', 0),
(38, 'Day', 'App\\Models\\Product', 35, 'one-time', 1, 'day', 0),
(39, 'Day', 'App\\Models\\Product', 36, 'one-time', 1, 'day', 0),
(40, 'Day', 'App\\Models\\Product', 37, 'one-time', 1, 'day', 0),
(41, 'Day', 'App\\Models\\Product', 38, 'one-time', 1, 'day', 0),
(42, 'Day', 'App\\Models\\Product', 39, 'one-time', 1, 'day', 0),
(43, 'Day', 'App\\Models\\Product', 40, 'one-time', 1, 'day', 0),
(44, 'Monthly', 'App\\Models\\Product', 41, 'recurring', 1, 'month', 0),
(45, 'Monthly', 'App\\Models\\Product', 42, 'recurring', 1, 'month', 0),
(46, 'Monthly', 'App\\Models\\Product', 43, 'recurring', 1, 'month', 0);

--
-- prices (66 rows)
--
DELETE FROM `prices`;
INSERT INTO `prices` (`id`, `price`, `setup_fee`, `currency_code`, `plan_id`) VALUES
(22, '70.00', '0.00', 'USD', 14),
(23, '140.00', '0.00', 'USD', 15),
(24, '120.00', '0.00', 'USD', 16),
(25, '240.00', '0.00', 'USD', 17),
(26, '350.00', '0.00', 'USD', 18),
(27, '700.00', '0.00', 'USD', 19),
(28, '580.00', '0.00', 'USD', 20),
(29, '1160.00', '0.00', 'USD', 21),
(30, '800.00', '0.00', 'USD', 22),
(31, '1600.00', '0.00', 'USD', 23),
(35, '367.75', '0.00', 'BRL', 14),
(36, '735.50', '0.00', 'BRL', 15),
(37, '630.43', '0.00', 'BRL', 16),
(38, '1260.86', '0.00', 'BRL', 17),
(39, '1838.75', '0.00', 'BRL', 18),
(40, '3677.51', '0.00', 'BRL', 19),
(41, '3047.08', '0.00', 'BRL', 20),
(42, '6094.16', '0.00', 'BRL', 21),
(43, '4202.87', '0.00', 'BRL', 22),
(44, '8405.74', '0.00', 'BRL', 23),
(45, '28.00', '0.00', 'USD', 24),
(46, '56.00', '0.00', 'USD', 25),
(47, '35.00', '0.00', 'USD', 26),
(48, '70.00', '0.00', 'USD', 27),
(49, '91.00', '0.00', 'USD', 28),
(50, '182.00', '0.00', 'USD', 29),
(51, '154.00', '0.00', 'USD', 30),
(52, '308.00', '0.00', 'USD', 31),
(53, '196.00', '0.00', 'USD', 32),
(54, '392.00', '0.00', 'USD', 33),
(55, '4.00', '0.00', 'USD', 34),
(56, '8.00', '0.00', 'USD', 35),
(57, '5.00', '0.00', 'USD', 36),
(58, '10.00', '0.00', 'USD', 37),
(59, '13.00', '0.00', 'USD', 38),
(60, '26.00', '0.00', 'USD', 39),
(61, '22.00', '0.00', 'USD', 40),
(62, '44.00', '0.00', 'USD', 41),
(63, '28.00', '0.00', 'USD', 42),
(64, '56.00', '0.00', 'USD', 43),
(65, '190.00', '0.00', 'USD', 44),
(66, '350.00', '0.00', 'USD', 45),
(67, '640.00', '0.00', 'USD', 46),
(68, '147.10', '0.00', 'BRL', 24),
(69, '294.20', '0.00', 'BRL', 25),
(70, '183.88', '0.00', 'BRL', 26),
(71, '367.75', '0.00', 'BRL', 27),
(72, '478.08', '0.00', 'BRL', 28),
(73, '956.15', '0.00', 'BRL', 29),
(74, '809.05', '0.00', 'BRL', 30),
(75, '1618.10', '0.00', 'BRL', 31),
(76, '1029.70', '0.00', 'BRL', 32),
(77, '2059.41', '0.00', 'BRL', 33),
(78, '21.01', '0.00', 'BRL', 34),
(79, '42.03', '0.00', 'BRL', 35),
(80, '26.27', '0.00', 'BRL', 36),
(81, '52.54', '0.00', 'BRL', 37),
(82, '68.30', '0.00', 'BRL', 38),
(83, '136.59', '0.00', 'BRL', 39),
(84, '115.58', '0.00', 'BRL', 40),
(85, '231.16', '0.00', 'BRL', 41),
(86, '147.10', '0.00', 'BRL', 42),
(87, '294.20', '0.00', 'BRL', 43),
(88, '998.18', '0.00', 'BRL', 44),
(89, '1838.75', '0.00', 'BRL', 45),
(90, '3362.29', '0.00', 'BRL', 46);

--
-- settings (107 rows)
--
DELETE FROM `settings`;
INSERT INTO `settings` (`id`, `key`, `value`, `type`, `encrypted`, `settingable_type`, `settingable_id`, `created_at`, `updated_at`) VALUES
(1, 'invoice_number', '213', 'string', 0, NULL, NULL, '2026-07-18 17:46:56', '2026-08-24 20:16:23'),
(2, 'company_name', 'Paymenter', 'string', 0, NULL, NULL, '2026-08-18 12:06:37', '2026-08-18 12:06:37'),
(3, 'timezone', 'UTC', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(4, 'app_language', 'en', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(5, 'allowed_languages', '[\"en\",\"pt\"]', 'array', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-21 19:12:47'),
(6, 'app_url', 'https://paymenter-dev.7hoop.net', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-14 19:19:01'),
(7, 'captcha', 'recaptcha-v2', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-19 21:46:20'),
(8, 'session_validation', 'none', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(9, 'oauth_google', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(10, 'oauth_github', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(11, 'oauth_discord', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(12, 'tax_enabled', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(13, 'tax_type', 'inclusive', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(14, 'mail_disable', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(15, 'mail_must_verify', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(16, 'mail_encryption', '', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-16 14:59:50'),
(17, 'mail_header', '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\">\n<head>\n<title>{{ config(\'app.name\') }}</title>\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" />\n<meta name=\"color-scheme\" content=\"light\">\n<meta name=\"supported-color-schemes\" content=\"light\">\n<style>\n@media only screen and (max-width: 600px) {\n.inner-body {\nwidth: 100% !important;\n}\n\n.footer {\nwidth: 100% !important;\n}\n}\n\n@media only screen and (max-width: 500px) {\n.button {\nwidth: 100% !important;\n}\n}\n{!! config(\'settings.mail_css\') !!}\n</style>\n</head>\n<body>\n\n<table class=\"wrapper\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n<tr>\n<td align=\"center\">\n<table class=\"content\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n@if(config(\'settings.logo\'))    \n<tr>\n<td class=\"header\">\n<a href=\"{{ url(\'/\') }}\" style=\"display: inline-block;\">\n<img src=\"{{ url(Storage::url(config(\'settings.logo\'))) }}\" class=\"logo\" alt=\"{{ config(\'app.name\') }}\">\n</a>\n</td>\n</tr>\n@endif\n\n\n<!-- Email Body -->\n<tr>\n<td class=\"body\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border: hidden !important;\">\n<table class=\"inner-body\" align=\"center\" width=\"570\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n<!-- Body content -->\n<tr>\n<td class=\"content-cell\">', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(18, 'mail_footer', '<tr>\n<td>\n<table class=\"footer\" align=\"center\" width=\"570\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">\n<tr>\n<td class=\"content-cell\" align=\"center\">\n© {{ date(\'Y\') }} {{ config(\'app.name\') }}. {{ __(\'All rights reserved.\') }}\n</td>\n</tr>\n</table>\n</td>\n</tr>\n', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(19, 'mail_css', '/* Base */\n\nbody,\nbody *:not(html):not(style):not(br):not(tr):not(code) {\n    box-sizing: border-box;\n    font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif,\n        \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\';\n    position: relative;\n}\n\nbody {\n    -webkit-text-size-adjust: none;\n    background-color: #ffffff;\n    color: #718096;\n    height: 100%;\n    line-height: 1.4;\n    margin: 0;\n    padding: 0;\n    width: 100% !important;\n}\n\np,\nul,\nol,\nblockquote {\n    line-height: 1.4;\n    text-align: left;\n}\n\na {\n    color: #3869d4;\n}\n\na img {\n    border: none;\n}\n\n/* Typography */\n\nh1 {\n    color: #3d4852;\n    font-size: 18px;\n    font-weight: bold;\n    margin-top: 0;\n    text-align: left;\n}\n\nh2 {\n    font-size: 16px;\n    font-weight: bold;\n    margin-top: 0;\n    text-align: left;\n}\n\nh3 {\n    font-size: 14px;\n    font-weight: bold;\n    margin-top: 0;\n    text-align: left;\n}\n\np {\n    font-size: 16px;\n    line-height: 1.5em;\n    margin-top: 0;\n    text-align: left;\n}\n\np.sub {\n    font-size: 12px;\n}\n\nimg {\n    max-width: 100%;\n}\n\n/* Layout */\n\n.wrapper {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 100%;\n    background-color: #edf2f7;\n    margin: 0;\n    padding: 0;\n    width: 100%;\n}\n\n.content {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 100%;\n    margin: 0;\n    padding: 0;\n    width: 100%;\n}\n\n/* Header */\n\n.header {\n    padding: 25px 0;\n    text-align: center;\n}\n\n.header a {\n    color: #3d4852;\n    font-size: 19px;\n    font-weight: bold;\n    text-decoration: none;\n}\n\n/* Logo */\n\n.logo {\n    height: 75px;\n    max-height: 75px;\n}\n\n/* Body */\n\n.body {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 100%;\n    background-color: #edf2f7;\n    border-bottom: 1px solid #edf2f7;\n    border-top: 1px solid #edf2f7;\n    margin: 0;\n    padding: 0;\n    width: 100%;\n}\n\n.inner-body {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 570px;\n    background-color: #ffffff;\n    border-color: #e8e5ef;\n    border-radius: 2px;\n    border-width: 1px;\n    box-shadow: 0 2px 0 rgba(0, 0, 150, 0.025), 2px 4px 0 rgba(0, 0, 150, 0.015);\n    margin: 0 auto;\n    padding: 0;\n    width: 570px;\n}\n\n/* Subcopy */\n\n.subcopy {\n    border-top: 1px solid #e8e5ef;\n    margin-top: 25px;\n    padding-top: 25px;\n}\n\n.subcopy p {\n    font-size: 14px;\n}\n\n/* Footer */\n\n.footer {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 570px;\n    margin: 0 auto;\n    padding: 0;\n    text-align: center;\n    width: 570px;\n}\n\n.footer p {\n    color: #b0adc5;\n    font-size: 12px;\n    text-align: center;\n}\n\n.footer a {\n    color: #b0adc5;\n    text-decoration: underline;\n}\n\n/* Tables */\n\n.table table {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 100%;\n    margin: 30px auto;\n    width: 100%;\n}\n\n.table th {\n    border-bottom: 1px solid #edeff2;\n    margin: 0;\n    padding-bottom: 8px;\n}\n\n.table td {\n    color: #74787e;\n    font-size: 15px;\n    line-height: 18px;\n    margin: 0;\n    padding: 10px 0;\n}\n\n.content-cell {\n    max-width: 100vw;\n    padding: 32px;\n}\n\n/* Buttons */\n\n.action {\n    -premailer-cellpadding: 0;\n    -premailer-cellspacing: 0;\n    -premailer-width: 100%;\n    margin: 30px auto;\n    padding: 0;\n    text-align: center;\n    width: 100%;\n}\n\n.button {\n    -webkit-text-size-adjust: none;\n    border-radius: 4px;\n    color: #fff;\n    display: inline-block;\n    overflow: hidden;\n    text-decoration: none;\n}\n\n.button-blue,\n.button-primary {\n    background-color: #2d3748;\n    border-bottom: 8px solid #2d3748;\n    border-left: 18px solid #2d3748;\n    border-right: 18px solid #2d3748;\n    border-top: 8px solid #2d3748;\n}\n\n.button-green,\n.button-success {\n    background-color: #48bb78;\n    border-bottom: 8px solid #48bb78;\n    border-left: 18px solid #48bb78;\n    border-right: 18px solid #48bb78;\n    border-top: 8px solid #48bb78;\n}\n\n.button-red,\n.button-error {\n    background-color: #e53e3e;\n    border-bottom: 8px solid #e53e3e;\n    border-left: 18px solid #e53e3e;\n    border-right: 18px solid #e53e3e;\n    border-top: 8px solid #e53e3e;\n}\n\n/* Panels */\n\n.panel {\n    border-left: #2d3748 solid 4px;\n    margin: 21px 0;\n}\n\n.panel-content {\n    background-color: #edf2f7;\n    color: #718096;\n    padding: 16px;\n}\n\n.panel-content p {\n    color: #718096;\n}\n\n.panel-item {\n    padding: 0;\n}\n\n.panel-item p:last-of-type {\n    margin-bottom: 0;\n    padding-bottom: 0;\n}\n\n/* Utilities */\n\n.break-all {\n    word-break: break-all;\n}', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(20, 'tickets_disabled', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(21, 'ticket_departments', '[\"Support\",\"Sales Support\"]', 'array', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-21 17:43:26'),
(22, 'ticket_client_closing_disabled', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(23, 'ticket_mail_piping', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-17 02:05:13'),
(24, 'ticket_mail_port', '993', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-17 02:05:13'),
(25, 'cronjob_time', '00:00', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(26, 'cronjob_invoice', '7', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(27, 'cronjob_invoice_reminder', '3', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(28, 'cronjob_order_cancel', '7', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(29, 'cronjob_order_suspend', '2', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(30, 'cronjob_order_terminate', '14', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(31, 'cronjob_delete_email_logs', '90', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(32, 'cronjob_close_ticket', '7', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(33, 'credits_enabled', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-14 13:18:05'),
(34, 'credits_minimum_deposit', '5', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(35, 'credits_maximum_deposit', '999', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-14 15:40:33'),
(36, 'credits_maximum_credit', '9999', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-14 15:40:33'),
(37, 'credits_auto_use', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(38, 'credits_on_downgrade', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(39, 'theme', 'proxy', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-21 05:20:14'),
(40, 'theme_default_direct_checkout', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(41, 'theme_default_small_images', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(42, 'theme_default_show_category_description', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(43, 'theme_default_logo_display', 'logo-and-name', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(44, 'theme_default_home_page_text', 'Welcome to Paymenter!', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(45, 'theme_default_primary', 'hsl(229, 100%, 64%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(46, 'theme_default_secondary', 'hsl(237, 33%, 60%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(47, 'theme_default_neutral', 'hsl(220, 25%, 85%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(48, 'theme_default_base', 'hsl(0, 0%, 0%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(49, 'theme_default_muted', 'hsl(220, 0%, 53%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(50, 'theme_default_inverted', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(51, 'theme_default_background', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(52, 'theme_default_background-secondary', 'hsl(0, 0%, 97%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(53, 'theme_default_dark-primary', 'hsl(229, 100%, 64%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(54, 'theme_default_dark-secondary', 'hsl(237, 33%, 60%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(55, 'theme_default_dark-neutral', 'hsl(0, 0%, 17%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(56, 'theme_default_dark-base', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(57, 'theme_default_dark-muted', 'hsl(0, 0%, 40%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(58, 'theme_default_dark-inverted', 'hsl(220, 14%, 60%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(59, 'theme_default_dark-background', 'hsl(240, 18%, 9%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(60, 'theme_default_dark-background-secondary', 'hsl(240, 13%, 11%)', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(61, 'bill_to_text', 'Paymenter\nhttps://paymenter-dev.7hoop.net\nTrading address not yet configured', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-08-18 14:03:45'),
(62, 'invoice_number_padding', '1', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(63, 'invoice_number_format', 'INV-{number}', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(64, 'invoice_proforma', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(65, 'invoice_snapshot', '1', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(66, 'gravatar_default', 'wavatar', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(67, 'default_currency', 'USD', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(68, 'registration_disabled', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(69, 'pagination', '10', 'string', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(70, 'debug', '0', 'boolean', 0, NULL, NULL, '2026-07-18 17:46:59', '2026-07-18 17:46:59'),
(71, 'telemetry_uuid', '20de75de-e86a-437f-a516-79500f3e72e7', 'string', 0, NULL, NULL, '2026-07-18 17:47:01', '2026-07-18 17:47:01'),
(74, 'vapid_public_key', 'eyJpdiI6Im5FZW1LTUxiVCszNjBOd2NicVJwQ3c9PSIsInZhbHVlIjoiazhIeTNKUEx2N0tVYW9uQVM1eE4zZ2FRaHhZYW5SYk9CazdkMGNPNnRSZU5jcTI3Vk9UODdlNHd6dEtRSld1UkV6Q0Z6ajZ4RExFR01iSkRUZ0EyaENBTDhPd0hGZXBFY1cwUHpzdHZ5R2owMEJvaXVUWnBiN0RaRnFQdFFmWU0iLCJtYWMiOiJjMTVmNjgzMGMzNGFkMGVkZWZiYWNkMjUyNGUyZjZlOTAwN2FjOTYzNTg3MzJhODczMjljZTg3ZjFkODdkZjY5IiwidGFnIjoiIn0=', 'string', 1, NULL, NULL, '2026-07-20 15:45:22', '2026-07-20 15:45:22'),
(75, 'vapid_private_key', 'eyJpdiI6Im9EeDk1UFFuNzQxR25RVHZPNDhYdGc9PSIsInZhbHVlIjoiRjJQQlJMYWFaaThHallOUEZpR1V4dWJxaTNrMEVnYkpUUUhpcDZRT1FjMHNQcFE4TTA5RXo0V3JOQzU5UUY0SyIsIm1hYyI6Ijg1ZTAyZWNhNTBlMzhkNjJmOGJkOGJkZTdjNjlmZGJmYWNiMDg1MGRhYjEzMmI2MjAzY2I3OTY0NTllYjNhZjYiLCJ0YWciOiIifQ==', 'string', 1, NULL, NULL, '2026-07-20 15:45:22', '2026-07-20 15:45:22'),
(97, 'theme_proxy_direct_checkout', '0', 'boolean', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(98, 'theme_proxy_small_images', '0', 'boolean', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(99, 'theme_proxy_show_category_description', '1', 'boolean', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(100, 'theme_proxy_logo_display', 'logo-and-name', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(101, 'theme_proxy_home_page_text', 'Welcome to Paymenter!', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(102, 'theme_proxy_primary', 'hsl(229, 100%, 64%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(103, 'theme_proxy_secondary', 'hsl(237, 33%, 60%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(104, 'theme_proxy_neutral', 'hsl(220, 25%, 85%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(105, 'theme_proxy_base', 'hsl(0, 0%, 0%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(106, 'theme_proxy_muted', 'hsl(220, 0%, 53%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(107, 'theme_proxy_inverted', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(108, 'theme_proxy_background', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(109, 'theme_proxy_background-secondary', 'hsl(0, 0%, 97%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(110, 'theme_proxy_dark-primary', 'hsl(229, 100%, 64%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(111, 'theme_proxy_dark-secondary', 'hsl(237, 33%, 60%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(112, 'theme_proxy_dark-neutral', 'hsl(0, 0%, 17%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(113, 'theme_proxy_dark-base', 'hsl(100, 100%, 100%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(114, 'theme_proxy_dark-muted', 'hsl(0, 0%, 40%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(115, 'theme_proxy_dark-inverted', 'hsl(220, 14%, 60%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(116, 'theme_proxy_dark-background', 'hsl(240, 18%, 9%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(117, 'theme_proxy_dark-background-secondary', 'hsl(240, 13%, 11%)', 'string', 0, NULL, NULL, '2026-08-14 11:06:25', '2026-08-14 11:06:25'),
(162, 'trusted_proxies', '[\"*\"]', 'array', 0, NULL, NULL, '2026-08-14 19:14:43', '2026-08-14 19:16:18'),
(172, 'mail_from_address', 'noreply@paymenter-dev.7hoop.net', 'string', 0, NULL, NULL, '2026-08-15 13:02:01', '2026-08-15 13:02:01'),
(173, 'mail_from_name', 'Paymenter', 'string', 0, NULL, NULL, '2026-08-18 12:06:37', '2026-08-18 12:06:37'),
(327, 'mail_host', '172.18.0.1', 'string', 0, NULL, NULL, '2026-08-16 14:59:50', '2026-08-16 14:59:50'),
(328, 'mail_port', '25', 'string', 0, NULL, NULL, '2026-08-16 14:59:50', '2026-08-16 14:59:50'),
(329, 'mail_username', '', 'string', 0, NULL, NULL, '2026-08-16 14:59:50', '2026-08-16 14:59:50'),
(330, 'mail_password', '', 'string', 0, NULL, NULL, '2026-08-16 14:59:50', '2026-08-16 14:59:50'),
(331, 'ticket_mail_host', '172.18.0.1', 'string', 0, NULL, NULL, '2026-08-17 00:37:14', '2026-08-17 00:37:14'),
(332, 'ticket_mail_email', 'support@paymenter-dev.7hoop.net', 'string', 0, NULL, NULL, '2026-08-17 00:37:14', '2026-08-21 14:24:49'),
(333, 'ticket_mail_password', 'tjWbhzppgz4nK7bzey5mNbVc', 'string', 0, NULL, NULL, '2026-08-17 00:37:14', '2026-08-17 00:37:14'),
(340, 'system_email_address', 'admin@paymenter-dev.7hoop.net', 'string', 0, NULL, NULL, '2026-08-18 14:03:45', '2026-08-18 14:03:45'),
(344, 'captcha_site_key', '6Lc-zJAtAAAAAL-cbx57eYULmxQ30FXxAjQ-Nx0Y', 'string', 0, NULL, NULL, '2026-08-19 21:46:21', '2026-08-21 14:18:50'),
(345, 'captcha_secret', 'eyJpdiI6ImVMM2c0ZWhucmxGZG9UN0RWVXFDUkE9PSIsInZhbHVlIjoibFhabHZuVHQ5TDFrdUZ4dTBTb3NBdEZlSUF2QnpkbzB6YW1kSEh3NXNubG5aUTNsTEdLeDBPUE5GUTRlMTRYQSIsIm1hYyI6ImVkNThlZTA3NTNiNzZiNTUzNmJmODM3ZjgzYjUwM2RjYzRmOTFiZGMyMDJjNzU4MDc5NGQ1ZGZiZWM4OWE3MjUiLCJ0YWciOiIifQ==', 'string', 1, NULL, NULL, '2026-08-19 21:46:21', '2026-08-21 14:18:50');

SET FOREIGN_KEY_CHECKS=1;