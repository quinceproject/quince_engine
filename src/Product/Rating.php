<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Product;

use Configuration;
use Context;
use Db;
use Module;

/**
 * Ocena produktu — średnia i liczba opinii.
 *
 * Opinie prowadzi `productcomments`: jego tabele, jego moderacja, jego formularz.
 * Bierzemy z nich dwie liczby i rysujemy je sami, tak jak robimy to z listą
 * życzeń — bo gwiazdki pod nazwą produktu mają wyglądać jak reszta strony,
 * a nie jak wstawka z cudzego modułu.
 *
 * Bez modułu opinii nie ma czego liczyć i nic się nie renderuje. Nie jest to
 * błąd: sklep bez opinii po prostu ich nie pokazuje.
 */
final class Rating
{
    /** Ile gwiazdek pokazuje skala. */
    private const GWIAZDEK = 5;

    /**
     * @return array{average:float,averageText:string,count:int,stars:array<int,string>,breakdown:array<int,array{stars:int,count:int,percent:int}>}|null
     */
    public static function forProduct(int $idProduct): ?array
    {
        if ($idProduct <= 0 || !self::modulJest()) {
            return null;
        }

        // Moderacja jest ustawieniem tamtego modułu: gdy jest włączona, klient
        // widzi wyłącznie opinie zatwierdzone, więc średnia musi być liczona
        // z tego samego zbioru. Inaczej gwiazdki nie zgadzałyby się z listą.
        $tylkoZatwierdzone = (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE');

        $wiersz = Db::getInstance()->getRow(
            'SELECT AVG(grade) AS srednia, COUNT(*) AS ile
             FROM ' . _DB_PREFIX_ . 'product_comment
             WHERE id_product = ' . $idProduct . '
               AND deleted = 0'
            . ($tylkoZatwierdzone ? ' AND validate = 1' : '')
        );

        $ile = (int) ($wiersz['ile'] ?? 0);

        if ($ile === 0) {
            return ['average' => 0.0, 'averageText' => '', 'count' => 0, 'stars' => [], 'breakdown' => []];
        }

        $srednia = round((float) $wiersz['srednia'], 1);

        return [
            'average' => $srednia,
            'breakdown' => self::rozklad($idProduct, $tylkoZatwierdzone),
            // Liczba do wypisania idzie przez lokalizację sklepu: po polsku
            // ocena ma przecinek, a nie kropkę. Szablon ma wypisać gotowy tekst,
            // a nie znać zasad zapisu liczb.
            'averageText' => self::liczba($srednia),
            'count' => $ile,
            'stars' => self::gwiazdki($srednia),
        ];
    }

    /**
     * Średnia zapisana po ludzku dla bieżącego języka.
     *
     * Poza żądaniem sklepu (konsola, zadanie w tle) kontekst nie ma lokalizacji —
     * wtedy zostaje zapis z kropką, bo lepszy niż błąd.
     */
    private static function liczba(float $srednia): string
    {
        $locale = Context::getContext()->getCurrentLocale();

        return $locale !== null ? $locale->formatNumber($srednia) : (string) $srednia;
    }

    /**
     * Rozkład ocen: ile opinii przypada na każdą liczbę gwiazdek.
     *
     * Oceny w bazie są ułamkowe (moduł pozwala oceniać w połówkach przy wielu
     * kryteriach), więc zaokrąglamy je do pełnej gwiazdki. Pięć wierszy jest
     * zawsze, także te z zerem: brak wiersza „1 gwiazdka" czytałby się jak brak
     * danych, a nie jak brak takich ocen.
     *
     * Udział liczymy względem najliczniejszej oceny, a nie względem sumy:
     * paski mają porównywać oceny między sobą, a przy rozkładzie 37/7/2/1/0
     * pasek liczony od sumy byłby ledwie widoczny wszędzie poza piątką.
     *
     * @return array<int,array{stars:int,count:int,percent:int}>
     */
    private static function rozklad(int $idProduct, bool $tylkoZatwierdzone): array
    {
        $wiersze = Db::getInstance()->executeS(
            'SELECT ROUND(grade) AS ocena, COUNT(*) AS ile
             FROM ' . _DB_PREFIX_ . 'product_comment
             WHERE id_product = ' . $idProduct . '
               AND deleted = 0'
            . ($tylkoZatwierdzone ? ' AND validate = 1' : '')
            . ' GROUP BY ROUND(grade)'
        ) ?: [];

        $policzone = [];

        foreach ($wiersze as $w) {
            $policzone[(int) $w['ocena']] = (int) $w['ile'];
        }

        $najwyzszy = $policzone === [] ? 0 : max($policzone);
        $rozklad = [];

        for ($gwiazdek = self::GWIAZDEK; $gwiazdek >= 1; --$gwiazdek) {
            $tyle = $policzone[$gwiazdek] ?? 0;

            $rozklad[] = [
                'stars' => $gwiazdek,
                'count' => $tyle,
                'percent' => $najwyzszy > 0 ? (int) round($tyle / $najwyzszy * 100) : 0,
            ];
        }

        return $rozklad;
    }

    /**
     * Pięć gwiazdek jako lista stanów: pełna, połowa albo pusta.
     *
     * Liczy to silnik, a nie szablon: zaokrąglenie do połówki jest decyzją
     * o tym, co pokazujemy, a nie sposobem wypisania. Szablon ma wypisać pięć
     * elementów i nie wiedzieć, skąd się wzięły.
     *
     * @return array<int,string> 'full' | 'half' | 'empty'
     */
    private static function gwiazdki(float $srednia): array
    {
        // Do najbliższej połówki: 4,3 to cztery gwiazdki, 4,3 z połówką byłoby
        // obietnicą dokładności, której średnia z kilku opinii nie ma.
        $polowki = (int) round($srednia * 2);
        $stany = [];

        for ($i = 1; $i <= self::GWIAZDEK; ++$i) {
            if ($polowki >= $i * 2) {
                $stany[] = 'full';
            } elseif ($polowki === ($i * 2) - 1) {
                $stany[] = 'half';
            } else {
                $stany[] = 'empty';
            }
        }

        return $stany;
    }

    /**
     * Czy moduł opinii jest zainstalowany i włączony.
     *
     * Sprawdzamy moduł, a nie samą tabelę: tabela zostaje po odinstalowaniu
     * dopiero do czasu sprzątania, a wtedy pokazywalibyśmy oceny ze sklepu,
     * który opinii już nie zbiera.
     */
    private static function modulJest(): bool
    {
        return Module::isInstalled('productcomments') && Module::isEnabled('productcomments');
    }
}
