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
 * 0.10.4: liczba cech w zajawce (widok listy) ustawiana tylko na ekranie
 * Karta produktu. Pole na ekranie Listy produktów znika.
 *
 * Sklep, który wpisał liczbę na ekranie list, a ekranu Karta produktu jeszcze
 * nie zapisał, dostaje tę liczbę w ustawieniach zajawki. Zapisana już wartość
 * zajawki zostaje.
 *
 * Bez wołania klas silnika: w trakcie aktualizacji w pamięci bywa jeszcze ich
 * stara wersja. Zakres jak w ProductCardSettings (od 0 do 12).
 *
 * @param Module $module
 */
function upgrade_module_0_10_4($module): bool
{
    $odczyt = static function (string $klucz, int $idShop): array {
        $dane = json_decode(
            html_entity_decode((string) Configuration::get($klucz, null, null, $idShop), ENT_QUOTES, 'UTF-8'),
            true
        );

        return is_array($dane) ? $dane : [];
    };

    foreach (Shop::getShops(false, null, true) as $idShop) {
        $idShop = (int) $idShop;
        $listy = $odczyt('QUINCE_LISTING_OPTIONS', $idShop);

        if (!isset($listy['products']['features'])) {
            continue;
        }

        $rzad = $odczyt('QUINCE_CARD_ROW', $idShop);

        if (array_key_exists('features', $rzad)) {
            continue;
        }

        $rzad['features'] = max(0, min(12, (int) $listy['products']['features']));

        Configuration::updateValue('QUINCE_CARD_ROW', (string) json_encode($rzad), true, null, $idShop);
    }

    return true;
}
