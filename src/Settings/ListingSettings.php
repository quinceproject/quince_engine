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
 * Układ listingu: strefy, elementy, kolejność, umiejscowienie filtrów
 * i miejsca promocyjne w siatce.
 *
 * Jeden widok, siedem kontrolerów. PrestaShop renderuje listę produktów na
 * kategorii, marce, dostawcy, w wyszukiwarce, w nowościach, promocjach
 * i bestsellerach, ale wszystkie siedem szablonów rozszerza jeden
 * `catalog/listing/product-list.tpl` i różnią się wyłącznie blokiem nagłówka.
 * Dlatego ustawienia są wspólne, a elementy, które mają sens tylko na
 * kategorii, na pozostałych kontrolerach nie mają czego wyrenderować i gasną
 * same.
 *
 * Strefy nazywamy rolą, nie pozycją, tak samo jak na stronie produktu.
 *
 * Filtry mają własną strefę, a nie miejsce w belce, bo ich położenie jest
 * ustawieniem z trzema wartościami, a nie kolejnością przeciągania.
 *
 * Spis pochodzi z makiety `quince/makiety/kategoria.html` oraz z szablonów
 * listingu PrestaShop 9.2 i Hummingbirda. Pełne uzasadnienie i inwentarz
 * hooków: docs/25-strefy-i-elementy-listingu.md.
 */
final class ListingSettings
{
    private const PREFIX = 'QUINCE_LISTING_';

    /** Filtry w oknie wysuwanym z boku; lista bierze całą szerokość. */
    public const FILTERS_DRAWER = 'drawer';

    /** Filtry w kolumnie obok listy, szerokiej dokładnie na jedną komórkę siatki. */
    public const FILTERS_COLUMN = 'column';

    /** Filtry w pasie nad listą, rozwijanym przyciskiem w belce. */
    public const FILTERS_BAR = 'bar';

    /** Widok siatki: kafle w rzędach. */
    public const VIEW_GRID = 'grid';

    /** Widok listy: jeden produkt na wiersz, z opisem i cechami. */
    public const VIEW_LIST = 'list';

    /** @var array<string,string> */
    public const VIEWS = [
        self::VIEW_GRID => 'Tiles',
        self::VIEW_LIST => 'Rows',
    ];

    /** Oba widoki do wyboru przez klienta. */
    public const MODE_BOTH = 'both';

    /** Ponumerowane strony, każda pod własnym adresem. */
    public const PAGINATION_PAGES = 'pages';

    /** Przycisk dokładający kolejną porcję produktów do tej samej strony. */
    public const PAGINATION_MORE = 'more';

    /**
     * Jak klient dochodzi do dalszych produktów.
     *
     * Ponumerowane strony mają własne adresy, więc wyszukiwarka je indeksuje,
     * a klient może wrócić do konkretnej. Dokładanie trzyma wszystko pod jednym
     * adresem i czyta się płynniej, ale nie ma do czego wrócić ani czego
     * zaindeksować poza pierwszą porcją.
     *
     * @var array<string,string>
     */
    public const PAGINATION_MODES = [
        self::PAGINATION_PAGES => 'Numbered pages',
        self::PAGINATION_MORE => 'Load more button',
    ];

    /**
     * Które widoki listy sklep w ogóle daje.
     *
     * Przy jednym widoku przełącznik w belce znika: sklep, który sprzedaje
     * odzież, nie ma po co pokazywać rzędu z tabelą cech, a sklep z częściami
     * może nie chcieć kafelków. Zostawiony przełącznik przy jednym widoku
     * byłby przyciskiem, który nic nie zmienia.
     *
     * @var array<string,string>
     */
    public const VIEW_MODES = [
        self::MODE_BOTH => 'Tiles and rows, the customer chooses',
        self::VIEW_GRID => 'Tiles only',
        self::VIEW_LIST => 'Rows only',
    ];

    public const FILTER_PLACEMENTS = [
        self::FILTERS_DRAWER => 'Drawer',
        self::FILTERS_COLUMN => 'Column beside the list',
        self::FILTERS_BAR => 'Bar above the list',
    ];

    /**
     * Strefy w kolejności, w jakiej stoją na stronie.
     *
     * @var array<string,array{label:string,note:string,jedenWWierszu?:bool}>
     */
    private const ZONES = [
        // Nazwa mówi o położeniu względem listy, a nie „nagłówek": nagłówek
        // sklepu jest osobną sekcją panelu i to samo słowo w dwóch miejscach
        // czytało się jak zarządzanie nagłówkiem sklepu z ekranu listingu.
        'header' => [
            'label' => 'Above the list',
            'note' => 'Full width above the list and the filters.',
        ],
        // Własna strefa, choć stoi w niej jeden element: filtry mają ustawienie
        // położenia, więc szablon musi wiedzieć o nich osobno od reszty.
        'filters' => [
            'label' => 'Filters',
            'note' => 'Where the shopper narrows the list down.',
            'jedenWWierszu' => true,
        ],
        'toolbar' => [
            'label' => 'Toolbar',
            'note' => 'The row above the list: how many products, how they are sorted, what is narrowed down.',
        ],
        'grid' => [
            'label' => 'Product grid',
            'note' => 'The list itself.',
            // Siatka układa kafle sama. Drugi element obok niej nie ma gdzie stanąć.
            'jedenWWierszu' => true,
        ],
        'footer' => [
            'label' => 'Below the list',
            'note' => 'Full width under the list.',
        ],
    ];

    /**
     * Katalog elementów.
     *
     * `zone` to strefa domyślna, `zones` to miejsca dozwolone — pusta tablica
     * znaczy „tylko tam, gdzie stoi domyślnie". `fixed` znaczy, że elementu nie
     * da się wyłączyć: bez siatki i bez tytułu nie ma listingu.
     *
     * `off` znaczy, że element po instalacji jest wyłączony.
     *
     * @var array<string,array{label:string,zone:string,zones:string[],fixed?:bool,off?:bool,options?:array<string,bool|int>}>
     */
    private const CATALOGUE = [
        // --- nagłówek ---
        'breadcrumb' => ['label' => 'Breadcrumbs', 'zone' => 'header', 'zones' => []],

        'title' => [
            'label' => 'Listing title',
            'zone' => 'header',
            'zones' => [],
            // Tytuł bierze się z kontrolera: nazwa kategorii, marki, dostawcy
            // albo szukana fraza. Strona bez tytułu nie mówi, co pokazuje.
            'fixed' => true,
        ],

        'description' => [
            'label' => 'Description',
            'zone' => 'header',
            'zones' => ['header', 'footer'],
            'options' => [
                // Rdzeń pokazuje opis tylko na pierwszej stronie stronicowania.
                // Zostawiamy to jako wybór, bo opis bywa też zapleczem SEO,
                // które ma stać na każdej stronie.
                'first_page' => true,
            ],
        ],

        // Zdjęcie kategorii stoi OBOK tytułu i opisu, w kolumnie tej samej
        // szerokości co kafelek podkategorii pod spodem. Makieta tego elementu
        // nie pokazuje; układ ustaliliśmy na osobnym podglądzie
        // `makiety/kategoria-naglowek-ze-zdjeciem.html`.
        'cover' => [
            'label' => 'Category image',
            'zone' => 'header',
            'zones' => ['header'],
            'off' => true,
            'options' => [
                // Osobno dla szerokiego ekranu i osobno dla telefonu, bo to dwie
                // różne decyzje: na szerokim zdjęcie zajmuje miejsce, którego
                // i tak nie ma czym wypełnić, a na telefonie odbiera je tytułowi.
                'desktop' => true,
                'mobile' => true,
            ],
        ],

        'subcategories' => [
            'label' => 'Subcategories',
            'zone' => 'header',
            'zones' => ['header', 'footer'],
            'options' => [
                'images' => true,
                // Zdjęcie jako maska malowana barwą tekstu, a po najechaniu
                // barwą marki. Dla sklepów, które mają do kategorii
                // jednobarwne rysunki zamiast fotografii: bez tego trybu
                // każdy z nich zostaje w swoim kolorze i rząd nie trzyma się
                // kupy. Port z Flavora.
                'icons' => false,
                // Czy kafelek reaguje na najechanie. We Flavorze to ustawienie
                // gasiło tło pod kafelkiem, bo tam najechanie je zapalało.
                // W Quince najechanie zmienia barwę obramowania, więc włącznik
                // dotyczy obramowania — to ta sama decyzja sklepu przeniesiona
                // na to, co robi nasz motyw.
                'hover' => true,
            ],
        ],

        // Hook rdzenia. Mimo nazwy odpala się na wszystkich siedmiu
        // kontrolerach listingu, bo siedzi w szablonie bazowym. Stoi w katalogu
        // jako element, żeby sklep przechodzący na Quince nie stracił po cichu
        // modułów, które się w nim zarejestrowały.
        //
        // Etykieta niesie nazwę hooka, bo tylko ona ten element definiuje:
        // sam napis „Moduły nad listą" nie mówi, o który hook chodzi, a sklep
        // szuka właśnie nazwy.
        'hook_header' => [
            'label' => 'Modules above every list (displayHeaderCategory)',
            'zone' => 'header',
            'zones' => ['header', 'footer'],
        ],

        // --- filtry ---
        'facets' => [
            'label' => 'Filters',
            'zone' => 'filters',
            'zones' => [],
            // Treść przychodzi gotowa w `$listing.rendered_facets`, z rdzenia
            // albo z ps_facetedsearch. Silnik decyduje tylko, gdzie ją postawić.
        ],

        // --- belka ---
        'filters_button' => [
            'label' => 'Filters button',
            'zone' => 'toolbar',
            'zones' => ['toolbar'],
            'options' => [
                'count' => true,   // liczba zawężeń przy przycisku
            ],
        ],
        'count' => ['label' => 'Product count', 'zone' => 'toolbar', 'zones' => ['toolbar']],
        'sort' => ['label' => 'Sorting', 'zone' => 'toolbar', 'zones' => ['toolbar']],
        'view_switch' => ['label' => 'Grid and list view switch', 'zone' => 'toolbar', 'zones' => ['toolbar']],
        'active_filters' => [
            'label' => 'Active filters',
            'zone' => 'toolbar',
            'zones' => ['toolbar'],
            'options' => [
                'clear_all' => true,
            ],
        ],

        // --- siatka ---
        'products' => [
            'label' => 'Products',
            'zone' => 'grid',
            'zones' => [],
            'fixed' => true,
            'options' => [
                // Ile cech pokazuje zajawka w widoku listy. Ranking jest ten
                // sam co w środkowej kolumnie karty produktu — osobny liczyłby
                // drugi raz to samo. Różni się tylko limit, bo w rzędzie listy
                // jest na cechy więcej miejsca niż w wąskiej kolumnie karty.
                'features' => 6,
            ],
            'ranges' => ['features' => [0, 12]],
        ],
        'empty' => [
            'label' => 'No results message',
            'zone' => 'grid',
            'zones' => [],
            'fixed' => true,
        ],

        // --- pod listą ---
        'pagination' => ['label' => 'Pagination', 'zone' => 'footer', 'zones' => ['footer']],

        'additional_description' => [
            'label' => 'Additional description',
            'zone' => 'footer',
            'zones' => ['header', 'footer'],
            'options' => [
                'first_page' => true,
            ],
        ],

        'hook_footer' => [
            'label' => 'Modules below every list (displayFooterCategory)',
            'zone' => 'footer',
            'zones' => ['header', 'footer'],
        ],

        // Rząd polecanych produktów. Silnik stawia miejsce, a produkty przynosi
        // moduł quince_product_section: to on wie, skąd je brać na każdej
        // z siedmiu stron z listą. Bez modułu element nie renderuje nic.
        'section' => [
            'label' => 'Recommended products',
            'zone' => 'footer',
            'zones' => ['header', 'footer'],
        ],
    ];

    /**
     * Kolejność po instalacji. Odpowiada makiecie `kategoria.html` czytanej
     * od góry.
     *
     * @var array<string,string[]>
     */
    private const DEFAULT_ORDER = [
        // Zdjęcie kategorii przed tytułem, bo stoi w kolumnie OBOK niego i obok
        // opisu. Blok otwiera się na zdjęciu i zamyka na pierwszym elemencie,
        // który do nagłówka nie należy — patrz `catalog/listing/product-list.tpl`.
        'header' => ['breadcrumb', 'cover', 'title', 'description', 'hook_header', 'subcategories'],
        'filters' => ['facets'],
        'toolbar' => ['filters_button', 'count', 'sort', 'view_switch', 'active_filters'],
        'grid' => ['products', 'empty'],
        'footer' => ['pagination', 'section', 'hook_footer', 'additional_description'],
    ];

    /** @var array<string,string> */
    private const OPTION_LABELS = [
        'first_page' => 'Only on the first page',
        'images' => 'With images',
        'icons' => 'Images as icons in the theme colour',
        'hover' => 'Border lights up on hover',
        'features' => 'How many features the list view shows',
        'count' => 'With the number of narrowed-down filters',
        'clear_all' => 'With "Clear all"',
        'desktop' => 'On wide screens',
        'mobile' => 'On phones',
    ];

    /**
     * Miejsca promocyjne w siatce.
     *
     * Wokół siatki produktów PrestaShop nie daje ani jednego hooka, więc te
     * miejsca budujemy sami. Wpis nie niesie treści: silnik stawia miejsce
     * i woła hooka, a treść daje moduł. Hook, który zwróci pusto, nie zostawia
     * dziury — miejsce nie renderuje się wcale.
     *
     * `row` to numer RZĘDU, nie kafla. Pas na całą szerokość potrzebuje wolnego
     * całego rzędu: postawiony po kaflu, który wypada w środku rzędu, przeskakuje
     * do następnego i zostawia po sobie puste komórki. Numer kafla trafia
     * w granicę rzędu tylko przy jednej liczbie kolumn, więc Flavor kazał sklepowi
     * podawać go trzy razy, osobno dla każdego progu. Numer rzędu trafia w nią
     * zawsze, przy każdej szerokości ekranu i po włączeniu kolumny filtrów,
     * bo kafel wylicza z niego silnik.
     *
     * @var array<string,string>
     */
    public const PLACEMENT_TYPES = [
        'promo' => 'One tile',
        'banner' => 'Full width',
    ];

    /** Numer rzędu liczony od początku bieżącej strony: wpis wraca na każdej. */
    public const SCOPE_PAGE = 'page';

    /** Numer rzędu liczony od początku listy: wpis pojawia się jeden raz. */
    public const SCOPE_LIST = 'list';

    /** @var array<string,string> */
    /**
     * Na jakim widoku listy miejsce się pokazuje.
     *
     * Kafelki i rzędy to dwa różne układy: pas, który między kafelkami wygląda
     * dobrze, między rzędami bywa zbędny i odwrotnie.
     *
     * @var array<string,string>
     */
    public const PLACEMENT_VIEWS = [
        self::MODE_BOTH => 'Tiles and rows',
        self::VIEW_GRID => 'Tiles only',
        self::VIEW_LIST => 'Rows only',
    ];

    /** @var array<string,string> */
    public const PLACEMENT_SCOPES = [
        self::SCOPE_PAGE => 'On every page',
        self::SCOPE_LIST => 'Once in the whole list',
    ];

    /** @var array<string,string> */
    private const SCHEMA = [
        // Rozstawienie elementów po strefach razem z hookami wstawionymi między
        // nie. JSON, bo liczba pozycji w strefie zmienia się przy każdym
        // przestawieniu.
        'ARRANGEMENT' => '',

        // Włączniki szczegółów elementów.
        'OPTIONS' => '',

        // Gdzie stoją filtry: szuflada, kolumna albo pas nad listą.
        'FILTERS' => self::FILTERS_DRAWER,

        // Który widok listy sklep pokazuje na wejściu. Klient może go przełączyć
        // i jego wybór zostaje w przeglądarce, ale pierwsze wejście decyduje
        // o tym, co zobaczy ktoś, kto nigdy tu nie był.
        'VIEW' => self::VIEW_GRID,

        // Które widoki listy sklep w ogóle daje: oba, same kafelki albo same
        // rzędy. Przy jednym przełącznik w belce znika.
        'VIEW_MODE' => self::MODE_BOTH,

        // Miejsca promocyjne w siatce. JSON, bo sklep dodaje i kasuje wpisy.
        'PLACEMENTS' => '',

        // Styl bloków: tło, ramka, cień i zaokrąglenie każdego z osobna.
        // Tak samo jak na stronie produktu.
        'SURFACES' => '',

        // Jak klient dochodzi do dalszych produktów: ponumerowane strony albo
        // przycisk dokładający kolejną porcję.
        'PAGINATION' => self::PAGINATION_PAGES,
    ];

    /**
     * Bloki, którym sklep ustawia wygląd z osobna.
     *
     * Tylko STREFY, które w prototypie są blokiem z własnym tłem: belka
     * narzędzi z pigułkami i panel filtrów. Panel filtrów ma tło wyłącznie
     * w kolumnie, więc jego zębatka pokazuje się tylko przy tym wariancie.
     *
     * Zębatka stoi w nagłówku strefy, której dotyczy. Baner też
     * jest blokiem z tłem, ale jest ELEMENTEM wewnątrz strefy, a nie strefą,
     * więc nie ma gdzie postawić jego zębatki tak, żeby było widać, czego
     * dotyczy.
     *
     * Kafelka podkategorii tu NIE MA. Ma własne tło, ale nie jest blokiem
     * strony, tylko pozycją rzędu, więc jego barwa jest włącznikiem elementu
     * „Podkategorie", a nie stylem bloku.
     *
     * Zajawki produktu też nie: jest wspólna z karuzelami modułów, więc jej
     * wygląd należy do osobnego katalogu, nie do stref listingu.
     *
     * @var string[]
     */
    private const SURFACES = ['toolbar', 'filters'];

    /** @return array<string,array{label:string,note:string,jedenWWierszu?:bool}> */
    public function zoneCatalogue(): array
    {
        return self::ZONES;
    }

    /** @return array<string,array<string,mixed>> */
    public function catalogue(): array
    {
        return self::CATALOGUE;
    }

    public function optionLabel(string $klucz): string
    {
        return self::OPTION_LABELS[$klucz] ?? $klucz;
    }

    /** Który widok listy jest domyślny. */
    public function defaultView(): string
    {
        // Przy jednym dostępnym widoku domyślny nie ma znaczenia: to ten jeden.
        $tryb = $this->viewMode();

        if ($tryb !== self::MODE_BOTH) {
            return $tryb;
        }

        $zapisany = (string) Configuration::get(self::PREFIX . 'VIEW');

        return isset(self::VIEWS[$zapisany]) ? $zapisany : self::SCHEMA['VIEW'];
    }

    /** Jak klient dochodzi do dalszych produktów. */
    public function paginationMode(): string
    {
        $zapisany = (string) Configuration::get(self::PREFIX . 'PAGINATION');

        return isset(self::PAGINATION_MODES[$zapisany]) ? $zapisany : self::SCHEMA['PAGINATION'];
    }

    /** Które widoki listy sklep daje: oba, same kafelki albo same rzędy. */
    public function viewMode(): string
    {
        $zapisany = (string) Configuration::get(self::PREFIX . 'VIEW_MODE');

        return isset(self::VIEW_MODES[$zapisany]) ? $zapisany : self::SCHEMA['VIEW_MODE'];
    }

    /** Gdzie stoją filtry. */
    public function filtersPlacement(): string
    {
        $zapisane = (string) Configuration::get(self::PREFIX . 'FILTERS');

        return isset(self::FILTER_PLACEMENTS[$zapisane])
            ? $zapisane
            : self::SCHEMA['FILTERS'];
    }

    /**
     * Ile kolumn zostaje zajawkom na tym listingu.
     *
     * Kolumna filtrów zabiera dokładnie jedną komórkę siatki, a nie własną
     * szerokość. Inaczej ta sama zajawka byłaby na kategorii węższa niż
     * w karuzeli modułu, bo listing liczyłby kolumny z tego, co zostało,
     * a karuzela z pełnej szerokości sekcji.
     *
     * @param int $kolumny ustawienie sklepu, czyli GRID_COLUMNS z wyglądu
     */
    public function gridColumns(int $kolumny): int
    {
        return $this->filtersPlacement() === self::FILTERS_COLUMN
            ? max(1, $kolumny - 1)
            : $kolumny;
    }

    /**
     * Miejsca promocyjne w siatce, w kolejności rzędów.
     *
     * @return array<int,array{id:string,hook:string,type:string,row:int,scope:string,active:bool}>
     */
    public function placements(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'PLACEMENTS'), true);

        if (!is_array($zapisane)) {
            return [];
        }

        $wpisy = self::sanitizePlacements($zapisane);

        usort($wpisy, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $wpisy;
    }

    /**
     * @param array<mixed> $dane
     *
     * @return array<int,array{id:string,hook:string,type:string,row:int,scope:string,active:bool}>
     */
    public static function sanitizePlacements(array $dane): array
    {
        $czyste = [];

        foreach ($dane as $wpis) {
            if (!is_array($wpis)) {
                continue;
            }

            // Nazwa hooka przechodzi przez sito znaków, bo trafia do zapytania
            // o hook i do znacznika. Bez niej wpis nie ma czego wyrenderować.
            $hook = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($wpis['hook'] ?? ''));

            if ($hook === '') {
                continue;
            }

            $typ = (string) ($wpis['type'] ?? '');
            $zasieg = (string) ($wpis['scope'] ?? '');
            $widok = (string) ($wpis['views'] ?? '');

            $czyste[] = [
                'id' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($wpis['id'] ?? $hook)),
                'hook' => $hook,
                'type' => isset(self::PLACEMENT_TYPES[$typ]) ? $typ : 'promo',
                // Po której zajawce miejsce staje. Zero znaczy „przed pierwszą",
                // dwa „po drugiej", czyli miejsce jest trzecią pozycją listy.
                // Zajawki przesuwają się wtedy o jedno pole dalej.
                'position' => max(0, (int) ($wpis['position'] ?? $wpis['row'] ?? 0)),
                'scope' => isset(self::PLACEMENT_SCOPES[$zasieg]) ? $zasieg : self::SCOPE_PAGE,
                'views' => isset(self::PLACEMENT_VIEWS[$widok]) ? $widok : self::MODE_BOTH,
                'active' => !empty($wpis['active']),
            ];
        }

        return $czyste;
    }

    /**
     * Miejsca przeliczone na numery zajawek, po których mają stanąć.
     *
     * Sklep podaje POZYCJĘ: zero znaczy „przed pierwszą zajawką", dwa „po
     * drugiej", czyli miejsce jest trzecią pozycją listy, a zajawki przesuwają
     * się o jedno pole dalej. Tak samo liczył to Flavor.
     *
     * Jeden wyjątek robi pas na całą szerokość. Taki pas zajmuje cały rząd,
     * więc postawiony w jego środku przeskakuje do następnego i zostawia po
     * sobie puste komórki. Dlatego dla pasa zsuwamy pozycję do najbliższej
     * granicy rzędu w dół. Przy kaflu na jedno pole nic nie przeliczamy:
     * pozycja znaczy pozycję.
     *
     * @param int $kolumny liczba kolumn siatki NA TYM listingu, czyli już po
     *                     odjęciu kolumny filtrów
     * @param int $pominiete ile produktów pokazały wcześniejsze strony
     *
     * @return array<int,array<int,array{hook:string,type:string}>> klucz to numer
     *         zajawki, po której wchodzą wpisy; zero znaczy „przed pierwszą"
     */
    public function placementsByTile(int $kolumny, int $pominiete): array
    {
        $kolumny = max(1, $kolumny);
        $pominiete = max(0, $pominiete);
        $mapa = [];

        foreach ($this->placements() as $wpis) {
            if (!$wpis['active']) {
                continue;
            }

            $kafel = (int) $wpis['position'];

            if ($wpis['scope'] === self::SCOPE_LIST) {
                $kafel -= $pominiete;

                if ($kafel < 0) {
                    continue;
                }
            }

            // Pas na całą szerokość musi trafić na granicę rzędu. W rzędach
            // każdy wiersz ma jedną pozycję, więc granica jest wszędzie.
            if ($wpis['type'] === 'banner') {
                $kafel = intdiv($kafel, $kolumny) * $kolumny;
            }

            // Kilka wpisów może trafić w ten sam kafel — przy zasięgu „raz na
            // całej liście" zsunięcie do granicy rzędu sprowadza tu sąsiednie
            // rzędy. Stają wtedy jeden pod drugim, zamiast po cichu się kasować.
            $mapa[$kafel][] = [
                'hook' => $wpis['hook'],
                'type' => $wpis['type'],
                // Na którym widoku listy miejsce ma być widoczne. Rozstrzyga to
                // arkusz, nie silnik: widok przełącza klient w przeglądarce
                // i serwer nie wie, co ten klient ma w danej chwili na ekranie.
                'views' => $wpis['views'],
            ];
        }

        ksort($mapa);

        return $mapa;
    }

    /**
     * Rozstawienie po instalacji: strefy z elementami w kolejności z makiety.
     *
     * @return array<string,array<int,string[]>> strefa => wiersze
     */
    public function defaultArrangement(): array
    {
        $strefy = array_fill_keys(array_keys(self::ZONES), []);
        $ustawione = [];

        foreach (self::DEFAULT_ORDER as $strefa => $identyfikatory) {
            foreach ($identyfikatory as $id) {
                if (isset($ustawione[$id])) {
                    continue;
                }

                // Miejsce na cudzy moduł (`hook:<nazwa>`) nie stoi w katalogu,
                // bo katalog opisuje elementy z własnym zadaniem. Do układu
                // domyślnego wchodzi tak samo jak miejsce dodane ręcznie.
                if (!str_starts_with($id, 'hook:') && !isset(self::CATALOGUE[$id])) {
                    continue;
                }

                $strefy[$strefa][] = [$id];
                $ustawione[$id] = true;
            }
        }

        // Element, którego w spisie nie ma — nowy w katalogu — dochodzi na koniec
        // swojej strefy domyślnej, żeby nie zniknął z ekranu.
        foreach (self::CATALOGUE as $id => $element) {
            if (!isset($ustawione[$id])) {
                $strefy[$element['zone']][] = [$id];
            }
        }

        return $strefy;
    }

    /**
     * Co silnik podaje szablonowi: strefy z włączonymi elementami w kolejności
     * wyświetlania. Szablon nie wie, skąd się wzięły — przechodzi je po kolei.
     *
     * @return array<string,mixed>
     */
    public function forTemplate(): array
    {
        $strefy = [];

        foreach ($this->zones() as $strefa => $wiersze) {
            $strefy[$strefa] = [];

            foreach ($wiersze as $wiersz) {
                foreach ($wiersz as $element) {
                    if ($element['on']) {
                        $strefy[$strefa][] = $element;
                    }
                }
            }
        }

        return [
            'zones' => $strefy,

            // Włączniki szczegółów osobno, kluczem elementu. Strefy niosą je już
            // przy elementach, ale filtrowanie odświeża samą siatkę, bez stref
            // dookoła, a wtedy kawałek bez tej mapy wracałby do wartości
            // domyślnych.
            'options' => $this->options(),

            'filters' => $this->filtersPlacement(),
            'view' => $this->defaultView(),

            // Czy klient ma czym przełączać. Przy jednym widoku belka nie
            // renderuje przełącznika, a widok jest wymuszony.
            'views' => $this->viewMode(),

            // Ponumerowane strony albo przycisk dokładający kolejną porcję.
            'pagination' => $this->paginationMode(),

            // Ile kolumn ma siatka NA TYM listingu, czyli już po oddaniu jednej
            // filtrom, jeśli stoją w kolumnie. Szablon liczy z tego szerokość
            // kolumny filtrów i numer kafla przy miejscach promocyjnych.
            //
            // Czytamy wprost z konfiguracji, a nie przez `AppearanceSettings`:
            // tamta klasa bierze w konstruktorze dwa generatory, których tu nie
            // ma skąd wziąć, a potrzebna jest jedna liczba.
            'columns' => $this->gridColumns(self::gridColumnsSetting()),

            // Miejsca promocyjne zostają nieprzeliczone: numer kafla zależy
            // jeszcze od tego, ile produktów pokazały wcześniejsze strony,
            // a to wie dopiero szablon. Przelicza je modyfikator `qslots`.
            'placements' => $this->placements(),
        ];
    }

    /**
     * Miejsca promocyjne przeliczone na numery kafli — wejście dla szablonu.
     *
     * Modyfikator Smarty, nie metoda obiektu: szablon dostaje z silnika same
     * dane, a tego wyliczenia nie da się zrobić w Smarty bez przepisania go
     * drugi raz.
     *
     * @return array<int,array<int,array{hook:string,type:string}>>
     */
    public static function slots(int $kolumny, int $pominiete): array
    {
        return (new self())->placementsByTile($kolumny, $pominiete);
    }

    /**
     * Ustawienie liczby kolumn siatki z ekranu wyglądu.
     *
     * Klucz należy do `AppearanceSettings`, ale tamta klasa bierze
     * w konstruktorze generator palety i generator metryk, więc do odczytania
     * jednej liczby trzeba by zbudować pół silnika. Trzymamy więc nazwę klucza
     * w jednym miejscu tutaj i czytamy ją wprost.
     */
    private static function gridColumnsSetting(): int
    {
        $zapisane = (int) Configuration::get('QUINCE_GRID_COLUMNS');

        return in_array($zapisane, [3, 4, 5], true) ? $zapisane : 4;
    }

    /**
     * Zapisane rozstawienie.
     *
     * @return array<string,array<int,array<int,array{id:string,on:bool}>>>
     */
    private function arrangement(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'ARRANGEMENT'), true);

        return is_array($zapisane) ? $zapisane : [];
    }

    /**
     * Włączniki szczegółów, z wartościami domyślnymi dla tego, czego zapis
     * jeszcze nie zna.
     *
     * @return array<string,array<string,bool|int>>
     */
    public function options(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'OPTIONS'), true);
        $zapisane = is_array($zapisane) ? $zapisane : [];
        $wynik = [];

        foreach (self::CATALOGUE as $id => $element) {
            foreach ($element['options'] ?? [] as $klucz => $domyslna) {
                $wynik[$id][$klucz] = is_int($domyslna)
                    ? (int) ($zapisane[$id][$klucz] ?? $domyslna)
                    : (bool) ($zapisane[$id][$klucz] ?? $domyslna);
            }
        }

        return $wynik;
    }

    /**
     * Strefy z miejscami w kolejności wyświetlania — to, czym żywi się ekran
     * panelu.
     *
     * @return array<string,array<int,array<int,array<string,mixed>>>>
     */
    public function zones(): array
    {
        $zapisane = $this->arrangement();
        $opcje = $this->options();

        $uzyte = [];
        $wynik = [];

        foreach (array_keys(self::ZONES) as $strefa) {
            $wynik[$strefa] = [];

            foreach (is_array($zapisane[$strefa] ?? null) ? $zapisane[$strefa] : [] as $zapisanyWiersz) {
                $wiersz = [];

                foreach (is_array($zapisanyWiersz) ? $zapisanyWiersz : [] as $pozycja) {
                    $miejsce = $this->miejsce(is_array($pozycja) ? $pozycja : [], $strefa, $opcje, $uzyte);

                    if ($miejsce !== null) {
                        $wiersz[] = $miejsce;
                    }
                }

                if ($wiersz !== []) {
                    $wynik[$strefa][] = $wiersz;
                }
            }
        }

        // Elementy, których zapis nie zna — nowe w katalogu albo takie, które
        // dopiero co zmieniły strefę — dochodzą tam, gdzie stoją domyślnie.
        foreach ($this->defaultArrangement() as $strefa => $wiersze) {
            foreach ($wiersze as $identyfikatory) {
                foreach ($identyfikatory as $id) {
                    if (isset($uzyte[$id])) {
                        continue;
                    }

                    $uzyte[$id] = true;

                    // Miejsce na cudzy moduł nie ma wpisu w katalogu, więc nie
                    // da się go zbudować tak jak elementu z własnym zadaniem.
                    if (str_starts_with($id, 'hook:')) {
                        $wynik[$strefa][] = [[
                            'id' => $id,
                            'hook' => substr($id, 5),
                            'on' => true,
                            'label' => substr($id, 5),
                            'options' => [],
                        ]];

                        continue;
                    }

                    $wynik[$strefa][] = [$this->element($id, empty(self::CATALOGUE[$id]['off']), $opcje)];
                }
            }
        }

        return $wynik;
    }

    /**
     * Jedno miejsce w wierszu: element silnika albo hook modułu.
     *
     * Zwraca null dla pozycji, której nie wolno tu postawić — spoza katalogu,
     * powtórzonej albo w niedozwolonej strefie.
     *
     * @param array<string,mixed>                $pozycja
     * @param array<string,array<string,bool|int>> $opcje
     * @param array<string,bool>                 $uzyte
     *
     * @return array<string,mixed>|null
     */
    private function miejsce(array $pozycja, string $strefa, array $opcje, array &$uzyte): ?array
    {
        $id = (string) ($pozycja['id'] ?? '');

        if (str_starts_with($id, 'hook:')) {
            // Do użytych tak samo jak element katalogu: bez tego ten sam hook
            // dochodzi drugi raz z układu domyślnego i strona woła go dwa razy.
            $uzyte[$id] = true;

            return [
                'id' => $id,
                'hook' => substr($id, 5),
                'on' => !empty($pozycja['on']),
                'label' => substr($id, 5),
                'options' => [],
            ];
        }

        if (!isset(self::CATALOGUE[$id]) || isset($uzyte[$id]) || !$this->allowedIn($id, $strefa)) {
            return null;
        }

        $uzyte[$id] = true;

        return $this->element($id, !empty($pozycja['on']), $opcje);
    }

    /**
     * Czy element wolno położyć w tej strefie.
     *
     * Pusta lista stref dozwolonych znaczy „tylko tam, gdzie stoi domyślnie".
     */
    public function allowedIn(string $id, string $zone): bool
    {
        if (!isset(self::CATALOGUE[$id]) || !isset(self::ZONES[$zone])) {
            return false;
        }

        $dozwolone = self::CATALOGUE[$id]['zones'];

        return $dozwolone === []
            ? $zone === self::CATALOGUE[$id]['zone']
            : in_array($zone, $dozwolone, true);
    }

    /**
     * @param array<string,array<string,bool|int>> $opcje
     *
     * @return array<string,mixed>
     */
    private function element(string $id, bool $on, array $opcje): array
    {
        return [
            'id' => $id,
            'hook' => '',
            'on' => !empty(self::CATALOGUE[$id]['fixed']) ? true : $on,
            'label' => self::CATALOGUE[$id]['label'],
            'options' => $opcje[$id] ?? [],
            'requires' => '',
            'available' => true,
            'optionRequires' => [],
            'optionAvailable' => [],
            // Granice pól liczbowych na ekranie panelu.
            'optionRange' => self::CATALOGUE[$id]['ranges'] ?? [],
        ];
    }

    /**
     * Sklepy, które pokazują inny listing niż ten na ekranie panelu.
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
     * Zapis z wysyłki ekranu.
     *
     * Zapisujemy tylko to, co wysyłka faktycznie niesie. Każdy przełącznik ma
     * przed sobą pole ukryte z zerem, więc ekran przysyła komplet i te warunki
     * są zawsze spełnione. Chronią przed wywołaniem z niepełnym zestawem pól,
     * które bez nich po cichu zgasiłoby wszystkie włączniki naraz.
     *
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        if (array_key_exists('VIEW', $values)) {
            $widok = (string) $values['VIEW'];

            ConfigStore::set(
                self::PREFIX . 'VIEW',
                isset(self::VIEWS[$widok]) ? $widok : self::SCHEMA['VIEW']
            );
        }

        if (array_key_exists('PAGINATION', $values)) {
            $tryb = (string) $values['PAGINATION'];

            ConfigStore::set(
                self::PREFIX . 'PAGINATION',
                isset(self::PAGINATION_MODES[$tryb]) ? $tryb : self::SCHEMA['PAGINATION']
            );
        }

        if (array_key_exists('VIEW_MODE', $values)) {
            $tryb = (string) $values['VIEW_MODE'];

            ConfigStore::set(
                self::PREFIX . 'VIEW_MODE',
                isset(self::VIEW_MODES[$tryb]) ? $tryb : self::SCHEMA['VIEW_MODE']
            );
        }

        if (array_key_exists('FILTERS', $values)) {
            $filtry = (string) $values['FILTERS'];

            ConfigStore::set(
                self::PREFIX . 'FILTERS',
                isset(self::FILTER_PLACEMENTS[$filtry]) ? $filtry : self::SCHEMA['FILTERS']
            );
        }

        if (trim((string) ($values['ARRANGEMENT'] ?? '')) !== '') {
            ConfigStore::set(
                self::PREFIX . 'ARRANGEMENT',
                (string) json_encode($this->readArrangement($values)),
                true
            );
        }

        if (array_key_exists('option', $values)) {
            ConfigStore::set(self::PREFIX . 'OPTIONS', (string) json_encode($this->readOptions($values)), true);
        }

        if (array_key_exists('PLACEMENTS', $values)) {
            $surowe = json_decode((string) $values['PLACEMENTS'], true);

            ConfigStore::set(
                self::PREFIX . 'PLACEMENTS',
                (string) json_encode(self::sanitizePlacements(is_array($surowe) ? $surowe : [])),
                true
            );
        }

        if (array_key_exists('wyglad', $values)) {
            ConfigStore::set(
                self::PREFIX . 'SURFACES',
                (string) json_encode(SurfaceStyle::fromForm($values, self::SURFACES)),
                true
            );
        }
    }

    /**
     * Wygląd bloków listingu: tło, ramka, cień i zaokrąglenie każdego z osobna.
     *
     * @return array<string,array<string,string>>
     */
    public function surfaces(): array
    {
        return SurfaceStyle::read((string) Configuration::get(self::PREFIX . 'SURFACES'), self::SURFACES);
    }

    /**
     * Tokeny bloków do wypisania w nagłówku strony.
     *
     * @param array<string,string> $paleta    stopnie skali z generatora palety
     * @param array<string,int>    $przeskoki o ile stopni kreska odchodzi od tła
     *
     * @return array<string,string>
     */
    public function surfaceTokens(array $paleta, array $przeskoki = []): array
    {
        return SurfaceStyle::tokens($this->surfaces(), 'listing-', $paleta, $przeskoki);
    }

    /**
     * Rozstawienie z wysyłki formularza.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,array<int,array<int,array{id:string,on:bool}>>>
     */
    private function readArrangement(array $values): array
    {
        $surowe = json_decode((string) ($values['ARRANGEMENT'] ?? ''), true);
        $surowe = is_array($surowe) ? $surowe : [];
        $wynik = [];
        $uzyte = [];

        foreach (array_keys(self::ZONES) as $strefa) {
            $wynik[$strefa] = [];

            foreach (is_array($surowe[$strefa] ?? null) ? $surowe[$strefa] : [] as $surowyWiersz) {
                $wiersz = [];

                foreach (is_array($surowyWiersz) ? $surowyWiersz : [] as $pozycja) {
                    $id = is_array($pozycja) ? (string) ($pozycja['id'] ?? '') : '';

                    if ($id === '' || isset($uzyte[$id])) {
                        continue;
                    }

                    if (str_starts_with($id, 'hook:')) {
                        // Nazwa hooka trafia do `Hook::exec`, więc przepuszczamy
                        // wyłącznie to, co może nią być.
                        if (!preg_match('/^hook:[a-zA-Z][a-zA-Z0-9]{2,63}$/', $id)) {
                            continue;
                        }
                    } elseif (!$this->allowedIn($id, $strefa)) {
                        continue;
                    }

                    $uzyte[$id] = true;
                    $wiersz[] = ['id' => $id, 'on' => !empty($pozycja['on'])];
                }

                if ($wiersz !== []) {
                    $wynik[$strefa][] = $wiersz;
                }
            }
        }

        return $wynik;
    }

    /**
     * Włączniki szczegółów z wysyłki formularza.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,array<string,bool|int>>
     */
    private function readOptions(array $values): array
    {
        $wyslane = is_array($values['option'] ?? null) ? $values['option'] : [];
        $wynik = [];

        foreach (self::CATALOGUE as $id => $element) {
            foreach ($element['options'] ?? [] as $klucz => $domyslna) {
                $wynik[$id][$klucz] = is_int($domyslna)
                    ? (int) ($wyslane[$id][$klucz] ?? $domyslna)
                    : !empty($wyslane[$id][$klucz]);
            }
        }

        return $wynik;
    }

    /** @return string[] klucze, które ten ekran zapisuje w konfiguracji */
    public static function configurationKeys(): array
    {
        return array_map(
            static fn (string $klucz): string => self::PREFIX . $klucz,
            array_keys(self::SCHEMA)
        );
    }
}
