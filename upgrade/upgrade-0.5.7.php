<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 0.5.7: zakup na firmę w silniku (wcześniej osobny moduł `quince_checkout`).
 *
 * Kasa w motywie bez tej logiki gubi drugi adres, więc nie da się jej
 * sensownie oddzielić od silnika. Dochodzą dwa hooki zapisu adresu
 * i nadpisanie kroku adresu kasy.
 *
 * Nadpisanie zakłada się dopiero, gdy stary moduł nie trzyma już swojego:
 * PrestaShop nie pozwoli dwóm modułom nadpisać tej samej metody.
 *
 * Ustawienia `QUINCE_CHECKOUT_VIES_LOOKUP` i `QUINCE_CHECKOUT_VAT_CHECK`
 * zostają pod tymi samymi nazwami. Brak wiersza znaczy „włączone”.
 *
 * @param Module $module
 */
function upgrade_module_0_5_7($module): bool
{
    $stary = Module::getInstanceByName('quince_checkout');

    if ($stary instanceof Module && Module::isInstalled('quince_checkout')) {
        if (!$stary->uninstall()) {
            return false;
        }
    }

    foreach (['actionValidateCustomerAddressForm', 'actionSubmitCustomerAddressForm'] as $hook) {
        if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
            return false;
        }
    }

    return (bool) $module->installOverrides();
}
