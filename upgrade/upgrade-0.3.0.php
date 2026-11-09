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
 * 0.3.0 — wysyłka zdarzeń na zewnątrz.
 *
 * Skrzynka nadawcza przeniosła się od tej wersji do osobnego modułu,
 * `quince_events` — jego własna instalacja zakłada tabelę `quince_event`.
 * Ten krok został tylko bez treści, żeby historia uaktualnień sklepów
 * zainstalowanych przed portem nie urywała się na klasie, która już nie
 * istnieje w tym module.
 *
 * @param Module $module
 */
function upgrade_module_0_3_0($module): bool
{
    return true;
}
