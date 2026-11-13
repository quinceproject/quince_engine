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
 * 0.2.0: strażnik formularzy.
 *
 * Dochodzi licznik przyjętych zgłoszeń, na którym stoi limit powtórzeń z jednego
 * adresu, i hook, na którym moduły captchy oceniają zgłoszenie.
 *
 * @param Module $module
 */
function upgrade_module_0_2_0($module): bool
{
    return $module->installFormHitTable() && $module->registerFormVerifyHook();
}
