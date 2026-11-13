<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Settings;

use Configuration;
use Shop;

/**
 * Zapis ustawień silnika do konfiguracji sklepu.
 *
 * Configuration::updateValue zapisuje tam, gdzie wskazuje kontekst back-office.
 * Przy włączonej wielosklepowości i wybranej pozycji „Wszystkie sklepy" kontekst nie
 * wskazuje żadnego sklepu, więc wartość ląduje w wierszu globalnym. Sklep czyta
 * najpierw swój własny wiersz i po globalny sięga dopiero wtedy, gdy własnego nie ma,
 * więc zwykły zapis „dla wszystkich sklepów" nie dociera do sklepu z własnym wierszem.
 *
 * Dlatego zapisujemy wartość każdemu sklepowi z bieżącego kontekstu (jednemu,
 * grupie albo wszystkim, zależnie od wyboru w panelu), a w kontekście
 * „wszystkie sklepy" dodatkowo do wiersza globalnego, żeby sklep założony później
 * też ją dostał.
 */
final class ConfigStore
{
    /**
     * @param bool $html wartość zawiera znaki, których PrestaShop nie ma escapować (JSON)
     */
    public static function set(string $key, string $value, bool $html = false): void
    {
        foreach (Shop::getContextListShopID() as $idShop) {
            Configuration::updateValue($key, $value, $html, null, (int) $idShop);
        }

        if (Shop::getContext() === Shop::CONTEXT_ALL) {
            Configuration::updateGlobalValue($key, $value, $html);
        }
    }
}
