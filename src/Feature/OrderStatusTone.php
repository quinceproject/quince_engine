<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Feature;

use Configuration;

/**
 * Odcień statusu zamówienia — wyłącznie z naszych czterech tokenów
 * (sukces/ostrzeżenie/info/błąd), nigdy z koloru wpisanego w panelu przy
 * statusie. Ten dowolny kolor (`ps_order_state.color`) sklepikarz może
 * ustawić na cokolwiek i nie mamy gwarancji, że będzie pasować do reszty
 * strony, stąd zamiast niego rozpoznajemy stałe, wbudowane statusy rdzenia
 * PO ICH KLUCZU KONFIGURACJI (`PS_OS_*`), nie po samym id — id tych
 * statusów różni się między instalacjami.
 *
 * Własny status sklepu (dodany w panelu, spoza tej listy) zostaje bez
 * odcienia, `null`, zamiast dostawać zgadywany kolor.
 *
 * Jedno źródło dla panelu konta (ostatnie zamówienia) i podglądu zamówienia
 * (plakietka statusu, oś czasu) — oba mają pokazywać ten sam kolor dla tego
 * samego statusu.
 */
final class OrderStatusTone
{
    /**
     * @return 'success'|'danger'|'warning'|'info'|null
     */
    public static function forState(int $idOrderState): ?string
    {
        static $mapa = null;

        if ($mapa === null) {
            $mapa = [];

            foreach (['PS_OS_DELIVERED', 'PS_OS_PAYMENT', 'PS_OS_WS_PAYMENT', 'PS_OS_COD_VALIDATION'] as $klucz) {
                $mapa[(int) Configuration::get($klucz)] = 'success';
            }

            foreach (['PS_OS_CANCELED', 'PS_OS_ERROR', 'PS_OS_REFUND'] as $klucz) {
                $mapa[(int) Configuration::get($klucz)] = 'danger';
            }

            foreach (['PS_OS_CHEQUE', 'PS_OS_BANKWIRE', 'PS_OS_OUTOFSTOCK_UNPAID'] as $klucz) {
                $mapa[(int) Configuration::get($klucz)] = 'warning';
            }

            foreach (['PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_OUTOFSTOCK_PAID'] as $klucz) {
                $mapa[(int) Configuration::get($klucz)] = 'info';
            }

            unset($mapa[0]);
        }

        return $mapa[$idOrderState] ?? null;
    }
}
