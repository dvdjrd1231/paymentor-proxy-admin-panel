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
];
