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
 * 0.4.3: kontrolka filmów na karcie produktu.
 *
 * Dochodzi hook zasobów panelu, żeby skrypt zamieniający pole tekstowe na
 * wiersze z miniaturami trafił na kartę produktu.
 *
 * @param Module $module
 */
function upgrade_module_0_4_3($module): bool
{
    return $module->registerHook('actionAdminControllerSetMedia');
}
