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
 * 0.4.6: natywne przyciski udostępniania schodzą z karty produktu.
 *
 * `ps_sharebuttons` wpina się w hook, który motyw wypisuje przy dostępności,
 * więc jego przyciski stały w kolumnie opisu bez możliwości przestawienia ich
 * z panelu. Sklep, który ich chce, włącza moduł z powrotem.
 *
 * @param Module $module
 */
function upgrade_module_0_4_6($module): bool
{
    return $module->wylaczUdostepnianie();
}
