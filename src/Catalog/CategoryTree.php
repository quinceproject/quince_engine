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

use Category;
use Configuration;
use Context;
use Db;
use Link;

/**
 * Drzewo kategorii dla nawigacji.
 *
 * Buduje gałęzie do zadanej głębokości jednym zapytaniem na poziom, a nie jednym
 * na kategorię: menu z pięcioma działami po dziesięć podkategorii to przy naiwnym
 * podejściu pięćdziesiąt zapytań na każdej stronie sklepu.
 *
 * Klasa nie wie, jak menu wygląda. Kolumny, kafelki i kaskada dostają ten sam
 * materiał, różni je wyłącznie szablon.
 */
final class CategoryTree
{
    /** Głębiej menu przestaje być nawigacją, a zaczyna być spisem treści. */
    private const MAX_DEPTH = 3;

    private Link $link;

    public function __construct(?Link $link = null)
    {
        $this->link = $link ?? Context::getContext()->link;
    }

    /**
     * Gałęzie menu.
     *
     * @param array<int,array{id:int,icon:string}> $roots wybrane kategorie główne
     *                              w kolejności sprzedawcy; pusto znaczy wszystkie
     *                              dzieci korzenia sklepu
     *
     * @return array<int,array{id:int,name:string,url:string,image:?string,icon:string,children:array<int,mixed>}>
     */
    public function branches(array $roots, int $depth, int $idLang): array
    {
        $depth = max(1, min(self::MAX_DEPTH, $depth));
        $home = (int) Configuration::get('PS_HOME_CATEGORY');

        if ($roots !== []) {
            $level = $this->fetch(array_column($roots, 'id'), $idLang);

            // Ikona jest ustawieniem menu, nie własnością kategorii, więc dokładamy ją
            // po wyjęciu z bazy, po numerze.
            $icons = array_column($roots, 'icon', 'id');

            foreach ($level as $i => $row) {
                $level[$i]['icon'] = (string) ($icons[$row['id']] ?? '');
            }
        } else {
            $level = $this->fetchChildren([$home], $idLang)[$home] ?? [];
        }

        return $this->attachChildren($level, $depth - 1, $idLang);
    }

    // ------------------------------------------------------------------ budowanie

    /**
     * @param array<int,array<string,mixed>> $level
     *
     * @return array<int,array<string,mixed>>
     */
    private function attachChildren(array $level, int $remaining, int $idLang): array
    {
        if ($level === [] || $remaining < 1) {
            return array_map(fn (array $row): array => $row + ['children' => []], $level);
        }

        // Jedno zapytanie na cały poziom, nie jedno na kategorię.
        $children = $this->fetchChildren(array_column($level, 'id'), $idLang);
        $out = [];

        foreach ($level as $row) {
            $mine = $children[$row['id']] ?? [];
            $row['children'] = $this->attachChildren($mine, $remaining - 1, $idLang);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Kategorie wskazane wprost, w kolejności ustawionej w panelu (nie w kolejności
     * zwróconej przez bazę).
     *
     * @param array<int,int> $ids
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetch(array $ids, int $idLang): array
    {
        $rows = $this->query('c.id_category IN (' . implode(',', array_map('intval', $ids)) . ')', $idLang);
        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row['id_category']] = $this->present($row);
        }

        $out = [];

        foreach ($ids as $id) {
            if (isset($byId[(int) $id])) {
                $out[] = $byId[(int) $id];
            }
        }

        return $out;
    }

    /**
     * Dzieci wielu kategorii naraz, pogrupowane po rodzicu.
     *
     * @param array<int,int> $parents
     *
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function fetchChildren(array $parents, int $idLang): array
    {
        if ($parents === []) {
            return [];
        }

        $rows = $this->query(
            'c.id_parent IN (' . implode(',', array_map('intval', $parents)) . ')',
            $idLang
        );

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row['id_parent']][] = $this->present($row);
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function query(string $where, int $idLang): array
    {
        $sql = 'SELECT c.id_category, c.id_parent, cl.name, cl.link_rewrite
                FROM ' . _DB_PREFIX_ . 'category c
                INNER JOIN ' . _DB_PREFIX_ . 'category_lang cl
                    ON cl.id_category = c.id_category
                    AND cl.id_lang = ' . (int) $idLang . '
                    AND cl.id_shop = ' . (int) Context::getContext()->shop->id . '
                INNER JOIN ' . _DB_PREFIX_ . 'category_shop cs
                    ON cs.id_category = c.id_category
                    AND cs.id_shop = ' . (int) Context::getContext()->shop->id . '
                WHERE c.active = 1 AND ' . $where . '
                ORDER BY cs.position ASC';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return array<string,mixed>
     */
    private function present(array $row): array
    {
        $id = (int) $row['id_category'];

        return [
            'id' => $id,
            'name' => (string) $row['name'],
            'url' => $this->link->getCategoryLink($id, (string) $row['link_rewrite']),
            // Obraz istnieje tylko dla części kategorii. Kafelki muszą sobie
            // z jego brakiem poradzić, więc null jest tu wartością, nie błędem.
            'image' => $this->image($id),
            // Nadpisywana wyżej dla kategorii głównych; podkategorie ikon nie mają.
            'icon' => '',
        ];
    }

    private function image(int $id): ?string
    {
        $file = _PS_CAT_IMG_DIR_ . $id . '.jpg';

        return is_file($file) ? $this->link->getCatImageLink((string) $id, $id, 'category_default') : null;
    }
}
