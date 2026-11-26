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
 * 0.10.15: hook `actionWatermark`, który po wgraniu zdjęcia dorabia brakujące
 * formaty miniatur (WebP po wgraniu przez Webservice).
 *
 * Wpis wprost do `hook_module`, a nie `registerHook()`: w trakcie aktualizacji
 * w pamięci siedzi jeszcze stara klasa modułu, bez `hookActionWatermark()`,
 * i rdzeń w trybie deweloperskim odrzuca rejestrację wyjątkiem (tak padła
 * aktualizacja quince_contacts 0.7.0 na toolexperts).
 *
 * @param Module $module
 */
function upgrade_module_0_10_15($module): bool
{
    $idHook = (int) Hook::getIdByName('actionWatermark', false);

    if ($idHook <= 0 || (int) $module->id <= 0) {
        return false;
    }

    $ok = true;

    foreach (Shop::getCompleteListOfShopsID() as $idShop) {
        $jest = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'hook_module`
             WHERE id_module = ' . (int) $module->id . ' AND id_hook = ' . $idHook . ' AND id_shop = ' . (int) $idShop
        );

        if ($jest > 0) {
            continue;
        }

        $pozycja = (int) Db::getInstance()->getValue(
            'SELECT MAX(position) FROM `' . _DB_PREFIX_ . 'hook_module`
             WHERE id_hook = ' . $idHook . ' AND id_shop = ' . (int) $idShop
        );

        $ok = $ok && Db::getInstance()->insert('hook_module', [
            'id_module' => (int) $module->id,
            'id_hook' => $idHook,
            'id_shop' => (int) $idShop,
            'position' => $pozycja + 1,
        ]);
    }

    return $ok;
}
