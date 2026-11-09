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

use Db;

/**
 * Kategorie sklepu w postaci, której oczekuje wybierak drzewa w panelu.
 *
 * Jedno miejsce dla wszystkich ekranów. Zanim to powstało, każdy moduł budował
 * tę samą listę u siebie: historia cen, wyszukiwarka i bloki odnośników miały
 * po własnym zapytaniu, a każde z nich pomijało coś innego — raz brakowało
 * złączenia po sklepie i przy czterech sklepach lista rosła czterokrotnie, raz
 * wcięcia doklejone myślnikami do nazwy psuły potem wyszukiwanie.
 *
 * Zwracamy nie tylko poziom, ale i ŚCIEŻKĘ PRZODKÓW oraz informację o potomkach,
 * bo z tego komponent zwija gałęzie. Wyliczenie tego w szablonie oznaczałoby
 * pętlę po pętli przy każdym wierszu.
 */
final class CategoryChoices
{
    /**
     * @return array<int,array{id:int,name:string,depth:int,parent:int,sciezka:int[],dzieci:bool}>
     */
    public static function forPanel(int $idLang, int $idShop): array
    {
        // Złączenie po języku ORAZ po sklepie: `category_lang` ma wiersz na każdą
        // parę język–sklep, więc samo `id_lang` daje tyle kopii każdej kategorii,
        // ile sklep ma sklepów.
        $wiersze = Db::getInstance()->executeS(
            'SELECT c.id_category, c.id_parent, c.level_depth, cl.name
             FROM `' . _DB_PREFIX_ . 'category` c
             INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                 ON cl.id_category = c.id_category
                 AND cl.id_lang = ' . $idLang . '
                 AND cl.id_shop = ' . $idShop . '
             INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs
                 ON cs.id_category = c.id_category AND cs.id_shop = ' . $idShop . '
             WHERE c.active = 1 AND c.level_depth > 1
             ORDER BY c.nleft'
        );

        return self::zeSciezkami(is_array($wiersze) ? $wiersze : []);
    }

    /**
     * Dokłada ścieżkę przodków i informację o potomkach.
     *
     * Publiczne, bo ekran bywa potrzebuje własnego zapytania — na przykład
     * z dodatkową kolumną — a ścieżki liczy się wtedy tak samo.
     *
     * @param array<int,array<string,mixed>> $wiersze wiersze z `id_category`, `id_parent`, `level_depth`, `name`
     *
     * @return array<int,array{id:int,name:string,depth:int,parent:int,sciezka:int[],dzieci:bool}>
     */
    public static function zeSciezkami(array $wiersze, string $dodatkowe = ''): array
    {
        $kategorie = [];
        $rodzice = [];

        foreach ($wiersze as $w) {
            $id = (int) $w['id_category'];
            $rodzice[$id] = (int) $w['id_parent'];

            $kategorie[$id] = [
                'id' => $id,
                'name' => (string) $w['name'],
                // Poziom liczony od kategorii głównej, żeby wcięcie zaczynało się od zera.
                'depth' => max(0, (int) $w['level_depth'] - 2),
                'parent' => (int) $w['id_parent'],
            ];

            if ($dodatkowe !== '' && array_key_exists($dodatkowe, $w)) {
                $kategorie[$id][$dodatkowe] = $w[$dodatkowe];
            }
        }

        foreach ($kategorie as $id => $kategoria) {
            $sciezka = [];
            $biezacy = $kategoria['parent'];

            // Idziemy w górę po rodzicach. Warunek na obecność w tablicy zatrzymuje
            // pętlę na korzeniu, którego w zbiorze nie ma — i chroni przed
            // zapętleniem, gdyby dane były uszkodzone.
            while (isset($rodzice[$biezacy])) {
                $sciezka[] = $biezacy;
                $biezacy = $rodzice[$biezacy];
            }

            $kategorie[$id]['sciezka'] = $sciezka;
            $kategorie[$id]['dzieci'] = in_array($id, $rodzice, true);
        }

        return array_values($kategorie);
    }
}
