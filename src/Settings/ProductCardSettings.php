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

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;

/**
 * Zajawka produktu: z czego się składa i ile pokazuje.
 *
 * Zajawka stoi wszędzie tam, gdzie sklep wymienia produkty: na listingu,
 * w wynikach wyszukiwania, w karuzelach na stronie głównej i na karcie
 * produktu. Dlatego ustawienia są osobne od listingu.
 *
 * Dwa rodzaje z osobnymi włącznikami. KAFEL ma około 250 px i mieści zdjęcie,
 * nazwę, cenę i przycisk. RZĄD ma całą szerokość listy i mieści do tego opis,
 * zestawienie cech, kody i słupek z dostępnością.
 *
 * Kolejność elementów NIE jest ustawieniem: ustala ją szablon.
 */
final class ProductCardSettings
{
    private const PREFIX = 'QUINCE_CARD_';

    /** Kafel w siatce: listing, karuzele, wyniki wyszukiwania. */
    public const TILE = 'tile';

    /** Rząd na całą szerokość: widok listy na listingu. */
    public const ROW = 'row';

    /** @var array<string,array{label:string,note:string}> */
    private const VARIANTS = [
        self::TILE => [
            'label' => 'Tile',
            'note' => 'In the grid: listings, search results and module carousels.',
        ],
        self::ROW => [
            'label' => 'Row',
            'note' => 'Full width, one product per line. Only in the list view on listings.',
        ],
    ];

    /**
     * Katalog elementów zajawki.
     *
     * `fixed` znaczy, że elementu nie da się wyłączyć: bez zdjęcia, nazwy, ceny
     * i drogi do zakupu nie ma zajawki, tylko wiersz tabeli.
     *
     * `variants` ogranicza element do rodzajów, w których ma sens. Pusta lista
     * znaczy „w obu".
     *
     * `number` zamienia włącznik na pole liczbowe z zakresem.
     *
     * @var array<string,array<string,mixed>>
     */
    private const CATALOGUE = [
        'image' => ['label' => 'Photo', 'variants' => [], 'fixed' => true],

        'badges' => [
            'label' => 'Badges on the photo',
            'note' => 'Discount, new, pack, condition. In the row they move above the name.',
            'variants' => [],
            // Rodzaje plakietek do wyboru pod ☰, wszystkie domyślnie włączone.
            'kinds' => true,
        ],

        'quickview' => [
            'label' => 'Quick view',
            'note' => 'A button on the photo that opens the product without leaving the list.',
            'variants' => [],
        ],

        'wishlist' => [
            'label' => 'Favourites',
            'note' => 'A heart on the photo. Needs a favourites module.',
            'variants' => [],
        ],

        'countdown' => [
            'label' => 'Promotion countdown',
            'note' => 'Only when the discount has an end date and that date is near.',
            'variants' => [],
        ],

        'brand' => ['label' => 'Brand', 'variants' => []],
        'name' => ['label' => 'Product name', 'variants' => [], 'fixed' => true],

        'rating' => [
            'label' => 'Rating',
            'note' => 'Stars and the number of reviews. Needs a reviews module.',
            'variants' => [],
        ],

        // W kaflu nie ma na to miejsca: opis zepchnąłby cenę poza wysokość,
        // którą trzymają wszystkie kafle w rzędzie.
        'description' => [
            'label' => 'Short description',
            'note' => 'Cut to three lines.',
            'variants' => [self::ROW],
        ],

        // Tylko rząd, z tego samego powodu co opis.
        'features' => [
            'label' => 'Features',
            'note' => 'How many to show, counted from the most telling one. The order comes from the feature ranking.',
            'variants' => [self::ROW],
            'number' => 6,
            'range' => [0, 12],
        ],

        'code_reference' => ['label' => 'Reference', 'variants' => [self::ROW]],
        'code_ean' => ['label' => 'EAN', 'variants' => [self::ROW]],
        'code_mpn' => ['label' => 'MPN', 'variants' => [self::ROW]],

        'price' => ['label' => 'Price', 'variants' => [], 'fixed' => true],

        'omnibus' => [
            'label' => 'Lowest price note',
            'note' => 'The lowest price of the last 30 days. Needs a price history module.',
            'variants' => [],
        ],

        'availability' => ['label' => 'Availability', 'variants' => [self::ROW]],
        'shipping' => ['label' => 'Shipping time', 'variants' => [self::ROW]],

        'cart' => ['label' => 'Buy button', 'variants' => [], 'fixed' => true],
    ];

    /**
     * Rodzaje plakietek, które PrestaShop podaje w `$product.flags`.
     *
     * Klucz to nazwa w ustawieniach, `types` to wartości `flag.type` z rdzenia.
     * Stan towaru zbiera pięć typów, bo sklep wybiera je w jednym polu produktu.
     *
     * @var array<string,array{label:string,types:string[]}>
     */
    private const BADGE_KINDS = [
        'new' => ['label' => 'New', 'types' => ['new']],
        'on_sale' => ['label' => 'On sale', 'types' => ['on-sale']],
        'discount' => ['label' => 'Discount', 'types' => ['discount']],
        'pack' => ['label' => 'Pack', 'types' => ['pack']],
        'out_of_stock' => ['label' => 'Out of stock', 'types' => ['out_of_stock']],
        'online_only' => ['label' => 'Online only', 'types' => ['online-only']],
        'condition' => [
            'label' => 'Condition: used, refurbished, open box, damaged',
            'types' => ['used', 'refurbished', 'open_box', 'damaged', 'new_with_defects'],
        ],
    ];

    /**
     * Elementy obecne w obu rodzajach zajawki. Ten sam znacznik służy kaflowi
     * i rzędowi, a widok przełącza się bez przeładowania strony, więc
     * wyłączenie w jednym rodzaju robi klasa na karcie, nie szablon.
     */
    private const SHARED = ['quickview', 'wishlist', 'countdown', 'brand', 'rating', 'omnibus'];

    /**
     * Wartości po instalacji, osobno dla każdego rodzaju.
     *
     * @var array<string,array<string,bool|int>>
     */
    private const DEFAULTS = [
        self::TILE => [
            'badges' => true,
            'quickview' => true,
            'wishlist' => true,
            'countdown' => true,
            'brand' => true,
            'rating' => true,
            'omnibus' => true,
        ],
        self::ROW => [
            'badges' => true,
            'quickview' => true,
            'wishlist' => true,
            'countdown' => true,
            'brand' => true,
            'rating' => true,
            'description' => true,
            'features' => 6,
            'code_reference' => true,
            'code_ean' => true,
            'code_mpn' => true,
            'omnibus' => true,
            'availability' => true,
            'shipping' => true,
        ],
    ];

    /** @return array<string,array{label:string,note:string}> */
    public function variants(): array
    {
        return self::VARIANTS;
    }

    /** @return array<string,array<string,mixed>> */
    public function catalogue(): array
    {
        return self::CATALOGUE;
    }

    /** @return array<string,array{label:string,types:string[]}> */
    public function badgeKinds(): array
    {
        return self::BADGE_KINDS;
    }

    /** Czy element występuje w tym rodzaju zajawki. */
    public function inVariant(string $id, string $variant): bool
    {
        if (!isset(self::CATALOGUE[$id])) {
            return false;
        }

        $dozwolone = self::CATALOGUE[$id]['variants'] ?? [];

        return $dozwolone === [] || in_array($variant, $dozwolone, true);
    }

    /**
     * Ustawienia jednego rodzaju zajawki, z wartościami domyślnymi dla tego,
     * czego zapis jeszcze nie zna.
     *
     * @return array<string,bool|int>
     */
    public function forVariant(string $variant): array
    {
        $variant = isset(self::VARIANTS[$variant]) ? $variant : self::TILE;

        $zapisane = json_decode((string) Configuration::get(self::PREFIX . strtoupper($variant)), true);
        $zapisane = is_array($zapisane) ? $zapisane : [];

        $wynik = [];

        foreach (self::CATALOGUE as $id => $element) {
            if (!$this->inVariant($id, $variant)) {
                continue;
            }

            if (!empty($element['fixed'])) {
                $wynik[$id] = true;

                continue;
            }

            if (isset($element['number'])) {
                $zakres = $element['range'] ?? [0, 99];
                $wartosc = (int) ($zapisane[$id] ?? self::DEFAULTS[$variant][$id] ?? $element['number']);
                $wynik[$id] = max($zakres[0], min($zakres[1], $wartosc));

                continue;
            }

            $wynik[$id] = (bool) ($zapisane[$id] ?? self::DEFAULTS[$variant][$id] ?? false);
        }

        $rodzaje = is_array($zapisane['badge_kinds'] ?? null) ? $zapisane['badge_kinds'] : [];
        $wynik['badge_kinds'] = [];

        foreach (array_keys(self::BADGE_KINDS) as $rodzaj) {
            $wynik['badge_kinds'][$rodzaj] = (bool) ($rodzaje[$rodzaj] ?? true);
        }

        return $wynik;
    }

    /**
     * Co silnik podaje szablonowi: oba rodzaje naraz.
     *
     * Zajawka nie wie z góry, w którym trybie stoi: ten sam szablon renderuje
     * kafel i rząd, a o tym, co widać, decyduje arkusz. Dostaje więc oba
     * zestawy.
     *
     * @return array<string,array<string,bool|int>>
     */
    public function forTemplate(): array
    {
        $wynik = [];
        $klasy = [];

        foreach (array_keys(self::VARIANTS) as $variant) {
            $zestaw = $this->forVariant($variant);

            // Typy plakietek rdzenia, których ten rodzaj zajawki nie pokazuje.
            $zestaw['hidden_flags'] = [];

            foreach (self::BADGE_KINDS as $rodzaj => $opis) {
                if (!$zestaw['badge_kinds'][$rodzaj]) {
                    array_push($zestaw['hidden_flags'], ...$opis['types']);
                }
            }

            foreach (self::SHARED as $id) {
                if (array_key_exists($id, $zestaw) && !$zestaw[$id]) {
                    $klasy[] = 'q-pcard--' . $variant . '-no-' . $id;
                }
            }

            $wynik[$variant] = $zestaw;
        }

        // Gotowy ciąg klas dla <article>: szablon zajawki nie musi go składać
        // przy każdej karcie.
        $wynik['classes'] = implode(' ', $klasy);

        return $wynik;
    }

    /**
     * Zapis z wysyłki ekranu. Każdy rodzaj ma własny klucz konfiguracji.
     *
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        foreach (array_keys(self::VARIANTS) as $variant) {
            if (!array_key_exists($variant, $values) || !is_array($values[$variant])) {
                continue;
            }

            $wyslane = $values[$variant];
            $czyste = [];

            foreach (self::CATALOGUE as $id => $element) {
                if (!$this->inVariant($id, $variant) || !empty($element['fixed'])) {
                    continue;
                }

                if (isset($element['number'])) {
                    $zakres = $element['range'] ?? [0, 99];
                    $czyste[$id] = max($zakres[0], min($zakres[1], (int) ($wyslane[$id] ?? $element['number'])));

                    continue;
                }

                $czyste[$id] = !empty($wyslane[$id]);
            }

            $rodzaje = is_array($wyslane['badge_kinds'] ?? null) ? $wyslane['badge_kinds'] : [];
            $czyste['badge_kinds'] = [];

            foreach (array_keys(self::BADGE_KINDS) as $rodzaj) {
                $czyste['badge_kinds'][$rodzaj] = !empty($rodzaje[$rodzaj]);
            }

            ConfigStore::set(self::PREFIX . strtoupper($variant), (string) json_encode($czyste), true);
        }
    }

    /** @return string[] klucze, które ten ekran zapisuje w konfiguracji */
    public static function configurationKeys(): array
    {
        return array_map(
            static fn (string $v): string => self::PREFIX . strtoupper($v),
            array_keys(self::VARIANTS)
        );
    }
}
