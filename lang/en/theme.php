<?php

/*
|--------------------------------------------------------------------------
| Proxy theme wording
|--------------------------------------------------------------------------
|
| Every piece of text the `proxy` theme adds on top of Paymenter's own wording
| lives here. Edit the right-hand side — nothing else — to reword the client area.
| Paymenter's built-in wording lives in the other files in this folder
| (auth.php, invoices.php, services.php, ticket.php, product.php, …).
|
| To translate the client area, copy this file to lang/<locale>/theme.php.
|
*/

return [
    // Breadcrumbs / navigation
    'portal_home' => 'Portal Home',
    'lost_password' => 'Lost Password Reset',

    // Menu bar — the reference portal's wording (WHMCS defaults)
    'hello' => 'Hello, :name!',
    'my_services' => 'My Services',
    'order_new_services' => 'Order New Services',
    'billing' => 'Billing',
    'my_invoices' => 'My Invoices',
    'payment_methods' => 'Payment Methods',
    'support' => 'Support',
    'my_tickets' => 'My Support Tickets',
    'open_ticket' => 'Open Ticket',
    'view_cart' => 'View Cart',
    'notifications' => 'Notifications',

    // Client area — the reference portal's own wording
    'client_area' => 'Client Area',
    'services_short' => 'Services',
    'tickets_short' => 'Tickets',
    'invoices_short' => 'Invoices',
    'active_products_services' => 'Your Active Products/Services',
    'overdue_invoices' => 'Overdue Invoices',
    'recent_tickets' => 'Recent Support Tickets',
    'open_new_ticket' => 'Open New Ticket',
    'pay_now' => 'Pay Now',
    'view_more' => 'View More…',
    'no_services_yet' => 'It appears you do not have any products/services with us yet. Place an order to get started.',
    'no_recent_tickets' => 'No Recent Tickets Found. If you need any help, please open a ticket.',

    // My Products & Services — status filter rail
    'view' => 'View',
    'status_active' => 'Active',
    'status_pending' => 'Pending',
    'status_suspended' => 'Suspended',
    'status_terminated' => 'Terminated',
    'status_cancelled' => 'Cancelled',
    'place_new_order' => 'Place a New Order',

    // Store
    'categories' => 'Categories',
    'actions' => 'Actions',
    'starting_from' => 'Starting from',
    'order_now' => 'Order Now',
    'browse_all' => 'Browse All',

    // Login
    'restricted' => 'This page is restricted',
    'remember_me' => 'Remember Me',
    'email_address' => 'Email Address',
    'need_account' => 'Need an account?',

    // Registration — section headings
    'personal_information' => 'Personal Information',
    'billing_address' => 'Billing Address',
    'account_security' => 'Account Security',

    // Registration — field labels
    'phone_number' => 'Phone Number',
    'company_name' => 'Company Name',
    'street_address' => 'Street Address',
    'street_address_2' => 'Street Address 2',
    'city' => 'City',
    'state_region' => 'State/Region',
    'postcode' => 'Postcode',
    'country' => 'Country',
    'select_country' => 'Select a country',
    'optional' => '(Optional)',

    // Brazilian tax fields (Others/BrazilianRegistration)
    'cnpj' => 'CNPJ',
    'trade_name' => 'Nome Fantasia',
    'trade_name_hint' => '(Trade Name)',

    // Wording core does not ship. These were written as `__('product.coupon') ?? 'Coupon'`,
    // which never falls back — `__()` returns the key itself when a translation is missing,
    // and a non-null string ignores `??`. The pages showed customers raw keys such as
    // "product.coupon". Defined here so the theme owns them.
    'cart_title' => 'Cart',
    'each' => 'each',
    'coupon' => 'Coupon',
    'no_products' => 'No products in this category.',
    'configure' => 'Configure',
    'select_plan' => 'Select a plan',
    'ticket_word' => 'Ticket',
    'conversation' => 'Conversation',
];
