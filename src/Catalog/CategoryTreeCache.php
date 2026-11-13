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

/**
 * Zapamiętane drzewo kategorii.
 *
 * Menu stoi na każdej stronie sklepu, a katalog zmienia się rzadko, więc liczenie
 * go przy każdym żądaniu to kilka zapytań do bazy po ten sam wynik. Zapamiętujemy więc DANE, nie gotowy kod strony: szablon nadal
 * renderuje po swojemu, więc zmiana wyglądu nie wymaga czyszczenia niczego,
 * a te same dane obsługują wszystkie trzy tryby rozwijania.
 *
 * Unieważnianie jest jawne: silnik kasuje zapis, gdy w katalogu coś się zmieni
 * (dodanie, edycja albo usunięcie kategorii). Czas życia nie wystarcza: nowa
 * kategoria ma być widoczna w menu od razu.
 */
final class CategoryTreeCache
{
    private const PREFIX = 'quince_nav_';

    /**
     * Zapamiętany wynik albo świeżo policzony, gdy zapisu nie ma.
     *
     * @param callable():array<int,mixed> $build
     *
     * @return array<int,mixed>
     */
    public function remember(string $key, callable $build): array
    {
        $file = $this->path($key);

        if (is_file($file)) {
            $stored = json_decode((string) file_get_contents($file), true);

            if (is_array($stored)) {
                return $stored;
            }
        }

        $fresh = $build();

        // Zapis przez plik tymczasowy: bez tego dwa równoczesne żądania potrafią
        // zostawić plik w połowie zapisany, a wtedy menu znika do wyczyszczenia cache.
        $tmp = $file . '.' . getmypid();

        if (@file_put_contents($tmp, json_encode($fresh)) !== false) {
            @rename($tmp, $file);
        }

        return $fresh;
    }

    /** Kasuje wszystkie zapisy. Wołane, gdy w katalogu coś się zmieniło. */
    public static function flush(): void
    {
        foreach (glob(_PS_CACHE_DIR_ . self::PREFIX . '*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function path(string $key): string
    {
        return _PS_CACHE_DIR_ . self::PREFIX . md5($key) . '.json';
    }
}
