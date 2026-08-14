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

    // Login
    'restricted' => 'This page is restricted',
    'remember_me' => 'Remember me',
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
];
