<?php

/**
 * Wording for the Client Tools pages. Kept here (rather than inside the extension) to
 * match how Knowledgebase and Site Pages ship their strings, so all client-area wording
 * is editable from one directory.
 */
return [
    // ── Quotes ────────────────────────────────────────────────────────────────────
    'quotes' => 'My Quotes',
    'quotes_short' => 'Quotes',
    'quotes_subtitle' => 'Quotes issued to you',
    'quotes_empty' => 'There are no quotes to display',
    // Filled in by Others/Quotes. The keys exist whether or not it is installed, so a
    // half-installed store shows English rather than a translation key.
    'quotes_description' => 'Description',
    'quotes_qty' => 'Qty',
    'quotes_amount' => 'Amount',
    'quotes_total' => 'Total',
    'quotes_valid_until' => 'Valid until',
    'quotes_lapsed' => 'past its date — accept soon',
    'quotes_accept' => 'Accept quote',
    'quotes_decline' => 'Decline',
    'quotes_accept_confirm' => 'Accepting creates an invoice for this amount. Continue?',
    'quotes_decline_confirm' => 'Decline this quote? This cannot be undone.',
    'quotes_see_invoice' => 'View the invoice for this quote',

    // ── Mass Payment ──────────────────────────────────────────────────────────────
    'mass_payment' => 'Mass Payment',
    'mass_payment_subtitle' => 'Settle several invoices at once',
    'mass_nothing_due' => 'You have no unpaid invoices.',
    'mass_unpaid_invoices' => 'Unpaid Invoices',
    'mass_toggle_all' => 'Select / deselect all',
    'mass_selected_total' => 'Selected Total',
    'mass_credit_balance' => 'Your credit balance is :amount.',
    'mass_credit_note' => 'Credit is applied to the selected invoices oldest first. An invoice is only settled when your balance covers it in full; anything left over stays on your account.',
    'mass_pay_with_credit' => 'Apply Credit to Selected',
    'mass_none_selected' => 'Select at least one invoice first.',
    'mass_no_credit' => 'Your credit balance does not cover any of the selected invoices.',
    'mass_paid' => ':count invoice(s) paid from your credit balance.',
    'mass_partial' => ':count invoice(s) paid. Your remaining balance does not cover the next one.',

    // ── Contacts ──────────────────────────────────────────────────────────────────
    'contacts' => 'Contacts',
    'contacts_subtitle' => 'People listed on your account',
    'contacts_empty' => 'No Contacts Found',
    'contact_new' => 'New Contact',
    'contact_edit' => 'Edit Contact',
    'contact_save' => 'Save Contact',
    'contact_saved' => 'Contact saved.',
    'contact_deleted' => 'Contact deleted.',
    'contact_delete_confirm' => 'Delete this contact?',
    'contact_access' => 'Account Access',
    'contact_is_sub_account' => 'Allow this contact to sign in as a sub-account',
    'contact_sub_account' => 'Sub-Account',

    'perm_invoices' => 'View & pay invoices',
    'perm_services' => 'View services',
    'perm_tickets' => 'Open & manage tickets',
    'perm_account' => 'Manage account details',
    'perm_affiliates' => 'View affiliate details',

    // ── User Management ───────────────────────────────────────────────────────────
    'user_management' => 'User Management',
    'user_management_subtitle' => 'Who can access this account',
    'account_owner' => 'Account Owner',
    'owner' => 'Owner',
    'sub_accounts' => 'Users With Access',
    'sub_accounts_empty' => 'No other users have access to this account.',
    'manage_contacts' => 'Manage Contacts',
    'no_permissions' => 'no permissions granted',
    'revoke_access' => 'Revoke Access',
    'revoke_confirm' => 'Revoke this user\'s access? Their contact details are kept.',
    'access_revoked' => 'Access revoked.',
    'active_sessions' => 'Active Sessions',
    'sessions_empty' => 'No active sessions.',

    'users_found' => '{1} :count User Found|[2,*] :count Users Found',
    'email_last_login' => 'Email Address / Last Login',
    'actions' => 'Actions',
    'last_login' => 'Last Login',
    'never' => 'never',
    'manage_permissions' => 'Manage Permissions',
    'owner_note' => '* Account owners always have full permissions over a client account.',
    'invite_new_user' => 'Invite New User',
    'invite_help' => 'Inviting a new user adds them to this account with the permissions you choose. They are listed here and can be edited from Contacts at any time.',
    'all_permissions' => 'All Permissions',
    'choose_permissions' => 'Choose Permissions',
    'send_invite' => 'Send Invite',
    'invite_sent' => 'User added to this account.',
    'invite_duplicate' => 'Someone with that email address is already on this account.',
    'invite_is_owner' => 'That is the account owner\'s own address.',

    // ── Email History ─────────────────────────────────────────────────────────────
    'email_history' => 'Email History',
    'email_history_subtitle' => 'Messages we have sent you',
    'email_history_empty' => 'No emails have been sent to you yet.',
    'messages_sent' => 'Messages Sent',
    'subject' => 'Subject',
    'sent_to' => 'Sent To',
    'status' => 'Status',
    'date' => 'Date',

    // ── Addons ────────────────────────────────────────────────────────────────────
    'addons' => 'Available Addons',
    'addons_short' => 'Product Addons',
    'addons_subtitle' => 'Extend the services you already have',
    'addons_empty' => 'There are no addons available for your services.',
    'addons_order' => 'Order Now',
    'addons_service' => 'Service',

    // ── Apply Credit (invoice) ────────────────────────────────────────────────────
    'apply_credit' => 'Apply Credit',
    'credit_balance_is' => 'Your credit balance is :amount.',
    'credit_help' => 'This can be applied to the invoice using the form below. Enter the amount to apply:',
    'credit_amount' => 'Amount to apply',
    'credit_applied' => 'Credit applied to this invoice.',
    'credit_none' => 'There is no credit available to apply.',
    'credit_too_much' => 'You can apply at most :max to this invoice.',
    'credit_not_payable' => 'This invoice can no longer be paid.',

    // ── Shared ────────────────────────────────────────────────────────────────────
    'edit' => 'Edit',
    'delete' => 'Delete',
    'cancel' => 'Cancel',
];
