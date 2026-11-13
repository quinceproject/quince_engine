<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Catalog;

use Db;

/**
 * Odczytuje proporcję obrazu produktu z konfiguracji PrestaShopa.
 *
 * Proporcja NIE jest ustawieniem motywu. Sprzedawca definiuje wymiary typów obrazu
 * w Design → Image Settings (tabela ps_image_type) i motyw ma to uszanować. Zaszyta
 * na sztywno wartość 1:1 psułaby sklep, w którym ktoś ustawił kadr pionowy.
 *
 * Bierzemy typ `home_default`, bo to on obsługuje zajawki na listingach, czyli
 * miejsce, gdzie równa wysokość kart w rzędzie ma znaczenie.
 */
final class ImageRatioProvider
{
    /** Typ obrazu używany przez zajawki produktów na listingach. */
    private const LISTING_TYPE = 'home_default';

    /** Gdy typu nie ma albo ma bezsensowne wymiary. Domyślne typy PS są kwadratowe. */
    private const FALLBACK = '1 / 1';

    private ?string $cached = null;

    /**
     * Zwraca wartość gotową do wstawienia w CSS, np. „3 / 4".
     *
     * Wynik trzymamy w pamięci na czas żądania: wywoływane jest raz na stronę,
     * ale ten sam obiekt bywa użyty i przez hook, i przez zmienne szablonu.
     */
    public function forListing(): string
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $row = Db::getInstance()->getRow(
            'SELECT width, height FROM ' . _DB_PREFIX_ . 'image_type WHERE name = "' . self::LISTING_TYPE . '"'
        );

        $width = (int) ($row['width'] ?? 0);
        $height = (int) ($row['height'] ?? 0);

        // Zero albo wartość ujemna oznacza uszkodzony wpis. Lepiej kwadrat
        // niż `aspect-ratio: 0`, po którym karta znika ze strony.
        $this->cached = ($width > 0 && $height > 0)
            ? $width . ' / ' . $height
            : self::FALLBACK;

        return $this->cached;
    }
}
