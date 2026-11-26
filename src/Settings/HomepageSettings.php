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
use Quince\Engine\Appearance\Color;
use Quince\Engine\Ui\SafeUrl;

/**
 * Strona główna: lista sekcji, z których sklep ją składa.
 *
 * Sekcja jest pasem przez stronę, podzielonym na kolumny, a w każdej kolumnie
 * stoi coś innego. Podział ma dwa sposoby i to jest tu rzecz najważniejsza:
 *
 *   KOLUMNOWY   liczony w polach siatki sklepu. Kolumna szeroka na trzy pola
 *               pokazuje trzy zajawki obok siebie, dokładnie tak jak listing.
 *               Dzięki temu zajawki na stronie głównej i na kategorii mają tę
 *               samą szerokość, a sklep nie musi tego pilnować.
 *
 *   PROCENTOWY  liczony w procentach szerokości. Potrzebny, bo siatka bywa
 *               nieparzysta: pięciu pól nie da się podzielić na pół, a dwie
 *               duże zajawki po połowie strony albo dwa bloki modułu obok
 *               siebie równo nie mają się z czym równać.
 *
 * Ostatnia kolumna zawsze bierze resztę szerokości, więc podział sumuje się
 * sam i nie da się go rozjechać.
 *
 * Ile zajawek widać w kolumnie, NIE jest ustawieniem. Przy podziale kolumnowym
 * to wprost szerokość kolumny, przy procentowym tyle całych pól siatki, ile się
 * w niej mieści. „Ile zajawek” mówi tylko, ile sztuk wziąć z katalogu; nadmiar
 * jedzie w bok albo schodzi do kolejnych rzędów.
 *
 * Zapis idzie w JSON jednym kluczem, bo liczba sekcji i kolumn zmienia się przy
 * każdym przestawieniu (tak samo jak miejsca promocyjne na listingu).
 *
 * Projekt ekranu: `quince/widoki/panel-strona-glowna.html`.
 */
final class HomepageSettings
{
    private const PREFIX = 'QUINCE_HOME_';

    /** Podział liczony w polach siatki sklepu. */
    public const MODE_GRID = 'grid';

    /** Podział liczony w procentach szerokości, niezależny od siatki. */
    public const MODE_PERCENT = 'percent';

    /** @var array<string,string> */
    public const MODES = [
        self::MODE_GRID => 'By grid columns',
        self::MODE_PERCENT => 'By percent',
    ];

    /**
     * Sufit liczby kolumn.
     *
     * Przy podziale kolumnowym i tak ogranicza go siatka: kolumn nie może być
     * więcej niż pól, bo kolumna węższa od jednego pola nie pomieści zajawki.
     * Szóstka jest granicą samego ekranu: przy siedmiu kolumnach nie zostaje
     * miejsce na żadną treść.
     */
    public const MAX_COLUMNS = 6;

    /**
     * Skale palety, z których wolno brać barwę pasa.
     *
     * Nazwa spoza tej listy skończyłaby się odwołaniem do zmiennej CSS, której
     * nie ma: pas byłby przezroczysty bez żadnego błędu.
     */
    private const SKALE = ['neutral', 'primary', 'secondary', 'promo'];

    /** Najwęższa kolumna przy podziale procentowym. */
    public const MIN_PERCENT = 10;

    /**
     * Wartości do wyboru przy podziale procentowym.
     *
     * Skok co pięć punktów, a do tego trzecie i szóste części, żeby dało się
     * równo podzielić pas na trzy albo sześć kolumn.
     */
    public const PERCENT_STEPS = [10, 15, 16, 20, 25, 30, 33, 35, 40, 45, 50, 55, 60, 65, 67, 70, 75, 80, 85, 90];

    /** Pas przez całą szerokość okna; treść i tak zostaje w pojemniku strony. */
    public const WIDTH_FULL = 'full';

    /** Blok w świetle strony: tło i zaokrąglenie kończą się razem z pojemnikiem. */
    public const WIDTH_BOXED = 'boxed';

    /** @var array<string,string> */
    public const WIDTHS = [
        // Inny napis niż „Full width” z listingów: tłumaczenie jest jedno na
        // napis, a tam znaczy „cały rząd siatki”, nie szerokość okna.
        self::WIDTH_FULL => 'Window width',
        self::WIDTH_BOXED => 'Page width',
    ];

    /**
     * Skóry sekcji.
     *
     * Barwy biorą się z palety sklepu, nie z wartości wpisanych tutaj: „ink”
     * znaczy „najciemniejszy stopień skali neutralnej”, a nie konkretną czerń.
     * Sklep, który zmieni paletę, zmienia też skóry sekcji.
     *
     * @var array<string,string>
     */
    public const SKINS = [
        'none' => 'None',
        'mist' => 'Light grey',
        'stone' => 'Grey',
        'ink' => 'Dark',
        'brand' => 'Brand colour',
        'gradient' => 'Gradient',
        'photo' => 'Photo',
    ];

    /** Kolumna pokazuje wyjście hooka: treść przychodzi z modułu. */
    public const COLUMN_HOOK = 'hook';

    /** Kolumna pokazuje zajawki produktów z katalogu. */
    public const COLUMN_PRODUCTS = 'products';

    /** Kolumna pokazuje jeden produkt jako dużą zajawkę. */
    public const COLUMN_PRODUCT = 'product';

    /** Kolumna pokazuje kafle kategorii. */
    public const COLUMN_CATEGORIES = 'categories';

    /** @var array<string,string> */
    public const COLUMN_TYPES = [
        self::COLUMN_HOOK => 'Module block',
        self::COLUMN_PRODUCTS => 'Product tiles',
        self::COLUMN_PRODUCT => 'Single product tile',
        self::COLUMN_CATEGORIES => 'Category tiles',
    ];

    /**
     * Skąd biorą się produkty w kolumnie.
     *
     * „Ostatnio oglądane” daje pustą kolumnę komuś, kto wchodzi pierwszy raz.
     * Sekcja gaśnie wtedy sama, tak samo jak przy hooku bez wyjścia.
     *
     * @var array<string,string>
     */
    public const SOURCES = [
        'newest' => 'New products',
        'bestsellers' => 'Best sellers',
        'sale' => 'On sale',
        'viewed' => 'Recently viewed',
        'searched' => 'Most searched',
        'category' => 'From a category',
        'manual' => 'Picked by hand',
    ];

    /** Czy nadmiar zajawek schodzi do kolejnych rzędów, czy jedzie w bok. */
    public const DISPLAYS = [
        'grid' => 'Rows',
        'carousel' => 'Carousel',
    ];

    /** Co stoi na kaflu kategorii. */
    public const TILE_IMAGES = [
        'photo' => 'Category photo',
        'icon' => 'Icon',
    ];

    /** Barwa ikon na kaflach: z palety sklepu, więc idzie za motywem. */
    public const ICON_COLOURS = [
        'accent' => 'Brand colour',
        'text' => 'Text colour',
        'neutral' => 'Grey',
    ];

    /**
     * Przyciemnienie zdjęcia na kaflu kategorii.
     *
     * Gotowe style biorą barwy z palety sklepu, „własny” z pól przy kolumnie.
     * Napisy i przycisk dobiera `tileShade()` pod kontrast z zasłoną.
     */
    public const TILE_SHADES = [
        'dark' => 'Dark',
        'brand' => 'Brand colour',
        'light' => 'Light',
        'custom' => 'Custom',
    ];

    /** Moc zasłony przy napisach, w procentach: dolna granica i domyślna. */
    public const SHADE_MIN = 40;
    public const SHADE_DEFAULT = 88;

    /** Widoczność zdjęcia pod zasłoną, w procentach. */
    public const PHOTO_MIN = 30;

    /** Ułożenie kafla kategorii. */
    public const TILE_DIRECTIONS = [
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
    ];

    /**
     * Gdzie stoi zdjęcie w dużej zajawce produktu.
     *
     * „Nad nazwą” działa tylko przy kilku kolumnach, „pół na pół” tylko przy
     * produkcie jedynym w sekcji (bohater pasa): dwie równe kolumny, zdjęcie
     * w jednej, treść w drugiej na barwie tła sekcji. Panel pokazuje te, które
     * w danym układzie coś zmieniają.
     */
    public const PRODUCT_IMAGES = [
        'above' => 'Above the name',
        'beside' => 'Beside the name',
        'split' => 'Half and half',
    ];

    /**
     * Ile krótkich zapewnień mieści duża zajawka.
     *
     * To NIE są cechy z katalogu. Cechy przychodzą jako para nazwa i wartość,
     * więc dałyby „Gwarancja: 5 lat”, a tu ma stać „5 lat gwarancji”. Napisy
     * wpisuje sklep.
     */
    public const MAX_EXTRAS = 4;

    /**
     * Postać zapewnień.
     *
     * Pigułka to jeden napis w ramce: „5 lat gwarancji”. Blok rozbija to samo
     * na wyróżnioną wartość i zdanie pod nią: „42” nad „złącza w komplecie,
     * od 2-pin do multipinu 40”.
     */
    public const EXTRA_PILLS = 'pills';
    public const EXTRA_BLOCKS = 'blocks';

    /** @var array<string,string> */
    public const EXTRA_STYLES = [
        self::EXTRA_PILLS => 'Pills',
        self::EXTRA_BLOCKS => 'Blocks',
    ];

    /** Ile zajawek kolumna bierze z katalogu, zanim sklep to zmieni. */
    private const DEFAULT_COUNT = 8;

    /** Najwięcej zajawek, jakie kolumna weźmie z katalogu. */
    private const MAX_COUNT = 24;

    private const SCHEMA = [
        // Lista sekcji razem z ich kolumnami. JSON, bo sklep dodaje i kasuje
        // pozycje, a ich liczba zmienia się przy każdym przestawieniu.
        'SECTIONS' => '',
    ];

    /**
     * Ile zajawek stoi w rzędzie na listingach.
     *
     * Klucz należy do `AppearanceSettings`, ale tamta klasa bierze
     * w konstruktorze generator palety i generator metryk, więc do odczytania
     * jednej liczby trzeba by zbudować pół silnika. Tak samo robi
     * `ListingSettings`: trzyma nazwę klucza u siebie i czyta ją wprost.
     */
    public static function gridColumns(): int
    {
        $zapisane = (int) Configuration::get('QUINCE_GRID_COLUMNS');

        return in_array($zapisane, [3, 4, 5], true) ? $zapisane : 4;
    }

    /**
     * Sekcje, które sklep dostaje po instalacji.
     *
     * Slajder oraz bestsellery, bo to sekcja działająca bez konfiguracji
     * katalogu.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function defaults(): array
    {
        $siatka = self::gridColumns();

        return [
            [
                'id' => 'slider',
                'active' => true,
                'mode' => self::MODE_GRID,
                'parts' => [$siatka],
                'skin' => 'none',
                'width' => self::WIDTH_FULL,
                'header' => false,
                'title' => [],
                'link' => [],
                'link_label' => [],
                'columns' => [
                    ['type' => self::COLUMN_HOOK, 'hook' => 'displayQuinceHomeSlider'],
                ],
            ],
            [
                'id' => 'bestsellers',
                'active' => true,
                'mode' => self::MODE_GRID,
                'parts' => [$siatka],
                'skin' => 'mist',
                'width' => self::WIDTH_FULL,
                'header' => true,
                'title' => [],
                'link' => [],
                'link_label' => [],
                'columns' => [
                    [
                        'type' => self::COLUMN_PRODUCTS,
                        'source' => 'bestsellers',
                        'count' => self::DEFAULT_COUNT,
                        'display' => 'carousel',
                    ],
                ],
            ],
        ];
    }

    /**
     * Sekcje strony głównej w kolejności, w jakiej mają stanąć.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sections(): array
    {
        $zapis = (string) Configuration::get(self::PREFIX . 'SECTIONS');
        $dane = trim($zapis) === '' ? null : json_decode($zapis, true);

        // Wartości domyślne przechodzą przez to samo czyszczenie co zapis, żeby
        // szablon dostawał zawsze komplet kluczy (`bleed`, barwy pasa i inne).
        return $this->sanitizeSections(is_array($dane) ? $dane : self::defaults());
    }

    /**
     * Sekcje gotowe do wyrenderowania: bez wyłączonych i z policzonymi
     * szerokościami.
     *
     * Szablon dostaje tu wszystko, czego potrzebuje do postawienia siatki:
     * udział kolumny w procentach i liczbę zajawek, które się w niej mieszczą.
     * Nie musi wiedzieć, którym sposobem podzielono sekcję.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forTemplate(): array
    {
        $siatka = self::gridColumns();
        $gotowe = [];

        foreach ($this->sections() as $sekcja) {
            if (empty($sekcja['active'])) {
                continue;
            }

            $suma = array_sum($sekcja['parts']);

            foreach ($sekcja['columns'] as $i => $kolumna) {
                $sekcja['columns'][$i]['percent'] = round($sekcja['parts'][$i] / $suma * 100, 2);
                $sekcja['columns'][$i]['visible'] = self::visibleTiles($sekcja, $i, $siatka);

                // Ile pól siatki zajmuje kolumna. Szablon liczy z tego jej
                // szerokość, zamiast dzielić pas ułamkami: pole ma wtedy
                // dokładnie tę samą szerokość co pole listingu.
                $sekcja['columns'][$i]['parts'] = self::visibleTiles($sekcja, $i, $siatka);
            }

            $gotowe[] = $sekcja;
        }

        return $gotowe;
    }

    /**
     * Ile zajawek widać w kolumnie obok siebie.
     *
     * Przy podziale kolumnowym to wprost szerokość kolumny. Przy procentowym
     * tyle całych pól siatki, ile mieści się w jej szerokości: połowa strony
     * przy pięciu zajawkach w rzędzie to dwie zajawki.
     *
     * @param array<string,mixed> $sekcja
     */
    public static function visibleTiles(array $sekcja, int $kolumna, ?int $siatka = null): int
    {
        $siatka = $siatka ?? self::gridColumns();

        if (($sekcja['mode'] ?? self::MODE_GRID) === self::MODE_GRID) {
            return max(1, (int) $sekcja['parts'][$kolumna]);
        }

        return max(1, (int) floor($siatka * $sekcja['parts'][$kolumna] / 100));
    }

    /**
     * Zapis z wysyłki ekranu.
     *
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        if (!isset($values['SECTIONS'])) {
            return;
        }

        $dane = json_decode((string) $values['SECTIONS'], true);

        ConfigStore::set(
            self::PREFIX . 'SECTIONS',
            (string) json_encode(is_array($dane) ? $this->sanitizeSections($dane) : []),
            true
        );
    }

    /**
     * Sekcje z wysyłki, obcięte do tego, co silnik potrafi postawić.
     *
     * @param array<int,mixed> $dane
     *
     * @return array<int,array<string,mixed>>
     */
    public function sanitizeSections(array $dane): array
    {
        $siatka = self::gridColumns();
        $sekcje = [];

        foreach ($dane as $wpis) {
            if (!is_array($wpis)) {
                continue;
            }

            $tryb = isset(self::MODES[$wpis['mode'] ?? '']) ? (string) $wpis['mode'] : self::MODE_GRID;

            $czesci = $this->sanitizeParts($wpis['parts'] ?? [], $tryb, $siatka);
            $kolumny = $this->sanitizeColumns($wpis['columns'] ?? [], count($czesci));

            // Nazwa własna sekcji jest identyfikatorem, nie napisem: silnik
            // rozpoznaje po niej sekcję przy zapisie wyglądu.
            $nazwa = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($wpis['id'] ?? '')));

            $sekcje[] = [
                'id' => $nazwa !== '' ? $nazwa : 'section-' . (count($sekcje) + 1),
                'active' => !empty($wpis['active']),
                'mode' => $tryb,
                'parts' => $czesci,
                'skin' => isset(self::SKINS[$wpis['skin'] ?? '']) ? (string) $wpis['skin'] : 'none',

                // Barwy pasa i zdjęcie w tle. Każde pole należy do jednego
                // rodzaju tła, więc przy pozostałych zostaje puste.
                'skin_from' => $this->barwa((string) ($wpis['skin_from'] ?? '')),
                'skin_to' => $this->barwa((string) ($wpis['skin_to'] ?? '')),
                'skin_angle' => max(0, min((int) ($wpis['skin_angle'] ?? 120), 360)),
                'skin_image' => trim((string) ($wpis['skin_image'] ?? '')),
                'skin_shade' => !empty($wpis['skin_shade']),

                // Treść od krawędzi do krawędzi: potrzebna slajderowi i pasom,
                // w których zdjęcie ma dochodzić do brzegu okna. Pozostałe pasy
                // trzymają treść w świetle strony, choćby tło szło dalej.
                'bleed' => !empty($wpis['bleed']),
                'width' => isset(self::WIDTHS[$wpis['width'] ?? '']) ? (string) $wpis['width'] : self::WIDTH_FULL,
                'header' => !empty($wpis['header']),
                // Ikona przed nagłówkiem, nieobowiązkowa. Nazwa pochodzi
                // z biblioteki silnika, więc zostają tylko znaki, z których
                // te nazwy są zbudowane.
                'icon' => preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($wpis['icon'] ?? ''))),
                'title' => $this->sanitizeI18n($wpis['title'] ?? []),
                // Adres trafia do `href` nagłówka sekcji: tylko bezpieczne schematy.
                'link' => $this->sanitizeLinks($wpis['link'] ?? []),
                'link_label' => $this->sanitizeI18n($wpis['link_label'] ?? []),
                'columns' => $kolumny,
            ];
        }

        return $sekcje;
    }

    /**
     * Szerokości kolumn.
     *
     * Ostatnia kolumna bierze resztę, więc podział zawsze sumuje się do całości,
     * także wtedy, gdy przyszedł z wysyłki policzony źle albo gdy sklep zmienił
     * liczbę zajawek w rzędzie już po zapisaniu strony.
     *
     * @param mixed $dane
     *
     * @return int[]
     */
    private function sanitizeParts($dane, string $tryb, int $siatka): array
    {
        $calosc = $tryb === self::MODE_GRID ? $siatka : 100;
        $najmniej = $tryb === self::MODE_GRID ? 1 : self::MIN_PERCENT;

        $czesci = is_array($dane) ? array_values(array_map('intval', $dane)) : [];
        $ile = max(1, min(count($czesci), self::MAX_COLUMNS));

        // Przy siatce mniejszej niż liczba kolumn nie ma czego dzielić.
        if ($tryb === self::MODE_GRID) {
            $ile = min($ile, $siatka);
        }

        $czesci = array_slice($czesci, 0, $ile);
        $wynik = [];
        $zostalo = $calosc;

        for ($i = 0; $i < $ile - 1; ++$i) {
            // Po tej kolumnie musi zostać po jednej najmniejszej części
            // dla każdej następnej.
            $najwiecej = $zostalo - ($ile - $i - 1) * $najmniej;
            $wartosc = max($najmniej, min($czesci[$i] ?? $najmniej, $najwiecej));

            $wynik[] = $wartosc;
            $zostalo -= $wartosc;
        }

        $wynik[] = max($najmniej, $zostalo);

        return $wynik;
    }

    /**
     * Kolumny sekcji, po jednej na część podziału.
     *
     * @param mixed $dane
     *
     * @return array<int,array<string,mixed>>
     */
    private function sanitizeColumns($dane, int $ile): array
    {
        $kolumny = [];

        if (is_array($dane)) {
            foreach (array_slice(array_values($dane), 0, $ile) as $wpis) {
                if (!is_array($wpis)) {
                    continue;
                }

                $rodzaj = isset(self::COLUMN_TYPES[$wpis['type'] ?? ''])
                    ? (string) $wpis['type']
                    : self::COLUMN_PRODUCTS;

                $kolumny[] = $this->sanitizeColumn($wpis, $rodzaj);
            }
        }

        // Kolumn może przyjść mniej, niż ma podział. Brakujące dostają
        // zawartość domyślną, żeby siatka nie miała dziury.
        while (count($kolumny) < $ile) {
            $kolumny[] = $this->sanitizeColumn([], self::COLUMN_PRODUCTS);
        }

        return $kolumny;
    }

    /**
     * Jedna kolumna: wyłącznie pola, które jej rodzaj naprawdę ma.
     *
     * @param array<string,mixed> $wpis
     *
     * @return array<string,mixed>
     */
    private function sanitizeColumn(array $wpis, string $rodzaj): array
    {
        if ($rodzaj === self::COLUMN_HOOK) {
            return [
                'type' => $rodzaj,
                // Nazwa hooka to identyfikator, więc zostają tylko znaki,
                // z których PrestaShop buduje nazwy hooków.
                'hook' => preg_replace('/[^A-Za-z0-9_]/', '', (string) ($wpis['hook'] ?? '')),
            ];
        }

        if ($rodzaj === self::COLUMN_PRODUCT) {
            $zapewnienia = [];

            if (is_array($wpis['extras'] ?? null)) {
                foreach (array_slice(array_values($wpis['extras']), 0, self::MAX_EXTRAS) as $zapewnienie) {
                    if (!is_array($zapewnienie)) {
                        continue;
                    }

                    $tresc = $this->sanitizeI18n($zapewnienie['text'] ?? []);

                    // Wyróżniona wartość bez treści nie mówi nic, więc o tym,
                    // czy pozycja istnieje, rozstrzyga sama treść.
                    if ($tresc === []) {
                        continue;
                    }

                    $zapewnienia[] = [
                        'lead' => $this->sanitizeI18n($zapewnienie['lead'] ?? []),
                        'text' => $tresc,
                    ];
                }
            }

            return [
                'type' => $rodzaj,
                'product' => (int) ($wpis['product'] ?? 0),
                'image' => isset(self::PRODUCT_IMAGES[$wpis['image'] ?? ''])
                    ? (string) $wpis['image']
                    : 'above',
                'extra_style' => isset(self::EXTRA_STYLES[$wpis['extra_style'] ?? ''])
                    ? (string) $wpis['extra_style']
                    : self::EXTRA_PILLS,
                'extras' => $zapewnienia,
            ];
        }

        if ($rodzaj === self::COLUMN_CATEGORIES) {
            $kategorie = is_array($wpis['categories'] ?? null)
                ? array_values(array_filter(array_map('intval', $wpis['categories'])))
                : [];

            $ikony = [];

            if (is_array($wpis['icons'] ?? null)) {
                foreach ($wpis['icons'] as $id => $nazwa) {
                    $czysta = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $nazwa));

                    if ((int) $id > 0 && $czysta !== '') {
                        $ikony[(int) $id] = $czysta;
                    }
                }
            }

            return [
                'type' => $rodzaj,
                'categories' => $kategorie,
                'image' => isset(self::TILE_IMAGES[$wpis['image'] ?? '']) ? (string) $wpis['image'] : 'photo',
                'colour' => isset(self::ICON_COLOURS[$wpis['colour'] ?? '']) ? (string) $wpis['colour'] : 'accent',
                'icons' => $ikony,
                'button' => $this->sanitizeI18n($wpis['button'] ?? []),
                'direction' => isset(self::TILE_DIRECTIONS[$wpis['direction'] ?? ''])
                    ? (string) $wpis['direction']
                    : 'horizontal',
                // Opis kategorii pod nazwą. Brak klucza (zapis sprzed 0.10.14)
                // znaczy „pokazuj”, bo tak kafel działał dotąd.
                'lead' => !array_key_exists('lead', $wpis) || !empty($wpis['lead']),
                // Przyciemnienie zdjęcia. Zapis sprzed 0.10.15 nie ma stylu:
                // wpisana barwa zasłony znaczy wtedy styl własny, pusta ciemny.
                'shade' => $this->styl($wpis),
                'shade_strength' => max(self::SHADE_MIN, min(
                    (int) ($wpis['shade_strength'] ?? self::SHADE_DEFAULT),
                    100
                )),
                'photo_strength' => max(self::PHOTO_MIN, min((int) ($wpis['photo_strength'] ?? 100), 100)),
                // Barwy stylu własnego: stopień palety albo kod.
                'veil' => $this->barwa((string) ($wpis['veil'] ?? '')),
                'frame' => $this->barwa((string) ($wpis['frame'] ?? '')),
            ];
        }

        return [
            'type' => self::COLUMN_PRODUCTS,
            'source' => isset(self::SOURCES[$wpis['source'] ?? '']) ? (string) $wpis['source'] : 'newest',
            'count' => max(1, min((int) ($wpis['count'] ?? self::DEFAULT_COUNT), self::MAX_COUNT)),
            'display' => isset(self::DISPLAYS[$wpis['display'] ?? '']) ? (string) $wpis['display'] : 'carousel',
            'category' => (int) ($wpis['category'] ?? 0),
            'manual' => is_array($wpis['manual'] ?? null)
                ? array_values(array_filter(array_map('intval', $wpis['manual'])))
                : [],
        ];
    }

    /** Barwa z palety sklepu albo kod szesnastkowy; cokolwiek innego odpada. */
    /** @param array<string,mixed> $wpis */
    private function styl(array $wpis): string
    {
        $styl = (string) ($wpis['shade'] ?? '');

        if (isset(self::TILE_SHADES[$styl])) {
            return $styl;
        }

        return trim((string) ($wpis['veil'] ?? '')) !== '' ? 'custom' : 'dark';
    }

    /**
     * Zmienne CSS kafli kategorii z ustawień przyciemnienia.
     *
     * Barwy wychodzą jako kody, nie odwołania do palety: kontrast napisu
     * i przycisku trzeba policzyć tutaj, a CSS nie umie wybrać barwy
     * po kontraście.
     *
     * - Ciemny i jasny: napisy z przeciwnego końca skali neutralnej,
     *   przycisk w barwie marki.
     * - Barwa marki: napis jak na przycisku marki (`on-fill-primary`),
     *   przycisk odwrócony, bo w barwie marki zlałby się z tłem.
     * - Własny: napis czarny albo biały, który ma większy kontrast; przycisk
     *   odwrócony, gdy barwa marki ma z zasłoną mniej niż 3:1.
     *
     * @param array<string,mixed> $kolumna
     * @param array<string,string> $paleta
     *
     * @return array<string,string> nazwa zmiennej bez `--q-` => wartość
     */
    public static function tileShade(array $kolumna, array $paleta): array
    {
        $kod = static fn (string $wartosc, string $zapas): string => self::kodBarwy($wartosc, $zapas, $paleta);

        $atrament = $kod('neutral-12', '#141414');
        $jasny = $kod('neutral-1', '#f5f5f4');
        $marka = $kod('fill-primary', '#ffb400');
        $odwrocony = false;

        switch ((string) ($kolumna['shade'] ?? 'dark')) {
            case 'brand':
                $zaslona = $marka;
                $napis = $kod('on-fill-primary', Color::labelFor($marka));
                $ramka = $napis;
                $odwrocony = true;
                break;
            case 'light':
                $zaslona = $jasny;
                $napis = $atrament;
                $ramka = $marka;
                break;
            case 'custom':
                $zaslona = $kod((string) ($kolumna['veil'] ?? ''), $atrament);
                $napis = Color::labelFor($zaslona);
                $ramka = $kod((string) ($kolumna['frame'] ?? ''), $marka);
                $odwrocony = Color::contrast($marka, $zaslona) < 3;
                break;
            default:
                $zaslona = $atrament;
                $napis = $jasny;
                $ramka = $marka;
        }

        $zmienne = [
            'cat-veil' => $zaslona,
            'cat-veil-strength' => (int) ($kolumna['shade_strength'] ?? self::SHADE_DEFAULT) . '%',
            'cat-photo-strength' => (string) round((int) ($kolumna['photo_strength'] ?? 100) / 100, 2),
            'cat-text' => $napis,
            'cat-frame' => $ramka,
        ];

        // Cień pod napisem pomaga jasnemu na zdjęciu; ciemny z cieniem
        // wygląda na rozmazany.
        if (Color::luminance($napis) < 0.5) {
            $zmienne['cat-shadow'] = 'none';
        }

        if ($odwrocony) {
            $etykieta = Color::labelFor($napis);

            // Najechanie: tło przesunięte o 15% w stronę napisu, jak
            // `--q-neutral-11` przy przycisku na skórze marki.
            [$r1, $g1, $b1] = Color::toRgb($napis);
            [$r2, $g2, $b2] = Color::toRgb($etykieta);
            $najechanie = sprintf(
                '#%02x%02x%02x',
                (int) round($r1 * 0.85 + $r2 * 0.15),
                (int) round($g1 * 0.85 + $g2 * 0.15),
                (int) round($b1 * 0.85 + $b2 * 0.15)
            );

            $zmienne['cat-btn-bg'] = $napis;
            $zmienne['cat-btn-bg-hover'] = $najechanie;
            $zmienne['cat-btn-label'] = $etykieta;
        }

        return $zmienne;
    }

    /**
     * Napisy na tle sekcji: jasne czy ciemne, i barwa nazwy produktu pod
     * kursorem.
     *
     * - Ciemny styl i zdjęcie: tekst jasny z reguł motywu, tło do liczenia
     *   to atrament (pod zdjęciem leży ciemna zasłona, zdjęcia nie znamy).
     * - Gradient: tekst jasny albo ciemny, ten, którego słabszy kontrast
     *   z dwoma końcami jest wyższy. Jasny daje klasę `q-ps--light`, a z nią
     *   reguły ciemnego pasa w motywie.
     * - Nazwa pod kursorem (tylko przy jasnym tekście): barwa marki, gdy ma
     *   co najmniej 3:1 ze wszystkimi tłami; inaczej marka rozjaśniona do
     *   3:1 wobec najjaśniejszego tła; gdy i to nie wystarcza, pusto
     *   i nazwa się nie zmienia.
     * - Pozostałe style: bez zmian, działa zwykły hover.
     *
     * @param array<string,mixed> $sekcja
     * @param array<string,string> $paleta
     *
     * @return array{light:bool,hover:string,readable:bool}
     */
    public static function sectionTone(array $sekcja, array $paleta): array
    {
        $skora = (string) ($sekcja['skin'] ?? 'none');
        $jasny = self::kodBarwy('neutral-1', '#f5f5f4', $paleta);
        $atrament = self::kodBarwy('neutral-12', '#141414', $paleta);
        $wynik = ['light' => false, 'hover' => '', 'readable' => true];

        if ($skora === 'ink' || $skora === 'photo') {
            $tla = [$atrament];
            $jasnyTekst = true;
        } elseif ($skora === 'gradient') {
            // Puste końce: te same stopnie, które motyw bierze w zastępstwie.
            $tla = [
                self::kodBarwy((string) ($sekcja['skin_from'] ?? '') ?: 'neutral-4', '#f6f6f6', $paleta),
                self::kodBarwy((string) ($sekcja['skin_to'] ?? '') ?: 'neutral-6', '#e2e1df', $paleta),
            ];
            $naJasnym = min(array_map(static fn (string $t): float => Color::contrast($jasny, $t), $tla));
            $naCiemnym = min(array_map(static fn (string $t): float => Color::contrast($atrament, $t), $tla));
            $jasnyTekst = $naJasnym > $naCiemnym;
            $wynik['light'] = $jasnyTekst;
            $wynik['readable'] = max($naJasnym, $naCiemnym) >= 4.5;
        } else {
            return $wynik;
        }

        if (!$jasnyTekst) {
            return $wynik;
        }

        $najslabszy = static fn (string $barwa): float => min(
            array_map(static fn (string $t): float => Color::contrast($barwa, $t), $tla)
        );
        $marka = self::kodBarwy('fill-primary', '#ffb400', $paleta);

        if ($najslabszy($marka) >= 3) {
            $wynik['hover'] = $marka;

            return $wynik;
        }

        usort($tla, static fn (string $a, string $b): int => Color::luminance($b) <=> Color::luminance($a));
        $przesuniety = Color::shiftToContrast($marka, $tla[0], 3.0, 0.02);
        $wynik['hover'] = $najslabszy($przesuniety) >= 3 ? $przesuniety : '';

        return $wynik;
    }

    /**
     * Stopień palety („neutral-12”) albo kod na kod szesnastkowy #rrggbb.
     *
     * @param array<string,string> $paleta
     */
    private static function kodBarwy(string $wartosc, string $zapas, array $paleta): string
    {
        $wartosc = $wartosc === '' || $wartosc[0] === '#' ? $wartosc : ($paleta[$wartosc] ?? '');

        return Color::normalize(substr($wartosc, 0, 7), $zapas);
    }

    private function barwa(string $wartosc): string
    {
        $wartosc = trim($wartosc);

        if ($wartosc === '') {
            return '';
        }

        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $wartosc) === 1) {
            return $wartosc;
        }

        return preg_match('/^(' . implode('|', self::SKALE) . ')-([1-9]|1[0-2])$/', $wartosc) === 1 ? $wartosc : '';
    }

    /**
     * Napisy w językach sklepu.
     *
     * @param mixed $dane
     *
     * @return array<int,string>
     */
    private function sanitizeI18n($dane): array
    {
        if (!is_array($dane)) {
            return [];
        }

        $napisy = [];

        foreach ($dane as $jezyk => $tekst) {
            $tresc = trim((string) $tekst);

            if ((int) $jezyk > 0 && $tresc !== '') {
                $napisy[(int) $jezyk] = $tresc;
            }
        }

        return $napisy;
    }

    /**
     * Odnośnik nagłówka sekcji, osobny dla każdego języka.
     *
     * Do wersji 0.10.10 odnośnik był jeden dla wszystkich języków i zapisywał się
     * jako napis. Taki napis rozkładamy na wszystkie języki sklepu, żeby po
     * aktualizacji nagłówek prowadził tam, gdzie prowadził.
     *
     * @param mixed $dane
     *
     * @return array<int,string>
     */
    private function sanitizeLinks($dane): array
    {
        if (is_string($dane)) {
            $adres = SafeUrl::clean($dane);

            if ($adres === '') {
                return [];
            }

            $dane = [];

            foreach (\Language::getLanguages(false) as $jezyk) {
                $dane[(int) $jezyk['id_lang']] = $adres;
            }

            return $dane;
        }

        $adresy = [];

        foreach ($this->sanitizeI18n($dane) as $jezyk => $adres) {
            $adres = SafeUrl::clean($adres);

            if ($adres !== '') {
                $adresy[$jezyk] = $adres;
            }
        }

        return $adresy;
    }

    /**
     * Napis w języku odwiedzającego, a gdy go brak, w pierwszym wypełnionym.
     *
     * @param array<int,string> $napisy
     */
    public static function text(array $napisy, int $jezyk, string $zapas = ''): string
    {
        if (isset($napisy[$jezyk]) && $napisy[$jezyk] !== '') {
            return $napisy[$jezyk];
        }

        foreach ($napisy as $tekst) {
            if ($tekst !== '') {
                return $tekst;
            }
        }

        return $zapas;
    }

    /**
     * Sklepy, które pokazują inną stronę główną niż ta na ekranie panelu.
     *
     * @return string[] nazwy sklepów
     */
    public function shopsWithOwnSettings(): array
    {
        if (!\Shop::isFeatureActive() || \Shop::getContext() === \Shop::CONTEXT_SHOP) {
            return [];
        }

        $inne = [];

        foreach (\Shop::getContextListShopID() as $idShop) {
            foreach (self::configurationKeys() as $klucz) {
                $wspolna = Configuration::get($klucz);
                $wlasna = Configuration::get($klucz, null, null, (int) $idShop);

                if (self::odczyt($wspolna) != self::odczyt($wlasna)) {
                    $inne[] = (string) (new \Shop((int) $idShop))->name;

                    break;
                }
            }
        }

        return $inne;
    }

    /** @return mixed wartość JSON rozkodowana, inna bez zmian */
    private static function odczyt($wartosc)
    {
        $rozkodowana = is_string($wartosc) ? json_decode($wartosc, true) : null;

        return is_array($rozkodowana) ? $rozkodowana : $wartosc;
    }

    /**
     * Klucze, które moduł zakłada przy instalacji i kasuje przy usunięciu.
     *
     * @return string[]
     */
    public static function configurationKeys(): array
    {
        $klucze = [];

        foreach (array_keys(self::SCHEMA) as $nazwa) {
            $klucze[] = self::PREFIX . $nazwa;
        }

        return $klucze;
    }

    /**
     * Wartości wyjściowe do zapisania przy instalacji.
     *
     * @return array<string,string>
     */
    public static function installDefaults(): array
    {
        return [
            self::PREFIX . 'SECTIONS' => (string) json_encode(self::defaults()),
        ];
    }
}
