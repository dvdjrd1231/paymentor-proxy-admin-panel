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
    'downloads' => 'Downloads',
    'network_status' => 'Network Status',
    'by_month' => 'By Month',
    'older_announcements' => 'Older Announcements',
    'view_rss_feed' => 'View RSS Feed',
    'news' => 'News',
    'news_subtitle' => 'All the latest updates',
    'add_funds_help' => 'Add funds to your account with us to avoid lots of small transactions and to automatically take care of any new invoices that are generated.',
    'deposit_money_in_advance' => 'Deposit money in advance',
    'minimum_deposit' => 'Minimum Deposit',
    'maximum_deposit' => 'Maximum Deposit',
    'maximum_balance' => 'Maximum Balance',
    'amount_to_add' => 'Amount to Add',
    'deposits_non_refundable' => 'All deposits are non-refundable.',

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

    // Billing cycle as the reference portal words it — an adverb ("Monthly"), not the
    // noun Paymenter uses in services.billing_cycles ("month"). Anything that isn't a
    // period of one falls back to 'every'.
    'cycle' => [
        'day' => 'Daily',
        'week' => 'Weekly',
        'month' => 'Monthly',
        'year' => 'Annually',
        'one_time' => 'One Time',
        'every' => 'Every :period :unit',
    ],

    // Cart / checkout — the reference portal's wording
    'review_checkout' => 'Review & Checkout',
    'product_options' => 'Product/Options',
    'price_cycle' => 'Price/Cycle',
    'empty_cart_button' => 'Empty Cart',
    'apply_promo_code' => 'Apply Promo Code',
    'promo_placeholder' => 'Enter promo code if you have one',
    'validate_code' => 'Validate Code',
    'totals' => 'Totals',
    'total_due_today' => 'Total Due Today',
    'continue_shopping' => 'Continue Shopping',
    'checkout_intro' => 'Please enter your personal details and billing information to checkout.',
    'choose_account' => 'Choose Account',
    'payment_details' => 'Payment Details',
    'complete_order' => 'Complete Order',
    'order_confirmation' => 'Order Confirmation',

    // My Products & Services — table toolbar
    'showing_entries' => 'Showing :from to :to of :total entries',
    'search' => 'Search',

    // Dashboard knowledgebase search band
    'kb_search_placeholder' => 'Enter a question here to search our knowledgebase for answers…',

    // Manage Product
    'manage_product' => 'Manage Product',
    'overview' => 'Overview',
    'information' => 'Information',
    'registration_date' => 'Registration Date',
    'first_payment_amount' => 'First Payment Amount',
    'billing_cycle' => 'Billing Cycle',
    'next_due_date' => 'Next Due Date',
    'payment_method' => 'Payment Method',

    // Register — side rail
    'already_registered' => 'Already Registered?',
    'already_registered_help' => 'Already registered with us? If so, click the button below to login to our client area from where you can manage your account.',
    'generate_password' => 'Generate Password',
    'password_strength' => 'Password Strength',
    'password_strength_enter' => 'Enter a password',
    'password_weak' => 'Weak',
    'password_moderate' => 'Moderate',
    'password_strong' => 'Strong',
    'password_show' => 'Show',
    'password_hide' => 'Hide',
    'terms_of_service' => 'Terms of Service',
    'additional_information' => 'Additional Information',
    'required_fields_marked' => '(required fields are marked with *)',

    // Login
    'restricted' => 'This page is restricted',
    'reset_password_subtitle' => 'Enter your email address to request a password reset',
    'reset_password_choose' => 'Choose a new password for your account',
    'remember_me' => 'Remember Me',
    'email_address' => 'Email Address',
    'enter_email' => 'Enter email',
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
    // Notifications panel
    'notif_unpaid' => '{1} You have 1 unpaid invoice. Pay it early for peace of mind.|[2,*] You have :count unpaid invoices. Pay them early for peace of mind.',
    'notif_overdue' => '{1} You have 1 overdue invoice with a total balance due of :amount. Pay it now to avoid any interruptions in service.|[2,*] You have :count overdue invoices with a total balance due of :amount. Pay them now to avoid any interruptions in service.',
    'notif_credit' => 'You have a credit balance of :amount.',
    'notif_none' => 'Nothing needs your attention right now.',

    // Affiliates — before activation
    'activate_affiliate' => 'Activate Affiliate Account',
    'affiliate_pitch' => 'Get Paid for Referring Customers to Us',
    'affiliate_pitch_sub' => 'Activate your affiliate account and start earning money today...',
    'affiliate_point_commission' => 'We pay commissions for every signup that comes via your custom signup link.',
    'affiliate_point_cookie' => 'We track the visitors you refer to us using cookies, so users you refer don\'t have to purchase instantly for you to receive your commission. Cookies last for up to :days days following the initial visit.',
    'affiliate_point_more' => 'If you would like to find out more, please',

    // Open Ticket
    'name' => 'Name',
    'ticket_intro' => 'If you can\'t find a solution to your problems in our knowledgebase, you can submit a ticket by selecting the appropriate department below.',

    // Signed-in account menu, in the reference portal's wording
    'account_details' => 'Account Details',
    'your_profile' => 'Your Profile',
    'change_password' => 'Change Password',
    'security_settings' => 'Security Settings',
    'tickets' => 'Tickets',

    // Cart — guest checkout choice
    'checkout_needs_account' => 'Your order is placed against an account, so you will need one to continue. Your cart is kept either way.',
    'create_account_continue' => 'Create Account & Continue',
    'already_have_account' => 'Already registered? Sign in',

    'configure' => 'Configure',
    'continue' => 'Continue',
    'not_selected' => 'Not selected',
    'sales_help' => 'Have questions? Contact our sales team for assistance.',
    'click_here' => 'Click here',
    'configure_intro' => 'Configure your desired options and continue to checkout.',
    'select_plan' => 'Select a plan',
    'choose_billing_cycle' => 'Choose Billing Cycle',
    'configurable_options' => 'Configurable Options',
    'ticket_word' => 'Ticket',
    'conversation' => 'Conversation',
    'no_transactions' => 'No transactions yet.',
    'no_announcements' => 'There are no announcements at this time.',
    'captcha_recheck' => 'Please confirm the CAPTCHA again before resubmitting.',
    'captcha_protected' => 'Protected by reCAPTCHA.',
];
