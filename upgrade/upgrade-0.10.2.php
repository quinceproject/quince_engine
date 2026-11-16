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
 * 0.10.2: liczba „ile zajawek” z ekranu Strona produktu przepisana do modułów
 * rdzenia, które same pobierają produkty: ps_categoryproducts i ps_viewedproduct.
 *
 * Od tej wersji robi to każdy zapis ekranu. Tutaj raz, dla sklepów, które
 * ustawienie już mają, żeby nie trzeba było go zapisywać ponownie.
 *
 * Bez wołania klas silnika: w trakcie aktualizacji w pamięci bywa jeszcze ich
 * stara wersja. Domyślna liczba i zakres jak w ProductPageSettings (8, od 2 do 20).
 *
 * @param Module $module
 */
function upgrade_module_0_10_2($module): bool
{
    $klucze = [
        'ps_categoryproducts' => ['category_products', 'CATEGORYPRODUCTS_DISPLAY_PRODUCTS'],
        'ps_viewedproduct' => ['recently_viewed', 'PRODUCTS_VIEWED_NBR'],
    ];

    foreach ($klucze as $modul => [$element, $klucz]) {
        if (!Module::isInstalled($modul)) {
            continue;
        }

        foreach (Shop::getShops(false, null, true) as $idShop) {
            $opcje = json_decode(
                html_entity_decode((string) Configuration::get('QUINCE_PDP_OPTIONS', null, null, (int) $idShop), ENT_QUOTES, 'UTF-8'),
                true
            );

            $ile = is_array($opcje) && isset($opcje[$element]['count']) ? (int) $opcje[$element]['count'] : 8;
            $ile = max(2, min(20, $ile));

            Configuration::updateValue($klucz, (string) $ile, false, null, (int) $idShop);
        }
    }

    return true;
}
