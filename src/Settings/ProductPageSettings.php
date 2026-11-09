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
 * Układ strony produktu: strefy, elementy, kolejność, włączniki szczegółów.
 *
 * Nie ma dwóch stron produktu — jest jeden model stref i dwa sposoby ich
 * rozstawienia. W układzie trzykolumnowym `opis` jest środkową kolumną;
 * w dwukolumnowym nie ma jej wcale, a jej elementy wchodzą do `zakup`, nad blok
 * cen. To nie jest ta sama strona ze zwiniętą kolumną, tylko inne przypisanie
 * elementów do stref — dlatego rozstawienie trzymamy osobno dla każdego układu.
 *
 * Strefy nazywamy rolą, nigdy pozycją. „Kolumna druga" po przełączeniu układu
 * wskazywałaby na nic.
 *
 * Trzy rzeczy, które sklep ustawia dla każdego elementu:
 *  — czy jest w ogóle (włącznik),
 *  — w której strefie i na którym miejscu stoi (przeciąganie),
 *  — co dokładnie pokazuje (włączniki szczegółów, np. czy w kodach ma być EAN).
 *
 * Między elementy można wstawić hook — wtedy blok modułu staje dokładnie tam,
 * gdzie sklep chce, a nie tam, gdzie akurat wypadł w szablonie.
 *
 * Spis elementów pochodzi z makiet `quince/makiety/strona-produktu*.html`
 * oraz z docs/17-strefy-i-elementy-strony-produktu.md.
 */
final class ProductPageSettings
{
    private const PREFIX = 'QUINCE_PDP_';

    public const LAYOUT_THREE = 'three';
    public const LAYOUT_TWO = 'two';

    /**
     * Strefy w kolejności, w jakiej stoją na stronie.
     *
     * `opis` nie istnieje w układzie dwukolumnowym — panel nie pokazuje jej jako
     * miejsca docelowego, bo nie miałaby się gdzie wyświetlić.
     *
     * @var array<string,array{label:string,note:string,layouts:string[]}>
     */
    private const ZONES = [
        // Tylko układ trzykolumnowy. Przy dwóch kolumnach nad galerią nie ma
        // czego stawiać: nazwa, ocena i reszta karty należą wtedy do panelu
        // zakupu, tak jak w makiecie `strona-produktu.html`.
        'naglowek' => [
            'label' => 'Header',
            'note' => 'Full width above the gallery and the description.',
            'layouts' => [self::LAYOUT_THREE],
        ],
        'galeria' => [
            'label' => 'Gallery',
            'note' => 'What it looks like.',
            'layouts' => [self::LAYOUT_THREE, self::LAYOUT_TWO],
        ],
        'opis' => [
            'label' => 'Description',
            'note' => 'What it is. The middle column; in the two-column layout these elements move into the buy panel.',
            'layouts' => [self::LAYOUT_THREE],
        ],
        // Odbicie strefy `naglowek`: pas przez całą kartę, tyle że pod galerią
        // i opisem. Silnik nie stawia tu nic własnego — miejsce jest po to, żeby
        // sklep wpiął tam hooka ze swoją treścią.
        //
        // Na razie tylko układ trzykolumnowy. W dwukolumnowym karta niesie samą
        // galerię, więc „szerokość dwóch kolumn" znaczy tam co innego i wraca
        // do rozstrzygnięcia osobno.
        'stopka' => [
            'label' => 'Footer',
            'note' => 'Full width under the gallery and the description.',
            'layouts' => [self::LAYOUT_THREE],
        ],
        'zakup' => [
            'label' => 'Buy panel',
            'note' => 'How much and how to buy. Sticky right column.',
            'layouts' => [self::LAYOUT_THREE, self::LAYOUT_TWO],
        ],
        'zapewnienia' => [
            // Nazwa od MIEJSCA, nie od treści: pas bierze to, co sklep w niego
            // wstawi — zwrot, dostawę, płatności, a przez hooka cokolwiek innego.
            // „Zapewnienia" opisywały jeden moduł, a nie strefę.
            'label' => 'Strip under the card',
            'note' => 'Runs under the card, as wide as the gallery and the description together. Shop-wide content: it says the same thing at every product.',
            'layouts' => [self::LAYOUT_THREE, self::LAYOUT_TWO],
            // Pas biegnie przez całą szerokość karty i sam układa swoje pozycje
            // w rzędzie. Drugie miejsce obok niego nie ma gdzie stanąć.
            'jedenWWierszu' => true,
        ],
        'sekcje' => [
            'label' => 'Sections',
            'note' => 'Long content in an accordion.',
            'layouts' => [self::LAYOUT_THREE, self::LAYOUT_TWO],
            // Pozycja akordeonu to nagłówek na całą szerokość i treść pod nim.
            // Dwie rzeczy obok siebie nie mieszczą się w tej budowie, więc każdy
            // element i każdy hook dostaje tu własny wiersz.
            'jedenWWierszu' => true,
        ],
        // Zestawy mówią o tym produkcie, więc dzielą powierzchnię z kartą.
        // Zajawki to oferta obok — dostają własną sekcję i własne tło.
        'zestawy' => [
            'label' => 'Packs',
            'note' => 'What this product comes in and what it consists of.',
            'layouts' => [self::LAYOUT_THREE, self::LAYOUT_TWO],
        ],
        'zajawki' => [
            'label' => 'Product carousels',
            'note' => 'Related, recommended, recently viewed.',
            'layouts' => [self::LAYOUT_THREE, self::LAYOUT_TWO],
        ],
    ];

    /**
     * Katalog elementów.
     *
     * `zone` to strefa domyślna, `zones` to miejsca dozwolone — pusta tablica znaczy
     * „tylko tam, gdzie stoi domyślnie". Blok cen, warianty i przyciski zakupu nie mają
     * stref dozwolonych: przeniesienie ich poza `zakup` psuje stronę, a nie zmienia
     * jej wyglądu.
     *
     * `off` znaczy, że element po instalacji jest wyłączony. `options` to włączniki
     * szczegółów wraz z ich wartościami po instalacji.
     *
     * @var array<string,array{label:string,zone:string,zones:string[],off?:bool,options?:array<string,bool>}>
     */
    private const CATALOGUE = [
        // --- galeria ---
        'gallery' => [
            'label' => 'Image with zoom',
            'zone' => 'galeria',
            'zones' => [],
            'fixed' => true,
            'options' => [
                'counter' => true,   // licznik zdjęć 1 / 8 na kadrze
                'thumbs' => true,    // pas miniatur pod kadrem, kropki na telefonie
                'video' => true,     // film z plakatem wśród zdjęć
                'video_first' => false, // film przed zdjęciami, nie za nimi
                'zoom' => true,      // powiększenie na wierzchu po kliknięciu
            ],
        ],

        // Plakietki stoją domyślnie przy opisie, bo na kadrze leżałyby na zdjęciu.
        // Sklep z jasnymi zdjęciami może je przenieść na kadr.
        'badges' => ['label' => 'Badges', 'zone' => 'opis', 'zones' => ['galeria', 'opis', 'zakup']],

        // --- nagłówek karty ---
        // Nazwa i ocena idą przez całą szerokość karty, nad galerią i opisem.
        'name' => ['label' => 'Product name', 'zone' => 'naglowek', 'zones' => ['naglowek', 'opis', 'zakup']],
        'rating' => ['label' => 'Rating with review count', 'zone' => 'naglowek', 'zones' => ['naglowek', 'opis', 'zakup']],

        // --- opis ---
        'short_description' => ['label' => 'Short description', 'zone' => 'opis', 'zones' => ['opis', 'zakup']],
        'key_features' => [
            'label' => 'Key features',
            'zone' => 'opis',
            'zones' => ['opis', 'zakup'],
            'options' => [
                // Liczba, nie przełącznik: opcje elementów przyjmują też wartość
                // liczbową i wtedy panel rysuje pole liczbowe zamiast suwaka.
                'ile' => 5,          // ile cech pokazujemy pod opisem
                'link' => true,      // odnośnik „Pełna specyfikacja" pod listą
            ],
            'ranges' => ['ile' => [3, 10]],
        ],
        'brand_sku' => [
            'label' => 'Brand and codes',
            'zone' => 'opis',
            'zones' => ['opis', 'zakup'],
            'options' => [
                'brand' => true,
                'logo' => false,      // logo producenta zamiast samej nazwy
                'sku' => true,
                'ean' => true,
                'mpn' => false,
                'condition' => false, // nowy / używany / odnowiony
            ],
        ],
        'availability' => [
            'label' => 'Availability and shipping',
            // Domyślnie w panelu zakupu, nad przyciskiem: stan i termin są
            // częścią decyzji o zakupie, a nie opisem produktu. W wąskiej kolumnie
            // idą jedną zwartą linijką zamiast wierszami tabeli.
            'zone' => 'zakup',
            'zones' => ['opis', 'zakup'],
            'options' => [
                'stock' => true,     // plakietka stanu magazynu
                'shipping' => true,  // termin wysyłki
                'quantity' => false, // liczba sztuk na stanie
            ],
        ],

        // Przyciski, za którymi stoi osobny moduł. Silnik zna ich miejsce, ale
        // dopóki modułu nie ma, pole jest martwe — po instalacji ożywa samo,
        // bez ruszania ustawień sklepu.
        'offer' => [
            'label' => 'Download a quote',
            'zone' => 'opis',
            'zones' => ['opis', 'zakup'],
            'requires' => 'quince_offers',
        ],
        'ask_expert' => [
            'label' => 'Ask an expert',
            'zone' => 'opis',
            'zones' => ['opis', 'zakup'],
            'requires' => 'quince_contacts',
        ],

        // --- zakup ---
        'promo_bar' => [
            'label' => 'Promotion bar with countdown',
            'zone' => 'zakup',
            'zones' => [],
            'fixed' => true,
            'options' => [
                'days' => 365,       // na ile dni przed końcem promocji pas się pojawia
            ],
            // Zakres pola liczbowego na ekranie. Bez niego panel wziąłby zakres
            // liczby cech i nie dałby wpisać więcej niż dziesięciu dni.
            'ranges' => ['days' => [1, 365]],
        ],
        'price' => [
            'label' => 'Price block',
            'zone' => 'zakup',
            'zones' => [],
            'options' => [
                'net' => true,       // cena netto obok brutto
                'tax_label' => true,
                'discount' => true,  // plakietka obniżki przy cenie
                'omnibus' => true,   // najniższa cena z 30 dni
                'unit' => true,      // cena za kilogram, litr, metr
                'ecotax' => false,
            ],
        ],
        'tier_prices' => ['label' => 'Quantity discounts', 'zone' => 'zakup', 'zones' => []],
        'variants' => ['label' => 'Variants', 'zone' => 'zakup', 'zones' => []],
        'customization' => ['label' => 'Product customization', 'zone' => 'zakup', 'zones' => []],
        'add_to_cart' => [
            'label' => 'Quantity and add to cart',
            'zone' => 'zakup',
            'zones' => [],
            'options' => [
                'minimal' => true,   // informacja o minimalnej ilości zakupu
            ],
        ],
        'buy_now' => [
            'label' => 'Wishlist and buy now',
            'zone' => 'zakup',
            'zones' => [],
            'options' => [
                'wishlist' => true,
                'buy_now' => true,
                'notify' => true,    // „powiadom o dostępności" w miejscu „Kup teraz"
            ],
        ],

        // --- zapewnienia ---
        'reassurance' => ['label' => 'Store assurances', 'zone' => 'zapewnienia', 'zones' => ['zapewnienia', 'zakup']],

        // --- sekcje ---
        'section_description' => ['label' => 'Full description', 'zone' => 'sekcje', 'zones' => []],
        // Jedna pozycja akordeonu, dwa bloki w środku. To dwa różne źródła
        // w PrestaShopie — stałe pola produktu i tabela cech z katalogu — ale
        // dla kupującego jest to jedno pytanie: „co to dokładnie jest". Osobne
        // zakładki kazały mu szukać w dwóch miejscach.
        'section_spec' => [
            'label' => 'Product details',
            'zone' => 'sekcje',
            'zones' => [],
            'options' => [
                'fields' => true,    // marka, referencja, stan, EAN i pozostałe kody
                'logo' => true,      // logo producenta zamiast samej nazwy
                'features' => true,  // tabela cech z katalogu
            ],
        ],
        // Składniki zestawu stoją w panelu zakupu: pytanie „co dostanę w pudełku"
        // pada przy cenie. W panelu jeden wiersz, pełna lista w szufladzie.
        'section_pack_items' => [
            'label' => 'Pack contents',
            'zone' => 'zakup',
            'zones' => [],
            'options' => [
                'thumbnail' => true,
                'sku' => true,
                'in_pack' => true,      // kolumna „× N w zestawie"
                'price' => true,
                'availability' => true,
                'item_link' => true,    // nazwa składnika prowadzi do jego karty
                'buy' => false,         // zaznaczanie składników i dodanie ich do koszyka osobno
            ],
        ],
        // Słowniki katalogowe (dopasowanie do pojazdów, numery OEM, kody silnika).
        // Treść i dobór należą do modułu słowników; tutaj stoi tylko miejsce
        // w akordeonie.
        'section_dictionaries' => [
            'label' => 'Fitment and codes',
            'note' => 'Shows at products that have dictionary entries linked to them.',
            'zone' => 'sekcje',
            'zones' => [],
            'requires' => 'quince_dictionaries',
        ],
        // Bezpieczeństwo produktu i podmiot odpowiedzialny (GPSR). Treść przynosi
        // moduł bezpieczeństwa; tutaj stoi tylko miejsce w akordeonie.
        'section_safety' => [
            'label' => 'Product safety',
            'note' => 'Shows at products that have safety information or a responsible company.',
            'zone' => 'sekcje',
            'zones' => [],
            'requires' => 'quince_gpsr',
        ],
        'section_attachments' => ['label' => 'Attachments', 'zone' => 'sekcje', 'zones' => []],
        'section_reviews' => ['label' => 'Reviews', 'zone' => 'sekcje', 'zones' => []],
        'section_faq' => ['label' => 'Questions and answers', 'zone' => 'sekcje', 'zones' => [], 'requires' => 'quince_qa'],

        // Zakładki dokładane przez moduły (`$product.extraContent`). Jedna pozycja
        // na wszystkie — sklep decyduje, czy w ogóle się pojawiają i w którym miejscu
        // akordeonu, a nie który moduł co dokłada.
        'section_modules' => ['label' => 'Tabs added by modules', 'zone' => 'sekcje', 'zones' => []],

        // --- zestawy ---
        'bundles' => ['label' => 'Packs with this product', 'zone' => 'zestawy', 'zones' => []],

        // --- zajawki ---
        // Każda karuzela pod kartą jest tu elementem i każda ma zawsze tytuł:
        // bez niego na stronie nie da się rozpoznać, który rząd jest którym.
        // `note` stoi w panelu pod nazwą: karuzela bez treści znika ze strony,
        // więc sklep musi wiedzieć, kiedy ją w ogóle zobaczy.
        'related' => [
            'label' => 'Accessories and related products',
            'note' => 'Shows at products that have related products picked in the catalogue.',
            'zone' => 'zajawki',
            'zones' => [],
            'options' => [
                'count' => 8,      // ile zajawek wchodzi na taśmę
                'nav' => true,     // strzałki przewijania
            ],
            'ranges' => ['count' => [2, 20]],
        ],
        // Produkty do dokupienia. Dobór i tytuł ustawia moduł cross-sellingu,
        // tutaj stoi tylko miejsce karuzeli i jej wygląd.
        'cross_selling' => [
            'label' => 'Products to add',
            'note' => 'Shows when Cross-selling finds products to suggest from the sources set in its settings.',
            'zone' => 'zajawki',
            'zones' => [],
            'requires' => 'quince_crossselling',
            'options' => [
                'count' => 8,
                'nav' => true,
            ],
            'ranges' => ['count' => [2, 20]],
        ],
        // Listę daje natywny moduł kategorii. Wcześniej doklejał się sam pod
        // kartą, poza panelem, więc sklep nie mógł go ani przestawić, ani wyłączyć.
        'category_products' => [
            'label' => 'Products from the same category',
            'note' => 'Shows when the main category of the product has other products.',
            'zone' => 'zajawki',
            'zones' => [],
            'requires' => 'ps_categoryproducts',
            'options' => [
                'count' => 8,
                'nav' => true,
            ],
            'ranges' => ['count' => [2, 20]],
        ],
        'recently_viewed' => [
            'label' => 'Recently viewed',
            'note' => 'Shows once the customer has looked at other products in the shop.',
            'zone' => 'zajawki',
            'zones' => [],
            'options' => [
                'count' => 8,
                'nav' => true,
            ],
            'ranges' => ['count' => [2, 20]],
        ],
    ];

    /**
     * Kolejność elementów w obszarach po instalacji — wprost z makiety panelu
     * `quince/makiety/panel-karta-produktu.html`.
     *
     * Wpisana, a nie wyliczana z porządku deklaracji w katalogu: katalog grupuje
     * elementy tematycznie i jego kolejność nie ma nic wspólnego z tym, jak mają
     * stać na stronie. Element spoza tego spisu dochodzi na koniec swojego obszaru.
     *
     * @var array<string,string[]>
     */
    private const DEFAULT_ORDER = [
        'naglowek' => ['name', 'rating'],
        'galeria' => ['gallery'],
        'opis' => ['badges', 'short_description', 'key_features', 'brand_sku', ['offer', 'ask_expert']],
        // Pusto z zamiaru: strefa czeka na hooka sklepu, nie na element silnika.
        'stopka' => [],
        // Dostępność po wariantach, a przed przyciskiem: stan zależy od wybranego
        // wariantu, a decyduje o tym, czy w ogóle kliknąć.
        'zakup' =>['promo_bar', 'price', 'tier_prices', 'section_pack_items', 'variants', 'availability', 'customization', 'add_to_cart', 'buy_now'],
        'zapewnienia' => ['reassurance'],
        'sekcje' => [
            'section_description', 'section_spec', 'section_dictionaries', 'section_safety', 'section_attachments',
            'section_reviews', 'section_faq', 'section_modules',
        ],
        'zestawy' => ['bundles'],
        'zajawki' => ['related', 'cross_selling', 'category_products', 'recently_viewed'],
    ];

    /**
     * Kolejność w strefie `zakup` przy układzie dwukolumnowym.
     *
     * Elementy opisu wchodzą tu NAD blok cen, więc to nie jest ta sama lista
     * z dołożonymi pozycjami.
     *
     * Kolejność wprost z makiety `quince/makiety/strona-produktu.html`: nazwa
     * i ocena stoją w panelu, nad kodami, a dostępność z terminem wysyłki pod
     * przyciskami zakupu, jako odpowiedź na pytanie „kiedy to dostanę", zadane
     * po decyzji o kupnie.
     *
     * @var array<int,string|string[]>
     */
    private const TWO_COLUMN_BUY_ORDER = [
        'promo_bar',
        'name', 'rating', 'brand_sku', 'short_description', 'key_features',
        'price', 'tier_prices', 'section_pack_items', 'variants', 'customization',
        'add_to_cart', 'buy_now', 'availability', ['offer', 'ask_expert'],
    ];

    /**
     * Elementy, które przy dwóch kolumnach mają inne miejsce domyślne niż zwykle.
     *
     * Plakietki leżą wtedy na zdjęciu, tak jak w makiecie: panel niesie decyzję
     * o zakupie, a „nowość" czy „−25%" należą do kadru produktu. Poza tą listą
     * elementy strefy `opis` przechodzą w tym układzie do panelu zakupu, bo
     * strefy `opis` tam nie ma.
     *
     * @var array<string,string>
     */
    private const TWO_COLUMN_ZONES = [
        'badges' => 'galeria',
    ];

    /**
     * Włączniki szczegółów, za którymi stoi osobny moduł.
     *
     * Ta sama zasada co przy elementach: pole jest widoczne od początku, ale
     * martwe, dopóki modułu nie ma. Dzięki temu sklep wie, co dostanie, a po
     * instalacji nie musi niczego szukać.
     *
     * @var array<string,array<string,string>> element => włącznik => moduł
     */
    private const OPTION_REQUIRES = [
        'price' => ['omnibus' => 'quince_omnibus'],
    ];

    /**
     * Nazwy włączników szczegółów, którymi panel mówi do sklepikarza.
     *
     * Klucze w katalogu są krótkie, bo trafiają do konfiguracji i do szablonu.
     * Na ekranie muszą stać zdaniem, a nie skrótem: „stock" nikomu nic nie mówi.
     *
     * @var array<string,string>
     */
    private const OPTION_LABELS = [
        'counter' => 'Photo counter (1 / 8)',
        'thumbs' => 'Thumbnail strip, dots on a phone',
        'video' => 'Video with a poster frame',
        'video_first' => 'Film first, before the photos',
        'zoom' => 'Zoom on click',
        'ile' => 'How many features to show',
        'link' => 'Link to the full specification',
        'brand' => 'Brand',
        'logo' => 'Manufacturer logo instead of the name',
        'sku' => 'SKU',
        'ean' => 'EAN',
        'mpn' => 'MPN',
        'condition' => 'Condition: new, used, refurbished',
        'fields' => 'Brand, reference, availability and codes',
        'features' => 'The features table from the catalogue',
        'stock' => 'Stock badge',
        'shipping' => 'Shipping time',
        'quantity' => 'Number of items in stock',
        'notify' => 'Notify me when available, instead of the buy row',
        'offer' => 'Download a quote',
        'expert' => 'Ask an expert',
        'net' => 'Net price next to gross',
        'tax_label' => 'Tax label',
        'discount' => 'Discount badge next to the price',
        'omnibus' => 'Lowest price of the last 30 days',
        'unit' => 'Unit price: per kilogram, litre, metre',
        'ecotax' => 'Ecotax',
        'minimal' => 'Minimum purchase quantity',
        'wishlist' => 'Wishlist',
        'buy_now' => 'Buy now',
        'thumbnail' => 'Thumbnail',
        'price' => 'Price',
        'availability' => 'Availability',
        'count' => 'How many products to show',
        'nav' => 'Scroll arrows',
        'in_pack' => 'Quantity in the pack',
        'item_link' => 'Component name links to its product page',
        'buy' => 'Tick components and add them to the cart separately',
        'days' => 'Show the bar this many days before the end',
    ];

    /** @var array<string,string> klucz => wartość domyślna */
    private const SCHEMA = [
        'LAYOUT' => self::LAYOUT_THREE,

        // Rozstawienie elementów po strefach, osobne dla każdego układu, razem
        // z hookami wstawionymi między nie. JSON, bo liczba pozycji w strefie
        // zmienia się przy każdym przestawieniu.
        'ARRANGEMENT' => '',

        // Włączniki szczegółów. Wspólne dla obu układów: „w kodach pokazujemy EAN"
        // jest decyzją o treści, nie o kolumnach.
        'OPTIONS' => '',
        'PHONE' => '',
        'SURFACES' => '',

        // Ile procent szerokości strony bierze panel zakupu. Wartość ze spisu
        // SPLITS, nie dowolna liczba — patrz komentarz przy tej stałej.
        'SPLIT' => '33',
    ];

    /** @return array<string,array{label:string,note:string,layouts:string[]}> */
    public function zoneCatalogue(): array
    {
        return self::ZONES;
    }

    /** @return array<string,array{label:string,zone:string,zones:string[],off?:bool,options?:array<string,bool>}> */
    public function catalogue(): array
    {
        return self::CATALOGUE;
    }

    /**
     * Sklepy, które pokazują inną stronę produktu niż ta na ekranie panelu.
     *
     * Panel otwarty na „Wszystkie sklepy" albo na grupę pokazuje ustawienie
     * wspólne. Sklep, któremu ktoś zapisał układ z jego własnego kontekstu, czyta
     * swój wiersz i wygląda inaczej, a ekran nic o tym nie mówi. Przy jednym
     * sklepie ekran pokazuje dokładnie jego ustawienia, więc lista jest pusta.
     *
     * Porównujemy odczytane dane, a nie napisy: ten sam układ zapisany w innej
     * kolejności kluczy nie jest różnicą.
     *
     * @return string[] nazwy sklepów
     */
    public function shopsWithOwnSettings(): array
    {
        if (!\Shop::isFeatureActive() || \Shop::getContext() === \Shop::CONTEXT_SHOP) {
            return [];
        }

        $klucze = array_map(static fn (string $k): string => self::PREFIX . $k, array_keys(self::SCHEMA));
        $inne = [];

        foreach (\Shop::getContextListShopID() as $idShop) {
            foreach ($klucze as $klucz) {
                if (self::odczyt(Configuration::get($klucz)) != self::odczyt(Configuration::get($klucz, null, null, (int) $idShop))) {
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
     * Nazwa włącznika szczegółów do pokazania na ekranie.
     *
     * @return string klucz, gdy nazwy jeszcze nie spisano — lepiej pokazać klucz
     *                niż puste miejsce
     */
    public function optionLabel(string $klucz): string
    {
        return self::OPTION_LABELS[$klucz] ?? $klucz;
    }

    public function layout(): string
    {
        $zapisany = (string) Configuration::get(self::PREFIX . 'LAYOUT');

        return in_array($zapisany, [self::LAYOUT_THREE, self::LAYOUT_TWO], true)
            ? $zapisany
            : self::SCHEMA['LAYOUT'];
    }

    /**
     * Szerokości panelu zakupu, do wyboru w panelu.
     *
     * Cztery wartości, nie suwak: szerokość prawej kolumny wynika z tego, ile
     * miejsca potrzebuje wiersz „ilość + do koszyka", żeby się nie łamał, a nie
     * z dowolnego upodobania. Spis pochodzi ze stanów widoku w prototypie.
     *
     * @var string[]
     */
    public const SPLITS = ['37.5', '33', '30', '25'];

    /**
     * To samo przy dwóch kolumnach, z połową szerokości na dokładkę.
     *
     * Panel niesie tam całą kartę produktu, z nazwą i opisem, więc bywa równy
     * galerii. Przy trzech kolumnach połowa strony nie wchodzi w grę: galerii
     * i opisowi zostałoby po ćwiartce.
     *
     * @var string[]
     */
    public const SPLITS_TWO = ['50', '37.5', '33', '30', '25'];

    /**
     * Szerokości do wyboru w danym układzie.
     *
     * @return string[]
     */
    public function splitsFor(string $layout): array
    {
        return $layout === self::LAYOUT_TWO ? self::SPLITS_TWO : self::SPLITS;
    }

    /**
     * Ile procent szerokości strony bierze panel zakupu.
     *
     * Wartość jest osobna dla każdego układu, tak jak rozstawienie elementów:
     * połowa strony ma sens przy dwóch kolumnach, a przy trzech już nie, więc
     * jedna wspólna liczba przenosiłaby ustawienie tam, gdzie nie wolno go użyć.
     * Zapis sprzed rozdzielenia jest jedną liczbą i wtedy dotyczy obu układów.
     */
    public function split(?string $layout = null): string
    {
        $layout = $layout ?: $this->layout();
        $zapisane = (string) Configuration::get(self::PREFIX . 'SPLIT');
        $mapa = json_decode($zapisane, true);
        $wartosc = is_array($mapa) ? (string) ($mapa[$layout] ?? '') : $zapisane;

        return in_array($wartosc, $this->splitsFor($layout), true) ? $wartosc : self::SCHEMA['SPLIT'];
    }

    /**
     * Strefy układu wraz z elementami w kolejności wyświetlania.
     *
     * Źródłem jest zapisane rozstawienie, ale katalog rozstrzyga: element, którego
     * w katalogu nie ma, wypada (bo zniknął z motywu), a element, którego nie ma
     * w zapisie, dochodzi na koniec swojej strefy domyślnej (bo właśnie go dodaliśmy).
     * Dzięki temu aktualizacja motywu nie wymaga od sklepu żadnego ruchu.
     *
     * @return array<string,array<int,array{id:string,hook:string,on:bool,label:string,options:array<string,bool>}>>
     */
    public function zones(?string $layout = null): array
    {
        $layout = $layout ?: $this->layout();
        $zapisane = $this->arrangement()[$layout] ?? [];
        $opcje = $this->options();

        $uzyte = [];
        $wynik = [];

        foreach ($this->zonesFor($layout) as $strefa) {
            $wynik[$strefa] = [];

            foreach (is_array($zapisane[$strefa] ?? null) ? $zapisane[$strefa] : [] as $zapisanyWiersz) {
                $wiersz = [];

                foreach (is_array($zapisanyWiersz) ? $zapisanyWiersz : [] as $pozycja) {
                    $miejsce = $this->miejsce($pozycja, $strefa, $layout, $opcje, $uzyte);

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
        // dopiero co zmieniły strefę — dochodzą tam, gdzie stoją domyślnie,
        // każdy w osobnym wierszu.
        foreach ($this->defaultArrangement($layout) as $strefa => $wiersze) {
            foreach ($wiersze as $identyfikatory) {
                $wiersz = [];

                foreach ($identyfikatory as $id) {
                    if (isset($uzyte[$id])) {
                        continue;
                    }

                    $uzyte[$id] = true;
                    $wiersz[] = $this->element($id, empty(self::CATALOGUE[$id]['off']), $opcje);
                }

                if ($wiersz !== []) {
                    $wynik[$strefa][] = $wiersz;
                }
            }
        }

        return $wynik;
    }

    /**
     * Jedno miejsce w wierszu: element silnika albo hook modułu.
     *
     * Zwraca null dla pozycji, której nie wolno tu postawić — element spoza
     * katalogu (zniknął z motywu), powtórzony albo w niedozwolonej strefie.
     *
     * @param array<string,mixed>              $pozycja
     * @param array<string,array<string,bool>> $opcje
     * @param array<string,bool>               $uzyte
     *
     * @return array{id:string,hook:string,on:bool,label:string,options:array<string,bool>}|null
     */
    private function miejsce(array $pozycja, string $strefa, string $layout, array $opcje, array &$uzyte): ?array
    {
        $id = (string) ($pozycja['id'] ?? '');

        if (str_starts_with($id, 'hook:')) {
            return [
                'id' => $id,
                'hook' => substr($id, 5),
                'on' => !empty($pozycja['on']),
                'label' => substr($id, 5),
                'options' => [],
            ];
        }

        if (!isset(self::CATALOGUE[$id]) || isset($uzyte[$id])) {
            return null;
        }
        if (!$this->allowedIn($id, $strefa, $layout)) {
            return null;
        }

        $uzyte[$id] = true;

        return $this->element($id, !empty($pozycja['on']), $opcje);
    }

    /**
     * Czy element wolno położyć w tej strefie w tym układzie.
     *
     * Pusta lista stref dozwolonych znaczy „tylko tam, gdzie stoi domyślnie".
     * W układzie dwukolumnowym `opis` nie istnieje, więc jej miejsce zajmuje `zakup`.
     */
    public function allowedIn(string $id, string $zone, ?string $layout = null): bool
    {
        if (!isset(self::CATALOGUE[$id])) {
            return false;
        }

        $layout = $layout ?: $this->layout();
        $element = self::CATALOGUE[$id];
        $dozwolone = $element['zones'] ?: [$element['zone']];

        if ($layout === self::LAYOUT_TWO) {
            $dozwolone = array_map(
                fn (string $s): string => $this->zoneFor($id, $s, $layout),
                $dozwolone
            );
        }

        return in_array($zone, array_unique($dozwolone), true);
    }

    /**
     * Strefa elementu w danym układzie.
     *
     * Przy dwóch kolumnach nie ma strefy `opis`: jej elementy wchodzą do panelu
     * zakupu, poza tymi, którym makieta wyznacza inne miejsce (TWO_COLUMN_ZONES).
     */
    private function zoneFor(string $id, string $strefa, string $layout): string
    {
        if ($layout !== self::LAYOUT_TWO) {
            return $strefa;
        }

        if ($strefa === 'opis') {
            return self::TWO_COLUMN_ZONES[$id] ?? 'zakup';
        }

        return $strefa;
    }

    /**
     * Rozstawienie po instalacji — i punkt odniesienia dla elementów, których
     * zapisane rozstawienie nie zna.
     *
     * @return array<string,string[]>
     */
    public function defaultArrangement(string $layout): array
    {
        $strefy = array_fill_keys($this->zonesFor($layout), []);
        $ustawione = [];

        // Pozycja spisu może być tablicą — wtedy te elementy stoją w jednym
        // wierszu, obok siebie. Stąd wynik jest listą WIERSZY, nie elementów.
        $dopisz = function (string $strefa, array $identyfikatory) use (&$strefy, &$ustawione): void {
            $wiersz = [];

            foreach ($identyfikatory as $id) {
                if (isset(self::CATALOGUE[$id]) && !isset($ustawione[$id])) {
                    $wiersz[] = $id;
                    $ustawione[$id] = true;
                }
            }

            if ($wiersz !== []) {
                $strefy[$strefa][] = $wiersz;
            }
        };

        if ($layout === self::LAYOUT_TWO) {
            foreach (self::TWO_COLUMN_BUY_ORDER as $pozycja) {
                $dopisz('zakup', is_array($pozycja) ? $pozycja : [$pozycja]);
            }
        }

        foreach (self::DEFAULT_ORDER as $strefa => $pozycje) {
            foreach ($pozycje as $pozycja) {
                $identyfikatory = is_array($pozycja) ? $pozycja : [$pozycja];

                // Miejsce liczymy per element, bo przy dwóch kolumnach część
                // elementów strefy `opis` ma własne miejsce (plakietki na
                // galerii), a reszta idzie do panelu zakupu.
                foreach ($identyfikatory as $id) {
                    $docelowa = $this->zoneFor($id, $strefa, $layout);

                    if (isset($strefy[$docelowa])) {
                        $dopisz($docelowa, [$id]);
                    }
                }
            }
        }

        // Element, którego w spisie nie ma — nowy w katalogu — dochodzi na koniec
        // swojego obszaru domyślnego, żeby nie zniknął z ekranu.
        foreach (self::CATALOGUE as $id => $element) {
            if (isset($ustawione[$id])) {
                continue;
            }

            $dopisz($this->zoneFor($id, $element['zone'], $layout), [$id]);
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
        $layout = $this->layout();
        $strefy = [];

        // Dwa widoki tego samego: `zones` to elementy po kolei, a `rows` niesie
        // podział na wiersze. Motyw bierze wiersze tam, gdzie sklep może postawić
        // dwa elementy obok siebie (para „Pobierz ofertę" i „Zapytaj specjalistę"),
        // a płaską listę tam, gdzie każdy element i tak zajmuje cały wiersz.
        $wierszeStref = [];

        foreach ($this->zones($layout) as $strefa => $wiersze) {
            $strefy[$strefa] = [];
            $wierszeStref[$strefa] = [];

            foreach ($wiersze as $wiersz) {
                $wlaczone = array_values(array_filter(
                    $wiersz,
                    static fn (array $element): bool => (bool) $element['on']
                ));

                if ($wlaczone === []) {
                    continue;
                }

                $wierszeStref[$strefa][] = $wlaczone;

                foreach ($wlaczone as $element) {
                    $strefy[$strefa][] = $element;
                }
            }
        }

        $panel = (float) $this->split();

        return [
            'layout' => $layout,
            'zones' => $strefy,
            'rows' => $wierszeStref,
            // Włączniki szczegółów osobno, kluczem elementu. Strefy niosą je już
            // przy elementach, ale PrestaShop po zmianie wariantu renderuje same
            // kawałki karty — cenę, warianty, dostępność — bez strefy dookoła.
            // Kawałek, który dostawał ustawienia od strefy, po takim odświeżeniu
            // wracał do wartości domyślnych.
            'options' => $this->options(),
            // Siatka strony ma dwa tory, więc proporcja jest jedną liczbą: ile
            // zabiera panel zakupu. Podział na galerię i opis dzieje się wewnątrz
            // lewego toru i nie dotyczy siatki strony.
            'split' => [
                'main' => 100 - $panel,
                'panel' => $panel,
            ],
            'phone' => $this->phone(),
        ];
    }

    /**
     * Zapisuje układ, podział szerokości, rozstawienie i włączniki szczegółów.
     *
     * Rozstawienie przychodzi jako JSON, bo panel przestawia elementy przeciąganiem
     * i wysyła cały stan strefy naraz. Zapisujemy je pod układem, którego dotyczy —
     * drugi układ zostaje nietknięty i czeka ze swoim własnym ustawieniem.
     *
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        $layout = in_array($values['LAYOUT'] ?? '', [self::LAYOUT_THREE, self::LAYOUT_TWO], true)
            ? (string) $values['LAYOUT']
            : self::SCHEMA['LAYOUT'];

        ConfigStore::set(self::PREFIX . 'LAYOUT', $layout);

        // Szerokość panelu zapisujemy pod układem, którego dotyczy — tak samo
        // jak rozstawienie. Zapis sprzed rozdzielenia jest jedną liczbą, więc
        // przy pierwszym zapisie staje się wartością obu układów.
        $split = (string) ($values['SPLIT'] ?? '');
        $zapisane = (string) Configuration::get(self::PREFIX . 'SPLIT');
        $mapa = json_decode($zapisane, true);

        if (!is_array($mapa)) {
            $poprzednia = in_array($zapisane, self::SPLITS_TWO, true) ? $zapisane : self::SCHEMA['SPLIT'];
            $mapa = [self::LAYOUT_THREE => $poprzednia, self::LAYOUT_TWO => $poprzednia];
        }

        $mapa[$layout] = in_array($split, $this->splitsFor($layout), true) ? $split : self::SCHEMA['SPLIT'];

        ConfigStore::set(self::PREFIX . 'SPLIT', (string) json_encode($mapa), true);

        // Puste pole znaczy „nie ruszaj rozstawienia" — tak wysyła je przełącznik
        // układu, który tylko otwiera drugi układ. Nadpisanie zapisanego układu
        // tym, co akurat stało na ekranie, zabrałoby sklepowi jego ustawienia.
        if (trim((string) ($values['ARRANGEMENT'] ?? '')) !== '') {
            $rozstawienie = $this->arrangement();
            $rozstawienie[$layout] = $this->readArrangement($values, $layout);
            ConfigStore::set(self::PREFIX . 'ARRANGEMENT', (string) json_encode($rozstawienie), true);
        }

        // Zapisujemy tylko to, co wysyłka faktycznie niesie.
        //
        // Każdy przełącznik ma przed sobą pole ukryte z zerem, więc formularz
        // ekranu przysyła komplet i te warunki są zawsze spełnione. Chronią
        // przed czymś innym: wywołaniem `save()` z niepełnym zestawem pól —
        // z konsoli, z testu, z przyszłego ekranu, który ustawia tylko układ.
        // Bez nich taki zapis po cichu gasi wszystkie włączniki naraz.
        if (array_key_exists('option', $values)) {
            ConfigStore::set(self::PREFIX . 'OPTIONS', (string) json_encode($this->readOptions($values)), true);
        }

        if (array_key_exists('telefon', $values)) {
            ConfigStore::set(self::PREFIX . 'PHONE', (string) json_encode($this->readPhone($values)), true);
        }

        if (array_key_exists('wyglad', $values)) {
            ConfigStore::set(self::PREFIX . 'SURFACES', (string) json_encode($this->readSurfaces($values)), true);
        }
    }

    /**
     * Wygląd powierzchni z wysyłki formularza.
     *
     * Pola przychodzą jako `wyglad[karta][bg]`, `wyglad[karta][border]` i tak
     * dalej. Wartości spoza spisu wypadają — do CSS trafia tylko to, co sami
     * przewidzieliśmy.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,array<string,string>>
     */
    private function readSurfaces(array $values): array
    {
        $dozwolone = [
            'border' => ['', 'on', 'off'],
            'shadow' => ['', 'flat', 'subtle', 'strong'],
            'radius' => ['', 'sharp', 'round'],
        ];

        $wyslane = is_array($values['wyglad'] ?? null) ? $values['wyglad'] : [];
        $wynik = [];

        foreach (self::SURFACES as $nazwa) {
            $pola = is_array($wyslane[$nazwa] ?? null) ? $wyslane[$nazwa] : [];

            // Tło: kod barwy albo nazwa stopnia skali. Nic innego.
            $tlo = trim((string) ($pola['bg'] ?? ''));
            if (!preg_match('/^(#[0-9a-fA-F]{3,8}|[a-z]+-\d{1,2})$/', $tlo)) {
                $tlo = '';
            }

            $wynik[$nazwa] = ['bg' => $tlo];

            foreach ($dozwolone as $klucz => $wartosci) {
                $wybor = (string) ($pola[$klucz] ?? '');
                $wynik[$nazwa][$klucz] = in_array($wybor, $wartosci, true) ? $wybor : '';
            }
        }

        return $wynik;
    }

    /**
     * Ustawienia telefonu z wysyłki formularza.
     *
     * Pola przychodzą jako `telefon[pasek]`, `telefon[pasek_cena]` i tak dalej.
     * Brak pola znaczy „wyłączone": przeglądarka nie wysyła niezaznaczonego
     * pola wyboru, a każdy przełącznik ma przed sobą pole ukryte z zerem,
     * więc wartość przychodzi zawsze — poza listą warstwy, którą liczymy
     * z tego, co przyszło.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,mixed>
     */
    private function readPhone(array $values): array
    {
        $telefon = is_array($values['telefon'] ?? null) ? $values['telefon'] : [];

        $wylaczone = [];

        foreach ($telefon as $klucz => $wartosc) {
            if (!str_starts_with((string) $klucz, 'szuflada_')) {
                continue;
            }

            if ((string) $wartosc !== '1') {
                $wylaczone[] = substr((string) $klucz, strlen('szuflada_'));
            }
        }

        return [
            'bar' => ($telefon['pasek'] ?? '0') === '1',
            'bar_price' => ($telefon['pasek_cena'] ?? '0') === '1',
            'bar_button' => ($telefon['pasek_przycisk'] ?? '0') === '1',
            'sheet' => ($telefon['szuflada'] ?? '0') === '1',
            'sheet_off' => $wylaczone,
        ];
    }

    /**
     * Powierzchnie strony produktu — nazwa w panelu => klucz tokenu.
     *
     * Nazwa jest jednocześnie nazwą strefy, więc panel i silnik mówią o tym
     * samym jednym słowem.
     *
     * @var string[]
     */
    private const SURFACES = ['karta', 'zakup', 'zapewnienia', 'akordeon', 'zestawy', 'zajawki'];

    /**
     * Wygląd powierzchni: tło, ramka, cień i zaokrąglenie każdej karty.
     *
     * Pusta wartość znaczy „jak w ustawieniach globalnych" i nie wypisujemy dla
     * niej tokenu — motyw ma wtedy zostać przy swojej wartości domyślnej.
     * Zapisanie „dziedziczenia" jako konkretnej barwy zamroziłoby kartę na tym,
     * co akurat było globalnie w chwili zapisu.
     *
     * @return array<string,array<string,string>>
     */
    public function surfaces(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'SURFACES'), true);
        $zapisane = is_array($zapisane) ? $zapisane : [];
        $wynik = [];

        foreach (self::SURFACES as $nazwa) {
            $wynik[$nazwa] = [
                'bg' => (string) ($zapisane[$nazwa]['bg'] ?? ''),
                'border' => (string) ($zapisane[$nazwa]['border'] ?? ''),
                'shadow' => (string) ($zapisane[$nazwa]['shadow'] ?? ''),
                'radius' => (string) ($zapisane[$nazwa]['radius'] ?? ''),
            ];
        }

        return $wynik;
    }

    /**
     * Tokeny powierzchni do wypisania w nagłówku strony.
     *
     * Wypisujemy WYŁĄCZNIE to, co sklep ustawił. Motyw czyta je z wartością
     * zapasową (`var(--q-pdp-karta-bg, …)`), więc brak tokenu to nie brak
     * wyglądu — to wygląd domyślny.
     *
     * @param array<string,string> $paleta stopnie skali z generatora palety
     *
     * @return array<string,string>
     */
    public function surfaceTokens(array $paleta, array $przeskoki = []): array
    {
        $tokeny = [];

        foreach ($this->surfaces() as $nazwa => $wyglad) {
            $przedrostek = 'pdp-' . $nazwa . '-';

            // Kreski bloku liczone od JEGO tła, a nie od globalnego. Sklep ustawia
            // w Wyglądzie różnicę stopni, a nie odcień: blok z ciemniejszym tłem
            // dostaje wtedy proporcjonalnie ciemniejszą kreskę i nie zdarza się,
            // żeby kreska wypadła dokładnie w kolorze tła i zniknęła.
            $stopien = $this->stopienZeSkali($wyglad['bg']);

            if ($stopien !== null) {
                foreach (['kreska' => 'card', 'pole-kreska' => 'input', 'tabela-kreska' => 'table'] as $token => $rodzaj) {
                    $barwa = $this->kreskaOdTla($stopien, (int) ($przeskoki[$rodzaj] ?? 0), $paleta);

                    if ($barwa !== '') {
                        $tokeny[$przedrostek . $token] = $barwa;
                    }
                }
            }

            if ($wyglad['bg'] !== '') {
                // Wybór z palety trzymamy jako STOPIEŃ, nie jako kod barwy:
                // po zmianie koloru marki karta ma pójść za nią, a nie zostać
                // przy odcieniu sprzed zmiany.
                $tokeny[$przedrostek . 'bg'] = str_starts_with($wyglad['bg'], '#')
                    ? $wyglad['bg']
                    : ($paleta[$wyglad['bg']] ?? '');
            }

            if ($wyglad['border'] !== '') {
                // Wyłączona ramka zostaje przezroczysta, a nie znika: karta
                // zachowuje wymiar, więc przełączenie niczego nie przesuwa.
                $tokeny[$przedrostek . 'border'] = $wyglad['border'] === 'on'
                    ? '1px solid var(--q-pdp-' . $nazwa . '-kreska, var(--q-border-card))'
                    : '1px solid transparent';
            }

            if ($wyglad['shadow'] !== '') {
                $tokeny[$przedrostek . 'shadow'] = match ($wyglad['shadow']) {
                    'subtle' => 'var(--q-shadow-sm)',
                    'strong' => 'var(--q-shadow-lg)',
                    default => 'none',
                };
            }

            if ($wyglad['radius'] !== '') {
                $tokeny[$przedrostek . 'radius'] = $wyglad['radius'] === 'round'
                    ? 'var(--q-card-radius)'
                    : '0';
            }

            // Blok, który dostał widoczną skórę, dostaje też odstęp od krawędzi.
            // Bez tego pierwsze włączenie ramki albo tła w panelu przykleja treść
            // do kreski — a sklep nie ma osobnego pola „wcięcie" i nie ma tego
            // jak naprawić.
            if ($wyglad['bg'] !== '' || $wyglad['border'] === 'on') {
                $tokeny[$przedrostek . 'pad'] = 'var(--q-space-md)';
            }
        }

        return array_filter($tokeny, static fn (string $v): bool => $v !== '');
    }

    /**
     * Numer stopnia skali z zapisanego wyboru powierzchni.
     *
     * Zapis ma postać `neutral-5`. Barwa wpisana kodem (`#fff`) stopnia nie ma,
     * więc kreski liczonej od niej nie da się wyprowadzić i blok zostaje przy
     * wartościach globalnych.
     */
    private function stopienZeSkali(string $wybor): ?int
    {
        if (!preg_match('/^neutral-(\d{1,2})$/', $wybor, $trafienie)) {
            return null;
        }

        $stopien = (int) $trafienie[1];

        return $stopien >= 1 && $stopien <= 12 ? $stopien : null;
    }

    /**
     * Kreska o zadaną liczbę stopni od tła bloku.
     *
     * Na jasnych tłach schodzimy w dół skali, czyli ku ciemniejszym stopniom.
     * Na ciemnych idziemy w drugą stronę: kreska ciemniejsza od ciemnego tła
     * byłaby niewidoczna. Granicą jest koniec skali, więc blok w skrajnym
     * stopniu dostaje kreskę tak mocną, jak skala pozwala.
     *
     * @param array<string,string> $paleta
     */
    private function kreskaOdTla(int $stopienTla, int $przeskok, array $paleta): string
    {
        if ($przeskok === 0) {
            return '';
        }

        // Stopnie 1–3 to najjaśniejsze tła, 12 to atrament. Ciemne zaczynają się
        // mniej więcej od dziewiątki i tam kreska ma iść ku jaśniejszym.
        $kierunek = $stopienTla >= 9 ? -1 : 1;
        $cel = $stopienTla + ($kierunek * abs($przeskok));
        $cel = max(1, min(12, $cel));

        return $paleta['neutral-' . $cel] ?? '';
    }

    /**
     * Co widać na telefonie: pasek przy dolnej krawędzi i warstwa, która się
     * z niego wysuwa.
     *
     * Warstwa jest DUBLEM panelu zakupu, nie osobnym układem: kolejność bierze
     * z panelu, a osobna jest tylko decyzja, co do niej wchodzi. Powód jest
     * praktyczny — na telefonie miejsca jest mało, więc hook z leasingiem albo
     * warunkami hurtowymi ma sens w panelu na komputerze, a w wysuwanej warstwie
     * zajmowałby połowę ekranu. Druga, niezależna kolejność znaczyłaby dwa
     * rozstawienia tego samego panelu do pilnowania.
     *
     * Element, o którym sklep jeszcze nie zdecydował, wchodzi do warstwy —
     * bo w panelu też jest włączony i nie ma powodu, żeby zniknął z telefonu.
     *
     * @return array{bar:bool,bar_price:bool,bar_button:bool,sheet:bool,sheet_off:string[]}
     */
    public function phone(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'PHONE'), true);
        $zapisane = is_array($zapisane) ? $zapisane : [];

        return [
            'bar' => (bool) ($zapisane['bar'] ?? true),
            'bar_price' => (bool) ($zapisane['bar_price'] ?? true),
            'bar_button' => (bool) ($zapisane['bar_button'] ?? true),
            'sheet' => (bool) ($zapisane['sheet'] ?? true),
            // Trzymamy to, co WYŁĄCZONE, a nie to, co włączone: element dołożony
            // do panelu później ma się pojawić także na telefonie, bez wracania
            // na ten ekran. Lista wyjątków jest krótka, lista wszystkiego rośnie
            // z każdym hookiem.
            'sheet_off' => array_values(array_filter(
                array_map('strval', (array) ($zapisane['sheet_off'] ?? [])),
                static fn (string $id): bool => $id !== ''
            )),
        ];
    }

    /** Czy element panelu zakupu wchodzi do warstwy na telefonie. */
    public function inSheet(string $id): bool
    {
        return !in_array($id, $this->phone()['sheet_off'], true);
    }

    /**
     * Szczegóły elementów z nałożonymi wartościami domyślnymi.
     *
     * Rodzaj wartości poznajemy po katalogu, tak samo jak przy zapisie: liczba
     * zostaje liczbą, reszta staje się włącznikiem. Rzutowanie wszystkiego na
     * wartość logiczną zamieniało „pokaż 3 cechy" na „tak": panel rysował wtedy
     * przełącznik zamiast pola liczbowego, a karta produktu porównywała liczbę
     * cech z wartością logiczną i wypisywała wszystkie, bez odnośnika do
     * specyfikacji.
     *
     * @return array<string,array<string,bool|int>>
     */
    public function options(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'OPTIONS'), true);
        $zapisane = is_array($zapisane) ? $zapisane : [];
        $wynik = [];

        foreach (self::CATALOGUE as $id => $element) {
            if (empty($element['options'])) {
                continue;
            }

            foreach ($element['options'] as $klucz => $domyslna) {
                if (!isset($zapisane[$id][$klucz])) {
                    $wynik[$id][$klucz] = $domyslna;

                    continue;
                }

                $wynik[$id][$klucz] = is_int($domyslna)
                    ? $this->liczbaOpcji($zapisane[$id][$klucz], $domyslna, self::zakres($id, $klucz))
                    : (bool) $zapisane[$id][$klucz];
            }
        }

        return $wynik;
    }

    /**
     * Nazwy stref dostępnych w danym układzie.
     *
     * @return string[]
     */
    public function zonesFor(string $layout): array
    {
        $wynik = [];

        foreach (self::ZONES as $nazwa => $strefa) {
            if (in_array($layout, $strefa['layouts'], true)) {
                $wynik[] = $nazwa;
            }
        }

        return $wynik;
    }

    /**
     * Pełne nazwy kluczy konfiguracji tej klasy.
     *
     * Potrzebne przy odinstalowaniu: moduł kasuje dokładnie to, co założył. Nazwy
     * wyliczane ze schematu, a nie wypisane drugi raz — inaczej dołożenie pola
     * zostawiałoby po sobie klucz na zawsze.
     *
     * @return string[]
     */
    public static function configurationKeys(): array
    {
        return array_map(
            static fn (string $klucz): string => self::PREFIX . $klucz,
            array_keys(self::SCHEMA)
        );
    }

    // ------------------------------------------------------------------ wnętrze

    /**
     * @param array<string,array<string,bool>> $opcje
     *
     * @return array{id:string,hook:string,on:bool,label:string,options:array<string,bool>}
     */
    private function element(string $id, bool $on, array $opcje): array
    {
        $modul = self::CATALOGUE[$id]['requires'] ?? '';
        $wymagania = self::OPTION_REQUIRES[$id] ?? [];
        $szczegoly = $opcje[$id] ?? [];

        // Każdy włącznik szczegółu ma własny warunek: „Omnibus" czeka na swój
        // moduł, choć blok cen działa bez niego.
        $dostepne = [];

        foreach (array_keys($szczegoly) as $klucz) {
            $potrzebny = $wymagania[$klucz] ?? '';
            $dostepne[$klucz] = $potrzebny === '' || self::moduleActive($potrzebny);

            if (!$dostepne[$klucz] && !is_int($szczegoly[$klucz])) {
                $szczegoly[$klucz] = false;
            }
        }

        return [
            'id' => $id,
            'hook' => '',
            'on' => $on,
            'label' => self::CATALOGUE[$id]['label'],
            'options' => $szczegoly,

            // Czego moduł nie obsługuje, tego sklep nie włączy — pole zostaje
            // widoczne, żeby było wiadomo, co przyjdzie razem z modułem.
            'requires' => $modul,
            'available' => $modul === '' || self::moduleActive($modul),
            'optionRequires' => $wymagania,
            'optionAvailable' => $dostepne,

            // Granice pól liczbowych na ekranie panelu.
            'optionRange' => self::CATALOGUE[$id]['ranges'] ?? [],
        ];
    }

    /**
     * Czy moduł jest zainstalowany i włączony.
     *
     * Wynik zapamiętujemy na czas żądania: przy trzydziestu elementach pytanie
     * o ten sam moduł wracałoby kilkanaście razy.
     *
     * @var array<string,bool>
     */
    private static array $moduly = [];

    private static function moduleActive(string $nazwa): bool
    {
        if (!array_key_exists($nazwa, self::$moduly)) {
            $modul = \Module::getInstanceByName($nazwa);
            self::$moduly[$nazwa] = $modul !== false && (bool) $modul->active;
        }

        return self::$moduly[$nazwa];
    }

    /**
     * Zapisane rozstawienie, po jednym wpisie na układ.
     *
     * @return array<string,array<string,array<int,array{id:string,on:bool}>>>
     */
    private function arrangement(): array
    {
        $zapisane = json_decode((string) Configuration::get(self::PREFIX . 'ARRANGEMENT'), true);

        return is_array($zapisane) ? $zapisane : [];
    }

    /**
     * Czyta rozstawienie przysłane przez panel. Przyjmuje wyłącznie strefy tego
     * układu, elementy z katalogu w dozwolonych dla nich strefach oraz hooki
     * o poprawnej nazwie.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,array<int,array{id:string,on:bool}>>
     */
    private function readArrangement(array $values, string $layout): array
    {
        $surowe = json_decode((string) ($values['ARRANGEMENT'] ?? ''), true);
        $surowe = is_array($surowe) ? $surowe : [];
        $wynik = [];
        $uzyte = [];

        foreach ($this->zonesFor($layout) as $strefa) {
            $wynik[$strefa] = [];

            foreach (is_array($surowe[$strefa] ?? null) ? $surowe[$strefa] : [] as $surowyWiersz) {
                $wiersz = [];

                foreach (is_array($surowyWiersz) ? $surowyWiersz : [] as $pozycja) {
                    $id = is_array($pozycja) ? (string) ($pozycja['id'] ?? '') : '';

                    if ($id === '' || isset($uzyte[$id])) {
                        continue;
                    }

                    if (str_starts_with($id, 'hook:')) {
                        // Nazwa hooka trafia do `Hook::exec`, więc przepuszczamy wyłącznie
                        // to, co może nią być — litery i cyfry, nic poza tym.
                        if (!preg_match('/^hook:[a-zA-Z][a-zA-Z0-9]{2,63}$/', $id)) {
                            continue;
                        }
                    } elseif (!$this->allowedIn($id, $strefa, $layout)) {
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
     * Wartość liczbowej opcji elementu, przycięta do rozsądnego zakresu.
     *
     * Poniżej trzech lista przestaje być listą, powyżej dziesięciu staje się
     * specyfikacją przepisaną w drugie miejsce — a specyfikacja jest niżej, w swojej
     * własnej sekcji. Puste pole wraca do wartości domyślnej, a nie do zera:
     * zero znaczyłoby „nie pokazuj nic", a od tego jest wyłączenie elementu.
     *
     * @param mixed $wartosc
     */
    private function liczbaOpcji($wartosc, int $domyslna, array $zakres): int
    {
        if ($wartosc === null || $wartosc === '') {
            return $domyslna;
        }

        return max($zakres[0], min($zakres[1], (int) $wartosc));
    }

    /**
     * Dolna i górna granica opcji liczbowej.
     *
     * Każda liczba w katalogu znaczy co innego: liczba cech mieści się w kilku,
     * a próg dni przed końcem promocji może sięgać roku. Bez własnego zakresu
     * wszystkie brałyby granice pierwszej z nich.
     *
     * @return array{0:int,1:int}
     */
    private static function zakres(string $id, string $klucz): array
    {
        $zakres = self::CATALOGUE[$id]['ranges'][$klucz] ?? null;

        return is_array($zakres) ? [(int) $zakres[0], (int) $zakres[1]] : [0, 999];
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return array<string,array<string,bool|int>>
     */
    private function readOptions(array $values): array
    {
        $przyslane = is_array($values['option'] ?? null) ? $values['option'] : [];
        $wynik = [];

        foreach (self::CATALOGUE as $id => $element) {
            if (empty($element['options'])) {
                continue;
            }

            foreach ($element['options'] as $klucz => $domyslna) {
                // Rodzaj opcji poznajemy po wartości domyślnej w katalogu: liczba
                // znaczy pole liczbowe, wszystko inne — przełącznik. Dzięki temu
                // dołożenie kolejnej opcji liczbowej nie wymaga niczego poza
                // wpisaniem jej domyślnej wartości.
                if (is_int($domyslna)) {
                    $wynik[$id][$klucz] = $this->liczbaOpcji(
                        $przyslane[$id][$klucz] ?? null,
                        $domyslna,
                        self::zakres($id, $klucz)
                    );

                    continue;
                }

                $wynik[$id][$klucz] = !empty($przyslane[$id][$klucz]);
            }
        }

        return $wynik;
    }
}
