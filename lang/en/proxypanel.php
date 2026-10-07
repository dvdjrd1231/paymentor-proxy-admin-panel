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
    // A count, not the addresses themselves — a service can hold tens of thousands.
    'proxy_endpoints' => 'Proxy addresses assigned',
    'auth_ips' => 'Change IP Authorization',
    'rotation_time' => 'Rotate Interval Minutes',
    'rotations_used' => 'Rotations used',
    'api_key' => 'API key',
    'panel_expiration' => 'Expires on panel',
    'panel_service_id' => 'Service reference',
    'last_synced' => 'Last updated',

    // Client-area action buttons
    // The rail entries, worded as the reference words them. They differ from the page
    // headings above on purpose: its sidebar says "Proxy List (download)" over a page
    // headed "ProxyList".
    'menu_proxy_list' => 'Proxy List (download)',
    'menu_auth_ips' => 'Set IP Authorization',
    'menu_rotation' => 'Set IP Rotation time',
    'menu_password' => 'Set new password!',

    'action_sync' => 'Sync status',
    'action_done' => 'Done.',
    'action_rotate' => 'Rotate NOW!',
    'action_reboot' => 'Reboot',
    'action_export' => 'Export proxy list',

    // Errors shown to the customer
    'rotate_not_allowed' => 'Manual rotation is not available on this plan.',
    'rotate_limit_reached' => 'You have used all :max rotations available this period.',
    'invalid_ip' => '":ip" is not a valid IP address.',
    'too_many_ips' => 'You can authorize at most :max IP addresses.',
    'password_rules' => 'The proxy password must be exactly 8 letters and numbers, with no spaces or symbols.',
    'rotation_change_not_allowed' => 'Changing the rotation interval is not available on this plan.',
    'invalid_rotation_time' => 'The rotation interval must be zero or more minutes.',

    // Management panel on the service page
    'manage_title' => 'Manage proxies',
    'proxy_list' => 'ProxyList',
    'endpoint' => 'Address',
    'no_proxies' => 'No proxies have been assigned yet. Use "Sync status" to refresh.',
    // Shown under the proxy table when a service holds more than the page lists.
    'showing_preview' => 'Showing the first :shown of :total proxies. Download the full list:',
    'auth_ips_hint' => 'Allow connections from up to :max IP addresses. Leave blank to disable IP authorization and use username/password only.',
    'ip_number' => 'IP :number',
    'change_password' => 'Change Your Password',
    'new_password' => 'Password',
    'rotation' => 'Set IP Rotation Time',
    // The reference's own explanation, line for line, shown above the field.
    'rotation_time_hint' => 'This action will Change all the IPv6 assigned to your proxies.
Lets say for example you put 15 minutes, that means all the IPs on your proxies will change every 15 minutes.
In other words, you will get a new virgin & private IPs every 15 minutes! (According to the previous example).
Rotating allowed from 5 minutes - 10080 minutes (7 days). If you put 0 that will disable rotating. If you put null this will switch to default rotation time.
This means that the IPs for the proxies will only change the moment you enable this change to happen.
That will cause less than 1-10 seconds of downtime, to assign new IPs to your Ports.',
    'rotation_placeholder' => 'ROTATE INTERVAL MINUTES',
    'rotation_save' => 'SAVE & ROTATE NOW',
    'save' => 'Save',
    'out_of_stock' => '(Out of stock)',
    'region_placeholder' => 'Select Geographic Region for IPv6 Proxies',
    // Shown instead of invented data when the panel cannot be reached or has nothing to offer.
    'regions_stale' => 'Select Geographic Region — list may be out of date',
    'regions_unavailable' => 'Regions unavailable — could not reach the proxy panel',
    'regions_none' => 'No regions are currently available',

    // Confirmations
    'auth_ips_updated' => 'Authorized IPs updated.',
    'password_updated' => 'Proxy password updated.',
    'rotation_updated' => 'Rotation interval updated.',
];
