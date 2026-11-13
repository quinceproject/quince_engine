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

use Quince\Engine\Feature\MenuBranch;
use Quince\Engine\Feature\MenuOrder;

/**
 * 0.6.1: gałąź „Quince project” w sklepach, które zainstalowały silnik od zera.
 *
 * Rdzeń odrzucał gałąź przy instalacji, więc wpisy silnika i modułów stoją
 * w menu z rodzicem 0. Zakładamy gałąź i przenosimy je do niej.
 *
 * @param Module $module
 */
function upgrade_module_0_6_1($module): bool
{
    if (MenuBranch::ensure() === 0) {
        return false;
    }

    MenuBranch::adoptOrphans();
    MenuOrder::apply();

    return true;
}
