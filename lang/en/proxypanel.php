<?php

/*
 * Wording for the proxy service page (extensions/Servers/ProxyPanel).
 * Edit the right-hand side only — no code change needed.
 */

return [
    // Service detail labels
    'proxy_username' => 'Proxy username',
    'proxy_password' => 'Proxy password',
    'proxy_count' => 'Proxies',
    'proxy_endpoints' => 'Proxy addresses',
    'auth_ips' => 'Authorized IPs',
    'rotation_time' => 'Rotation interval (minutes)',
    'rotations_used' => 'Rotations used',
    'api_key' => 'API key',
    'panel_expiration' => 'Expires on panel',
    'panel_service_id' => 'Service reference',
    'last_synced' => 'Last updated',

    // Client-area action buttons
    'action_sync' => 'Sync status',
    'action_rotate' => 'Rotate proxies now',
    'action_reboot' => 'Reboot',
    'action_export' => 'Export proxy list',

    // Errors shown to the customer
    'rotate_not_allowed' => 'Manual rotation is not available on this plan.',
    'rotate_limit_reached' => 'You have used all :max rotations available this period.',
    'invalid_ip' => '":ip" is not a valid IP address.',
    'too_many_ips' => 'You can authorize at most :max IP addresses.',
    'password_too_short' => 'The proxy password must be at least 8 characters.',
    'rotation_change_not_allowed' => 'Changing the rotation interval is not available on this plan.',
    'invalid_rotation_time' => 'The rotation interval must be zero or more minutes.',

    // Management panel on the service page
    'manage_title' => 'Manage proxies',
    'proxy_list' => 'Your proxies',
    'endpoint' => 'Address',
    'no_proxies' => 'No proxies have been assigned yet. Use "Sync status" to refresh.',
    'auth_ips_hint' => 'Allow connections from up to :max IP addresses. Leave blank to disable IP authorization and use username/password only.',
    'ip_number' => 'IP :number',
    'change_password' => 'Proxy password',
    'new_password' => 'New password',
    'rotation' => 'Rotation',
    'rotation_time_hint' => 'Minutes between automatic rotations. Zero disables automatic rotation.',
    'save' => 'Save',
    'out_of_stock' => '(Out of stock)',

    // Confirmations
    'auth_ips_updated' => 'Authorized IPs updated.',
    'password_updated' => 'Proxy password updated.',
    'rotation_updated' => 'Rotation interval updated.',
];
