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
use DbQuery;

/**
 * Oceny produktów pokazywanych na listingu.
 *
 * Moduł opinii dociąga je skryptem, osobnym zapytaniem po wczytaniu strony.
 * Ma to trzy wady: ocena pojawia się z opóźnieniem i lista podskakuje, średnia
 * nie trafia do kodu strony, więc nie widzi jej wyszukiwarka, a przy włączonym
 * sklejaniu plików skrypt modułu w ogóle nie dojeżdża do przeglądarki.
 *
 * Liczymy więc sami, jednym zapytaniem dla całej strony listingu, i podajemy
 * gotowe wartości do szablonu. Czytamy tabelę modułu opinii wprost, bo to on
 * jest źródłem danych — gdy modułu nie ma, tabeli też nie ma i zwracamy pustkę.
 */
final class ListingRatings
{
    /** Tabela modułu opinii, bez przedrostka bazy. */
    private const TABLE = 'product_comment';

    /** @var bool|null czy tabela opinii istnieje; sprawdzane raz na żądanie */
    private static ?bool $jestTabela = null;

    /**
     * Średnia i liczba opinii dla podanych produktów.
     *
     * Liczymy tylko opinie zatwierdzone i nieskasowane — dokładnie te, które
     * moduł pokazuje na karcie produktu. Inaczej listing chwaliłby się oceną,
     * której nigdzie indziej nie widać.
     *
     * @param int[] $idProduktow
     *
     * @return array<int,array{srednia:float,ile:int}> kluczem jest identyfikator produktu
     */
    public static function forProducts(array $idProduktow): array
    {
        $idProduktow = array_values(array_unique(array_filter(array_map('intval', $idProduktow))));

        if ($idProduktow === [] || !self::tabelaIstnieje()) {
            return [];
        }

        $zapytanie = (new DbQuery())
            ->select('id_product, AVG(grade) AS srednia, COUNT(*) AS ile')
            ->from(self::TABLE)
            ->where('validate = 1')
            ->where('deleted = 0')
            ->where('id_product IN (' . implode(',', $idProduktow) . ')')
            ->groupBy('id_product');

        $wiersze = Db::getInstance()->executeS($zapytanie);
        $wynik = [];

        foreach (is_array($wiersze) ? $wiersze : [] as $wiersz) {
            $wynik[(int) $wiersz['id_product']] = [
                'srednia' => round((float) $wiersz['srednia'], 1),
                'ile' => (int) $wiersz['ile'],
            ];
        }

        return $wynik;
    }

    /**
     * Czy tabela opinii w ogóle jest.
     *
     * Sklep bez modułu opinii ma działać dalej, tylko bez ocen.
     *
     * `executeS`, a nie `getValue`: to drugie dokleja do zapytania `LIMIT 1`,
     * czego `SHOW TABLES` nie przyjmuje — całe wyszukiwanie wywracało się wtedy
     * błędem składni SQL i strona kategorii nie renderowała się wcale.
     */
    private static function tabelaIstnieje(): bool
    {
        if (self::$jestTabela !== null) {
            return self::$jestTabela;
        }

        $wiersze = Db::getInstance()->executeS(
            'SHOW TABLES LIKE "' . pSQL(_DB_PREFIX_ . self::TABLE) . '"'
        );

        return self::$jestTabela = is_array($wiersze) && $wiersze !== [];
    }
}
