<?php

namespace App\Classes\Extension;

use App\Models\Extension as ModelsExtension;
use App\Models\Gateway;
use App\Models\Server;

/**
 * Base class for extensions
 *
 * @link https://docs.paymenter.org/development/extensions
 */
class Extension
{
    public function __construct(public $config = []) {}

    /**
     * Get a configuration value
     *
     * @param  string  $key
     * @return mixed
     */
    public function config($key)
    {
        if (empty($this->config)) {
            // Check from which type its being called
            $type = debug_backtrace()[1]['class'];
            $type = str_replace('Paymenter\Extensions\\', '', $type);
            $type = str_replace('\\' . class_basename(static::class), '', $type);
            if (in_array($type, ['Servers', 'Gateways'])) {
                $type = substr($type, 0, -1);
                $type = ($type == 'Gateway') ? Gateway::class : Server::class;
                $this->config = $type::where('extension', class_basename(static::class))->first()->settings->pluck('value', 'key')->toArray();
            } else {
                $this->config = ModelsExtension::where('extension', class_basename(static::class))->first()->settings->pluck('value', 'key')->toArray();
            }
        }

        // With PAYMENT_MODE=dev, sandbox credentials from .env take precedence over
        // whatever an admin saved, so development never runs against the client's real
        // keys. See docs/CORE-TOUCHPOINTS.md #8 and docs/PAYMENT-KEYS.md.
        if (($development = $this->developmentConfig($key)) !== null) {
            return $development;
        }

        return $this->config[$key] ?? null;
    }

    /**
     * Sandbox credential override for a gateway setting, or null when it does not apply.
     *
     * Only gateways are affected, and only when PAYMENT_MODE=dev. A blank entry falls
     * through to the stored value, so a single field can be overridden on its own.
     */
    private function developmentConfig(string $key): ?string
    {
        if (strtolower((string) config('payments.mode', 'prod')) !== 'dev') {
            return null;
        }

        if (!str_starts_with(static::class, 'Paymenter\\Extensions\\Gateways\\')) {
            return null;
        }

        $value = config('payments.dev.' . class_basename(static::class) . '.' . $key);

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Get the configuration fields for the extension
     *
     * @link https://docs.paymenter.org
     *
     * @param  array  $values  The current values of the configuration (is empty on first load)
     * @return array
     */
    public function getConfig($values = [])
    {
        return [];
    }

    /**
     * Called when the extension is installed for the first time
     * If the extension type is server or gateway, it will be called when the first server or gateway is created
     *
     * @return void
     */
    public function installed() {}

    /**
     * Called when the extension is uninstalled
     * If the extension type is server or gateway, it will be called when the last server or gateway is deleted
     *
     * @return void
     */
    public function uninstalled() {}

    /**
     * Called when the extension is updated
     * This is called when the extension is updated to a new version
     *
     * @param  string  $oldVersion  The old version of the extension
     * @return void
     */
    public function upgraded($oldVersion = null) {}

    /**
     * Called every request to the extension (if the extension is enabled)
     *
     * @return void
     */
    public function boot() {}
}
