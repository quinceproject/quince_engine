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
 * każdym przestawieniu — tak samo jak miejsca promocyjne na listingu.
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
     * Szóstka jest granicą samego ekranu — przy siedmiu kolumnach nie zostaje
     * miejsce na żadną treść.
     */
    public const MAX_COLUMNS = 6;

    /**
     * Skale palety, z których wolno brać barwę pasa.
     *
     * Nazwa spoza tej listy przechodziła walidację i kończyła się odwołaniem
     * do zmiennej, której nie ma: pas był wtedy przezroczysty bez żadnego błędu.
     */
    private const SKALE = ['neutral', 'primary', 'secondary', 'promo'];

    /** Najwęższa kolumna przy podziale procentowym. */
    public const MIN_PERCENT = 10;

    /**
     * Wartości do wyboru przy podziale procentowym.
     *
     * Skok co pięć punktów, a do tego trzecie i szóste części. Bez nich równy
     * podział na trzy albo sześć kolumn nie miał na czym stanąć i ostatnia
     * kolumna zabierała całą resztę.
     */
    public const PERCENT_STEPS = [10, 15, 16, 20, 25, 30, 33, 35, 40, 45, 50, 55, 60, 65, 67, 70, 75, 80, 85, 90];

    /** Pas przez całą szerokość okna; treść i tak zostaje w pojemniku strony. */
    public const WIDTH_FULL = 'full';

    /** Blok w świetle strony: tło i zaokrąglenie kończą się razem z pojemnikiem. */
    public const WIDTH_BOXED = 'boxed';

    /** @var array<string,string> */
    public const WIDTHS = [
        self::WIDTH_FULL => 'Full width',
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
     * „Ostatnio oglądane” daje pustą kolumnę komuś, kto wchodzi pierwszy raz —
     * sekcja gaśnie wtedy sama, tak samo jak przy hooku bez wyjścia.
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

    /** Ułożenie kafla kategorii. */
    public const TILE_DIRECTIONS = [
        'horizontal' => 'Horizontal',
        'vertical' => 'Vertical',
    ];

    /** Gdzie stoi zdjęcie w dużej zajawce produktu. */
    public const PRODUCT_IMAGES = [
        'above' => 'Above the name',
        'beside' => 'Beside the name',
    ];

    /**
     * Ile krótkich zapewnień mieści duża zajawka.
     *
     * To NIE są cechy z katalogu. Cechy przychodzą jako para nazwa i wartość,
     * więc dałyby „Gwarancja: 5 lat”, a tu ma stać „5 lat gwarancji”. Poza tym
     * połowa tych napisów nie dotyczy produktu, tylko sklepu: „szkolenie
     * w cenie”, „montaż u ciebie”, „wysyłka 24 h”. Dlatego wpisuje je sklep.
     */
    public const MAX_EXTRAS = 4;

    /**
     * Postać zapewnień.
     *
     * Pigułka to jeden napis w ramce: „5 lat gwarancji”. Blok rozbija to samo
     * na wyróżnioną wartość i zdanie pod nią: „42” nad „złącza w komplecie,
     * od 2-pin do multipinu 40”. Przełącznik jest jawny, bo to wybór układu,
     * a nie skutek uboczny tego, które pole zostało wypełnione.
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
     * Celowo dwie i celowo takie: slajder, bo bez niego strona zaczyna się od
     * pustki, oraz bestsellery, bo to sekcja działająca bez konfiguracji
     * katalogu. Reszta jest do dołożenia, a nie do skasowania — sklep, który
     * dostaje dwanaście gotowych sekcji, zaczyna od kasowania.
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
                'link' => '',
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
                'link' => '',
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

        if (trim($zapis) === '') {
            return self::defaults();
        }

        $dane = json_decode($zapis, true);

        return is_array($dane) ? $this->sanitizeSections($dane) : self::defaults();
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
                'link' => trim((string) ($wpis['link'] ?? '')),
                'link_label' => $this->sanitizeI18n($wpis['link_label'] ?? []),
                'columns' => $kolumny,
            ];
        }

        return $sekcje;
    }

    /**
     * Szerokości kolumn.
     *
     * Ostatnia kolumna bierze resztę, więc podział zawsze sumuje się do całości
     * — także wtedy, gdy przyszedł z wysyłki policzony źle albo gdy sklep zmienił
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

        // Kolumn może przyjść mniej, niż ma podział — brakujące dostają
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
