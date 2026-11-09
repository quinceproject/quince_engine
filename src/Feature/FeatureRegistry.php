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

use Hook;
use Module;

/**
 * Rejestr możliwości — co sklep faktycznie potrafi.
 *
 * Silnik nie zna z góry nazw naszych modułów. Ogłasza hook, a każdy moduł, który
 * coś dostarcza, zgłasza się sam. Dzięki temu dołożenie kolejnego modułu nie
 * wymaga zmiany w silniku, czyli nowej jego wersji u wszystkich klientów.
 *
 * Po co to jest: ustawienie nie może oferować wyboru, którego nie da się spełnić.
 * Jeśli nasza wyszukiwarka nie jest zainstalowana, jej nazwa nie ma prawa pojawić
 * się na liście — a nie: pojawić się i nie działać.
 *
 * Zgłoszenie modułu wygląda tak:
 *
 *     public function hookActionQuinceRegisterFeature(): array
 *     {
 *         return [[
 *             'type'  => FeatureRegistry::TYPE_SEARCH,
 *             'id'    => 'quince_search',
 *             'label' => $this->trans('Wyszukiwarka Quince', [], 'Modules.Quincesearch.Admin'),
 *             'route' => 'quince_search_settings',
 *         ]];
 *     }
 */
final class FeatureRegistry
{
    /** Wyszukiwarka: pole w headerze i sposób szukania produktów. */
    public const TYPE_SEARCH = 'search';

    /** Menu główne: układ nawigacji i rozwijane działy. */
    public const TYPE_MENU = 'menu';

    /** Karuzela na stronie głównej. */
    public const TYPE_SLIDER = 'slider';

    /** Lista życzeń. */
    public const TYPE_WISHLIST = 'wishlist';

    /**
     * Powiadomienia dla klienta: dzwonek w nagłówku i to, co się pod nim otwiera.
     *
     * Silnik nie rysuje ich sam — daje miejsce hookiem `displayQuinceHeaderActions`,
     * a treść przynosi moduł. Rejestr służy tu do jednego: żeby przełącznik
     * w panelu pojawił się dopiero wtedy, gdy jest co włączać.
     */
    public const TYPE_NOTIFICATIONS = 'notifications';

    /** Bloki odnośników w stopce: nagłówek i lista pozycji pod nim. */
    public const TYPE_FOOTER_LINKS = 'footer_links';

    /**
     * Zabezpieczenie formularzy zewnętrznym dostawcą.
     *
     * Silnik broni formularzy sam — pułapką, czasem wypełnienia i limitem
     * powtórzeń. Moduł tego typu dokłada ostatnią warstwę i dostaje ostatnie
     * słowo na hooku `actionQuinceFormVerify`.
     */
    public const TYPE_CAPTCHA = 'captcha';

    /** Ludzie sklepu: zdjęcie, stanowisko i telefon przy produktach z ich działu. */
    public const TYPE_CONTACTS = 'contacts';

    /**
     * Historia cen produktu i najniższa cena z ostatnich dni.
     *
     * Silnik sam jej nie prowadzi: zapis każdej zmiany ceny to własna tabela,
     * która rośnie z katalogiem, i harmonogram — czyli moduł, a nie ustawienie.
     * Rejestr służy tu do tego, żeby włącznik przy cenie ożył dopiero wtedy,
     * gdy jest co pokazać, a nie obiecywał notę, której nikt nie policzy.
     */
    public const TYPE_PRICE_HISTORY = 'price_history';

    /**
     * Ranking cech: które cechy produktu pokazać pod opisem.
     *
     * Silnik sam ich nie wybiera. Kolejność wynika z tego, jak cechy rozkładają
     * się w całej kategorii, więc trzeba je policzyć na całym katalogu i gdzieś
     * zapisać — to jest własna tabela i zadanie w tle, czyli moduł. Bez modułu
     * element pokazuje pierwsze cechy w kolejności katalogu, tak jak dotąd.
     */
    public const TYPE_FEATURE_RANK = 'feature_rank';

    /**
     * Oferta PDF na wybrane produkty.
     *
     * Silnik zna miejsce przycisku na karcie produktu, ale samej oferty nie
     * wystawi: numer, dziennik, plik i wysyłka to własne tabele i własny
     * generator, czyli moduł.
     */
    public const TYPE_OFFERS = 'offers';

    /**
     * Podpowiedzi produktów do dokupienia: pod kartą produktu, w koszyku,
     * w szufladzie koszyka i na potwierdzeniu zamówienia.
     *
     * Silnik zna miejsce karuzeli na karcie produktu, ale dobór produktów
     * (historia zamówień, akcesoria, kategoria, bestsellery, próg darmowej
     * dostawy) to osobna logika i osobne ustawienia, czyli moduł.
     */
    public const TYPE_CROSS_SELLING = 'cross_selling';

    /**
     * Rząd polecanych produktów pod listą.
     *
     * Silnik zna miejsce rzędu w strefie pod listą, ale skąd wziąć produkty
     * na każdej z siedmiu stron z listą i czym się tam kierować, to osobna
     * logika i osobne ustawienia, czyli moduł.
     */
    public const TYPE_PRODUCT_SECTION = 'product_section';

    /** @var string[] typy, o które silnik pyta */
    public const TYPES = [
        self::TYPE_SEARCH,
        self::TYPE_MENU,
        self::TYPE_SLIDER,
        self::TYPE_WISHLIST,
        self::TYPE_NOTIFICATIONS,
        self::TYPE_FOOTER_LINKS,
        self::TYPE_CAPTCHA,
        self::TYPE_CONTACTS,
        self::TYPE_PRICE_HISTORY,
        self::TYPE_FEATURE_RANK,
        self::TYPE_OFFERS,
        self::TYPE_CROSS_SELLING,
        self::TYPE_PRODUCT_SECTION,
    ];

    public const HOOK = 'actionQuinceRegisterFeature';

    /**
     * Rozwiązanie wbudowane — zawsze obecne, bo sklep musi działać także wtedy,
     * gdy nie ma zainstalowanego ani jednego naszego modułu.
     */
    public const BUILT_IN = 'built-in';

    /** @var array<string,array<string,array{id:string,label:string,route:?string,module:string}>>|null */
    private static ?array $cache = null;

    /**
     * Pozycje panelu z tego samego zbierania — trzymane osobno, bo rządzą się
     * inną regułą: możliwości muszą być znane, pozycja wystarczy że jest pełna.
     *
     * @var array<string,array{module:string,label:string,icon:string,route:string}>|null
     */
    private static ?array $pozycjeCache = null;

    /**
     * Dostawcy dla danego typu, z rozwiązaniem wbudowanym na pierwszym miejscu.
     *
     * @return array<string,array{id:string,label:string,route:?string,module:string}>
     */
    public function providersFor(string $type): array
    {
        return self::collect()[$type] ?? [];
    }

    /**
     * Sprawdzony wybór sprzedawcy.
     *
     * Wartość zapisana w ustawieniach może wskazywać moduł, którego już nie ma —
     * sprzedawca wybrał naszą wyszukiwarkę, a potem ją odinstalował. Wtedy
     * wracamy do rozwiązania wbudowanego, zamiast zostawiać sklep bez wyszukiwania.
     */
    public function resolve(string $type, string $chosen): string
    {
        return isset($this->providersFor($type)[$chosen]) ? $chosen : self::BUILT_IN;
    }

    /**
     * Czy zapisany wybór wskazuje na coś, czego już nie ma. Panel pokazuje z tego
     * ostrzeżenie — cicha zmiana zachowania sklepu jest gorsza niż komunikat.
     */
    public function isMissing(string $type, string $chosen): bool
    {
        return $chosen !== self::BUILT_IN && !isset($this->providersFor($type)[$chosen]);
    }

    /**
     * Pozycje do menu panelu: każdy zainstalowany moduł, który zgłosił własny ekran.
     *
     * Niezależne od `TYPES` — patrz komentarz przy zbieraniu. Silnik nie musi
     * rozumieć, czym moduł się zajmuje, żeby postawić odnośnik do jego ustawień.
     *
     * @return array<int,array{module:string,label:string,icon:string,route:string}>
     */
    public function panelEntries(): array
    {
        self::collect();

        return array_values(self::$pozycjeCache ?? []);
    }

    /** @return array<string,array<string,array{id:string,label:string,route:?string,module:string}>> */
    public function all(): array
    {
        return self::collect();
    }

    /**
     * Nasze moduły widziane przez silnik — do przeglądu w panelu.
     *
     * @return array<int,array{module:string,type:string,label:string,route:?string,version:string}>
     */
    public function installedModules(): array
    {
        $out = [];

        foreach (self::collect() as $type => $providers) {
            foreach ($providers as $provider) {
                if ($provider['module'] === '') {
                    continue;
                }

                $module = Module::getInstanceByName($provider['module']);

                $out[] = [
                    'module' => $provider['module'],
                    'type' => $type,
                    'label' => $provider['label'],
                    'route' => $provider['route'],
                    'version' => $module ? (string) $module->version : '?',
                ];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ zbieranie

    /**
     * Odpowiedzi modułów zebrane raz na żądanie. Hook wykonuje się przy każdym
     * wywołaniu, a lista dostawców jest potrzebna w kilku miejscach jednej strony.
     *
     * @return array<string,array<string,array{id:string,label:string,route:?string,module:string}>>
     */
    private static function collect(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $out = [];

        foreach (self::TYPES as $type) {
            $out[$type] = [
                self::BUILT_IN => [
                    'id' => self::BUILT_IN,
                    'label' => self::builtInLabel($type),
                    'route' => null,
                    'module' => '',
                ],
            ];
        }

        // Trzeci parametr `true` sprawia, że dostajemy odpowiedzi rozbite na moduły,
        // a nie sklejone w jeden ciąg znaków.
        $answers = Hook::exec(self::HOOK, [], null, true);
        $pozycje = [];

        foreach (is_array($answers) ? $answers : [] as $moduleName => $declarations) {
            foreach (self::normalise($declarations) as $declaration) {
                $type = (string) ($declaration['type'] ?? '');
                $id = (string) ($declaration['id'] ?? '');
                $modul = (string) ($declaration['module'] ?? $moduleName);
                $trasa = isset($declaration['route']) ? (string) $declaration['route'] : null;

                if ($id === '' || $id === self::BUILT_IN) {
                    continue;
                }

                // POZYCJA W PANELU — otwarta, sprawdzana po kształcie.
                //
                // Moduł, który ma własny ekran, ma się w menu pokazać niezależnie
                // od tego, czy silnik zna jego dziedzinę. Inaczej każdy kolejny
                // moduł linii wymagałby wydania silnika u wszystkich sklepów tylko
                // po to, żeby dołożyć jedną pozycję — a to jest dokładnie ta wada,
                // którą dokument publikacji każe usunąć.
                //
                // Warunkiem jest komplet danych, nie przynależność do spisu nazw:
                // moduł napisany pod nowszy silnik nadal nie wywróci panelu na
                // starszym, bo zgłoszenie bez trasy albo bez modułu tu nie wchodzi.
                if ($trasa !== null && $modul !== '') {
                    $pozycje[$modul] = [
                        'module' => $modul,
                        'label' => (string) ($declaration['label'] ?? $id),
                        'icon' => (string) ($declaration['icon'] ?? 'puzzle'),
                        'route' => $trasa,
                    ];
                }

                // MOŻLIWOŚĆ — zamknięta, sprawdzana po przynależności do spisu.
                //
                // Tu lista musi zostać zamknięta, bo `TYPES` to nie filtr, tylko
                // spis rzeczy, których silnik UMIE UŻYĆ: wybór wyszukiwarki
                // w nagłówku, dostawcy menu, listy życzeń. Przyjęcie nieznanego
                // typu nie dałoby silnikowi umiejętności skorzystania z niego —
                // zwróciłoby wiersze, których nikt nie czyta.
                if (!isset($out[$type])) {
                    continue;
                }

                $out[$type][$id] = [
                    'id' => $id,
                    'label' => (string) ($declaration['label'] ?? $id),
                    'route' => $trasa,
                    'module' => $modul,
                ];
            }
        }

        self::$pozycjeCache = $pozycje;

        return self::$cache = $out;
    }

    /**
     * Moduł może zgłosić jedną możliwość albo kilka. Obie postacie sprowadzamy
     * do listy, żeby dalej był jeden przypadek zamiast dwóch.
     *
     * @param mixed $declarations
     *
     * @return array<int,array<string,mixed>>
     */
    private static function normalise($declarations): array
    {
        if (!is_array($declarations)) {
            return [];
        }

        return isset($declarations['type']) ? [$declarations] : array_filter($declarations, 'is_array');
    }

    private static function builtInLabel(string $type): string
    {
        $labels = [
            self::TYPE_SEARCH => 'Built-in search',
            self::TYPE_MENU => 'Built-in menu',
            self::TYPE_SLIDER => 'No slider',
            // Silnik nie ma własnej listy życzeń — bez modułu nie ma gdzie
            // zapisywać produktów, więc „wbudowana" znaczy tu: żadna.
            self::TYPE_WISHLIST => 'No wishlist',
            self::TYPE_FEATURE_RANK => 'Catalogue order',
        ];

        return $labels[$type] ?? 'Built-in';
    }
}
