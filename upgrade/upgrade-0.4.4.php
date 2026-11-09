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
 * 0.4.4: rozsyłanie adresów filmów do pozostałych sklepów.
 *
 * @param Module $module
 */
function upgrade_module_0_4_4($module): bool
{
    return $module->registerHook('actionProductSave');
}
