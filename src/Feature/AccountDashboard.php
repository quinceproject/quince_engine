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

use CartRule;
use Order;
use PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter;

/**
 * Dane panelu klienta (`my-account`): liczniki i ostatnie zamówienia.
 *
 * Rdzeń nie liczy nic z tego sam — `MyAccountController` tylko wyświetla listę
 * odnośników. Adresy ma już szablon przez `$customer.addresses`, więc tu tylko
 * to, czego nigdzie indziej nie ma.
 */
final class AccountDashboard
{
    /** Tyle ostatnich zamówień pokazuje panel — tyle samo, co w prototypie. */
    private const RECENT_ORDERS_LIMIT = 3;

    /**
     * @return array{ordersCount:int,activeVouchersCount:int,recentOrders:array}
     */
    public static function forCustomer(int $idCustomer, int $idLang): array
    {
        $zamowienia = Order::getCustomerOrders($idCustomer);

        $prezenter = new OrderPresenter();
        $ostatnie = [];
        foreach (array_slice($zamowienia, 0, self::RECENT_ORDERS_LIMIT) as $zamowienie) {
            $ostatnie[] = [
                'order' => $prezenter->present(new Order((int) $zamowienie['id_order'])),
                'statusTone' => OrderStatusTone::forState((int) $zamowienie['id_order_state']),
            ];
        }

        return [
            'ordersCount' => count($zamowienia),
            'activeVouchersCount' => self::liczbaAktywnychKuponow($idLang, $idCustomer),
            'recentOrders' => $ostatnie,
        ];
    }

    /**
     * Te same warunki co `DiscountController::getTemplateVarCartRules()`
     * (strona „Kupony"): tylko kupony przypisane temu klientowi z osobna,
     * aktywne i z dostępną pulą. Licznik na panelu ma się zgadzać z tym,
     * co klient zobaczy po wejściu w „Kupony".
     */
    private static function liczbaAktywnychKuponow(int $idLang, int $idCustomer): int
    {
        $kupony = CartRule::getCustomerCartRules($idLang, $idCustomer, true, false);

        $ile = 0;
        foreach ($kupony as $kupon) {
            $idKlientaReguly = (int) $kupon['id_customer'];
            if ($idKlientaReguly !== 0 && $idKlientaReguly !== $idCustomer) {
                continue;
            }
            if ((int) $kupon['quantity'] === 0 || (int) $kupon['quantity_for_user'] === 0) {
                continue;
            }
            ++$ile;
        }

        return $ile;
    }
}
