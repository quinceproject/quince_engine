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

use Db;
use Tab;

/**
 * Kolejność wpisów w gałęzi „Quince project" w menu PrestaShopa.
 *
 * Każdy moduł zgłasza swój wpis sam, przez `$this->tabs`, więc PrestaShop
 * ustawia kolejność taką, w jakiej ktoś moduły instalował. U jednego sklepu
 * oferty wyszłyby wtedy nad wyszukiwarką, u drugiego pod historią cen, a menu
 * ma wyglądać wszędzie tak samo.
 *
 * Kolejność ustala więc silnik, jedną listą: od tego, co sklep dotyka
 * najczęściej, do tego, co ustawia się raz i zostawia. Moduł nieobecny w liście
 * ląduje na końcu, w kolejności, jaką miał. Dzięki temu nie znikają wpisy,
 * których silnik nie zna, na przykład z modułu dołożonego później.
 */
final class MenuOrder
{
    /** Gałąź, w której stoją wpisy modułów. */
    public const PARENT = 'AdminQuinceProject';

    /**
     * PrestaShop dokłada ten przyrostek, gdy do gałęzi trafia pierwsze dziecko:
     * kopiuje rodzica i to kopia staje się kontenerem. Szukamy więc obu nazw.
     */
    private const SUFFIX = '_MTR';

    /** @var string[] kolejność wpisów, po nazwach klas zakładek */
    private const ORDER = [
        'AdminQuinceEngine',
        'AdminQuinceSearch',
        'AdminQuinceContacts',
        'AdminQuinceQa',
        'AdminQuinceOffers',
        'AdminQuinceLinkblocks',
        'AdminQuinceFeatureRank',
        'AdminQuinceOmnibus',
        'AdminQuinceCmp',
        'AdminQuinceRecaptcha',
        'AdminQuinceRobots',
    ];

    /**
     * Ustawienie pozycji wszystkich wpisów gałęzi.
     *
     * Wołane po instalacji i po odinstalowaniu dowolnego modułu, bo wtedy wpisów
     * przybywa albo ubywa. Zapis idzie prosto do bazy: `Tab::save()` przelicza
     * pozycje po swojemu i skasowałby to, co tu ustawiamy.
     */
    public static function apply(): void
    {
        $idParent = self::parentId();

        if ($idParent === 0) {
            return;
        }

        $wiersze = Db::getInstance()->executeS(
            'SELECT id_tab, class_name FROM `' . _DB_PREFIX_ . 'tab`'
            . ' WHERE id_parent = ' . $idParent . ' ORDER BY position'
        ) ?: [];

        $znane = [];
        $nieznane = [];

        foreach ($wiersze as $wiersz) {
            $miejsce = array_search((string) $wiersz['class_name'], self::ORDER, true);

            if ($miejsce === false) {
                $nieznane[] = (int) $wiersz['id_tab'];
            } else {
                $znane[$miejsce] = (int) $wiersz['id_tab'];
            }
        }

        ksort($znane);
        $pozycja = 0;

        foreach (array_merge(array_values($znane), $nieznane) as $idTab) {
            Db::getInstance()->update('tab', ['position' => $pozycja], 'id_tab = ' . $idTab);
            ++$pozycja;
        }
    }

    /** Identyfikator gałęzi, także wtedy, gdy PrestaShop zrobił z niej kopię. */
    private static function parentId(): int
    {
        $idParent = (int) Tab::getIdFromClassName(self::PARENT . self::SUFFIX);

        return $idParent > 0 ? $idParent : (int) Tab::getIdFromClassName(self::PARENT);
    }
}
