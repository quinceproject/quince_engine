<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Ui;

use Context;

/**
 * Kwota zapisana tak, jak zapisuje ją sklep.
 *
 * PrestaShop podaje szablonowi część kwot gotowych do wypisania, a część jako
 * surowe liczby (np. cena netto produktu). Zapis kwoty zależy od języka
 * i waluty odwiedzającego, więc formatuje go lokalizacja sklepu, ta sama,
 * która formatuje cenę brutto obok.
 *
 * Klasa jedzie do Smarty jako modyfikator `qprice`.
 */
final class PriceFormat
{
    /**
     * @param mixed $kwota liczba albo napis z liczbą
     */
    public static function format($kwota): string
    {
        $kontekst = Context::getContext();
        $locale = $kontekst->getCurrentLocale();
        $waluta = $kontekst->currency;

        // Poza żądaniem sklepu (konsola, zadanie w tle) kontekst nie ma ani
        // lokalizacji, ani waluty. Zwracamy wtedy samą liczbę.
        if ($locale === null || $waluta === null) {
            return (string) $kwota;
        }

        return $locale->formatPrice((float) $kwota, (string) $waluta->iso_code);
    }
}
