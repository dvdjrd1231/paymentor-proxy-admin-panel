<?php

namespace Paymenter\Extensions\Others\GatewayRules;

use App\Classes\Extension\Extension;
use App\Helpers\ExtensionHelper;
use App\Models\Gateway;
use Illuminate\Support\HtmlString;
use Paymenter\Extensions\Others\GatewayRules\Support\GatewayRuleEngine;

/**
 * Country-based (and product / group / currency / customer / amount) gateway
 * availability rules (spec item 5). Enforced server-side.
 *
 * Rules are managed in the admin panel (auto-discovered Payment... resource) and
 * evaluated by Support\GatewayRuleEngine. Enforcement happens at the point Paymenter
 * builds the checkout gateway list:
 *
 *  - Our own gateways (CoinPayments, Binance) call GatewayRules::allows() from their
 *    native canUseGateway() hook, so they respect the rules with no core change.
 *  - To enforce across ALL gateways (Stripe, Cryptomus, …) centrally, one documented
 *    line filters ExtensionHelper::getCheckoutGateways() — see docs/CORE-TOUCHPOINTS.md.
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
