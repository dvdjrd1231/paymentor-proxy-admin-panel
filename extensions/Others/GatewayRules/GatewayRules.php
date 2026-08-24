<?php

namespace Paymenter\Extensions\Others\GatewayRules;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\GatewayRules\Support\GatewayRuleEngine;

/**
 * Gateway availability rules by country, product, group, currency, customer or amount.
 * Managed in the admin panel, evaluated by Support\GatewayRuleEngine, enforced server-side.
 *
 * Our own gateways call allows() from their canUseGateway() hook. Covering every gateway
 * takes one documented line in ExtensionHelper::getCheckoutGateways() — see
 * docs/CORE-TOUCHPOINTS.md.
 *
 * @link docs/modules/gateway-rules.md
 */
class GatewayRules extends Extension
{
    public function getConfig($values = [])
    {
        try {
            $url = \Paymenter\Extensions\Others\GatewayRules\Admin\Resources\GatewayRuleResource::getUrl();

            return [[
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Manage availability rules under <a class="text-primary-600" href="' . $url . '">Gateway Rules</a>.'),
            ]];
        } catch (\Throwable $e) {
            return [[
                'name' => 'Notice',
                'type' => 'placeholder',
                'label' => new HtmlString('Enable this extension, then manage rules under "Gateway Rules".'),
            ]];
        }
    }

    public function installed()
    {
        ExtensionHelper::runMigrations('extensions/Others/GatewayRules/database/migrations');
    }

    public function uninstalled()
    {
        ExtensionHelper::rollbackMigrations('extensions/Others/GatewayRules/database/migrations');
    }

    /**
     * Whether $gateway may be offered for the given checkout context.
     * Call this from a gateway's canUseGateway() or the central checkout filter.
     */
    public static function allows(Gateway $gateway, $total, $currency, $type = null, $items = []): bool
    {
        return GatewayRuleEngine::allows($gateway, $total, $currency, $type, $items);
    }
}
