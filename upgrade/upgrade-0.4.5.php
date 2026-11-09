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
 * 0.4.5: filmy idą do szablonu, a nie do zmiennych JavaScriptu strony.
 *
 * Moduł zgód dopasowuje reguły blokowania po treści skryptów, więc adres filmu
 * w zmiennych JS pasował do reguły „youtube" i wycinał skrypt rdzenia razem
 * z sobą. Sklep tracił wtedy cały JavaScript. Hooka nie dokładamy: przypisanie
 * wchodzi do `actionFrontControllerInitAfter`, który silnik ma od początku.
 *
 * @param Module $module
 */
function upgrade_module_0_4_5($module): bool
{
    return true;
}
