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
 * 0.5.6: `/llms.txt` serwowany na żywo (Brand & Schema).
 *
 * Trasa zastępuje wcześniejszy zapis pliku na dysk — ta instalacja ma kilka
 * sklepów, część z nich dzieli jeden fizyczny katalog (`virtual_uri`), więc
 * zapisany plik był wspólny dla wszystkich naraz. `moduleRoutes` każe
 * rdzeniowi policzyć adres osobno dla każdego sklepu i każdego jego języka.
 *
 * Świeża instalacja rejestruje ten hook sama, z `install()`. Ten plik jest dla
 * sklepów, które mają silnik już u siebie.
 *
 * @param Module $module
 */
function upgrade_module_0_5_6($module): bool
{
    if ($module->isRegisteredInHook('moduleRoutes')) {
        return true;
    }

    return (bool) $module->registerHook('moduleRoutes');
}
