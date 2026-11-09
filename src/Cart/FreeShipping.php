<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Cart;

use Cart;
use Configuration;
use Context;
use Tools;

/**
 * Ile brakuje koszykowi do darmowej dostawy.
 *
 * Próg to ustawienie sklepu „Darmowa dostawa od kwoty" (`PS_SHIPPING_FREE_PRICE`).
 * Liczymy tak jak rdzeń, gdy decyduje o koszcie dostawy: wartość koszyka brutto
 * razem z rabatami, bez samej dostawy, a próg przeliczony na walutę klienta.
 * Inaczej pasek mówiłby „brakuje 3 zł", a kasa i tak liczyłaby już dostawę
 * za darmo albo odwrotnie.
 *
 * Z tego samego wyliczenia korzysta szuflada koszyka (pasek postępu) i moduł
 * dokupki (dobór produktów pod próg).
 */
final class FreeShipping
{
    /**
     * Stan koszyka wobec progu albo null, gdy nie ma czego pokazywać: sklep
     * nie ma progu, koszyk jest pusty albo zawiera same produkty wirtualne.
     *
     * @return array{threshold: float, missing: float, percent: int, reached: bool}|null
     */
    public static function forContext(Context $kontekst): ?array
    {
        $prog = (float) Configuration::get('PS_SHIPPING_FREE_PRICE');
        $koszyk = $kontekst->cart;

        if ($prog <= 0 || !$koszyk || !$koszyk->id || !$koszyk->nbProducts() || $koszyk->isVirtualCart()) {
            return null;
        }

        $prog = (float) Tools::convertPrice($prog, $kontekst->currency);
        $wartosc = (float) $koszyk->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, null, null, false);
        $brak = max(0.0, $prog - $wartosc);

        return [
            'threshold' => $prog,
            'missing' => $brak,
            'percent' => (int) min(100, floor($wartosc / $prog * 100)),
            'reached' => $brak <= 0,
        ];
    }

    /**
     * Wersja dla szablonu: modyfikator `qfreeshipping` na zmiennej `$cart`.
     *
     * Presentowany koszyk nie niesie progu, więc bierzemy koszyk z kontekstu,
     * ten sam, z którego powstała zmienna. Kwota brakująca przychodzi od razu
     * zapisana walutą sklepu.
     *
     * @param mixed $koszyk koszyk z szablonu; tylko dla czytelności wywołania
     *
     * @return array<string,mixed>|null
     */
    public static function forTemplate($koszyk = null): ?array
    {
        $kontekst = Context::getContext();
        $stan = self::forContext($kontekst);

        if ($stan === null) {
            return null;
        }

        $stan['missing_formatted'] = \Quince\Engine\Ui\PriceFormat::format($stan['missing']);

        return $stan;
    }
}
