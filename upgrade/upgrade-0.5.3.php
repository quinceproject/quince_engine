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
 * 0.5.3: przywrócenie zapisu ostatnio oglądanych.
 *
 * Wersja 0.5.2 odpinała `ps_viewedproduct` od `displayProductAdditionalInfo`
 * w przekonaniu, że to tam rysuje się karuzela. Ten hook u niego niczego nie
 * rysuje, tylko zapisuje obejrzany produkt do ciasteczka, więc odpięcie
 * zabierało liście źródło danych i znikała także z dołu karty.
 *
 * @param Module $module
 */
function upgrade_module_0_5_3($module): bool
{
    $viewed = Module::getInstanceByName('ps_viewedproduct');

    if (!$viewed || !Module::isInstalled('ps_viewedproduct')) {
        return true;
    }

    if ((int) $viewed->id === 0) {
        $viewed->id = (int) Module::getModuleIdByName('ps_viewedproduct');
    }

    return (bool) $viewed->registerHook('displayProductAdditionalInfo');
}
