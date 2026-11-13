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

use Module;
use Symfony\Component\Routing\RouterInterface;

/**
 * Spis naszych modułów: co istnieje, niezależnie od tego, co sklep ma u siebie.
 *
 * Rejestr możliwości (FeatureRegistry) mówi, co jest zainstalowane i zgłoszone.
 * Ten spis mówi, co w ogóle powstało, więc panel może pokazać także moduły,
 * których sklep jeszcze nie ma.
 *
 * Wersje i adresy paczek świadomie NIE są tutaj: przyjdą z pliku z listą wydań,
 * który silnik pobiera raz na dobę. Spis wkompilowany w moduł nigdy nie wiedziałby
 * o wydaniu nowszym niż on sam, czyli nie odpowiedziałby na pytanie o aktualizacje.
 */
final class ModuleCatalogue
{
    /** Konto, pod którym leżą wszystkie nasze moduły. */
    private const REPOSITORY_OWNER = 'GajewskiMarcin';

    /**
     * @var array<int,array{name:string,label:string,icon:string,type:?string,description:string}>
     */
    private const MODULES = [
        [
            'name' => 'quince_search',
            'label' => 'Search',
            'icon' => 'search',
            'type' => FeatureRegistry::TYPE_SEARCH,
            'description' => 'Search with a scope filter and product suggestions.',
        ],
        [
            'name' => 'quince_linkblocks',
            'label' => 'Link blocks',
            'icon' => 'link',
            'type' => FeatureRegistry::TYPE_FOOTER_LINKS,
            'description' => 'Blocks of links for the footer: categories, content pages and addresses of your own.',
        ],
        [
            'name' => 'quince_contacts',
            'label' => 'Contacts',
            'icon' => 'user',
            'type' => FeatureRegistry::TYPE_CONTACTS,
            'description' => 'The people behind the shop: photo, job title and phone, next to the products they know.',
        ],
        [
            'name' => 'quince_recaptcha',
            'label' => 'reCAPTCHA',
            'icon' => 'shield',
            'type' => FeatureRegistry::TYPE_CAPTCHA,
            'description' => 'Google reCAPTCHA on shop forms, on top of what the engine checks by itself.',
        ],
        [
            'name' => 'quince_omnibus',
            'label' => 'Price history',
            // Paczka design systemu nie ma ikony ceny ani historii, a dorysowanie
            // jej tutaj rozjechałoby zestaw z paczką. Do czasu jej dołożenia
            // moduł nosi ikonę neutralną.
            'icon' => 'box',
            'type' => FeatureRegistry::TYPE_PRICE_HISTORY,
            'description' => 'Records every price change and shows the lowest price of the last days, as the Omnibus Directive requires.',
        ],
        [
            'name' => 'quince_offers',
            'label' => 'Offers',
            'icon' => 'file',
            'type' => FeatureRegistry::TYPE_OFFERS,
            'description' => 'A PDF quote for the products a customer picks, with its own number and a copy kept in the shop.',
        ],
        [
            'name' => 'quince_crossselling',
            'label' => 'Cross-selling',
            'icon' => 'box',
            'type' => FeatureRegistry::TYPE_CROSS_SELLING,
            'description' => 'Products to add on the product page, in the cart and after the order, picked from order history, accessories, category and best sellers.',
        ],
        [
            'name' => 'quince_product_section',
            'label' => 'Product sections',
            'icon' => 'folder',
            'type' => FeatureRegistry::TYPE_PRODUCT_SECTION,
            'description' => 'A row of recommended products under the product list, set up separately for the category, brand, search, best sellers, new products, promotions and supplier pages.',
        ],
        [
            'name' => 'quince_slider',
            'label' => 'Slider',
            'icon' => 'image',
            'type' => FeatureRegistry::TYPE_SLIDER,
            'description' => 'Home page carousel with three slide types.',
        ],
        [
            'name' => 'quince_blog',
            'label' => 'Blog',
            'icon' => 'file',
            'type' => null,
            'description' => 'Multilingual blog with posts, categories, tags and teasers you can place anywhere in the theme.',
        ],
        [
            'name' => 'quince_dictionaries',
            'label' => 'Dictionaries',
            'icon' => 'folder',
            'type' => 'dictionaries',
            'description' => 'Vehicle fitment, OEM numbers and engine codes shown on the product page.',
        ],
        [
            'name' => 'quince_feature_rank',
            'label' => 'Feature rank',
            'icon' => 'layout',
            'type' => FeatureRegistry::TYPE_FEATURE_RANK,
            'description' => 'Picks which product specs to show first, based on which ones actually set products apart in that category.',
        ],
        [
            'name' => 'quince_qa',
            'label' => 'Questions & answers',
            'icon' => 'info',
            'type' => null,
            'description' => 'Customer questions about a product, answered by the shop and shown on the product page.',
        ],
        [
            'name' => 'quince_robots',
            'label' => 'Robots & AI',
            'icon' => 'shield',
            'type' => null,
            'description' => 'Decide which crawlers and AI agents may enter the shop, and see which of them actually come.',
        ],
        // quince_withdrawal NIE jest tu wpisany celowo: to moduł obsługi zamówień
        // (odstąpienie od umowy), nie wyglądu ani informacji o produkcie. Żyje
        // jako natywna zakładka PrestaShopa pod Customer Service, obok
        // "Zwrotów towaru", a nie jako pozycja w tym katalogu.
        // Menu i lista życzeń nie mają tu wpisu, bo są częścią silnika, nie dodatkiem.
        // Listę życzeń silnik prowadzi jako nakładkę na tabele blockwishlista:
        // jego frontend to aplikacja Vue, której nie da się ostylować, ale dane
        // i model zostają jego.
    ];

    private FeatureRegistry $registry;

    /**
     * Router panelu. Podaje go kontroler, bo tylko on ma go na pewno pod ręką:
     * `SymfonyContainer::getInstance()` w kontekście panelu zwraca `null`, więc
     * szukanie routera przez kontener gubi wszystkie pozycje modułów naraz.
     */
    private ?RouterInterface $router;

    public function __construct(?FeatureRegistry $registry = null, ?RouterInterface $router = null)
    {
        $this->registry = $registry ?? new FeatureRegistry();
        $this->router = $router;
    }

    /**
     * Stan każdego naszego modułu widziany przez silnik.
     *
     * Rozróżniamy trzy rzeczy, nie dwie: moduł może być wgrany na dysk, ale
     * niezainstalowany, i zainstalowany, ale wyłączony. Sprzedawca, który
     * wyłączył moduł i zapomniał, szuka potem błędu w ustawieniach.
     *
     * @return array<int,array{
     *     name:string, label:string, icon:string, description:string,
     *     present:bool, installed:bool, active:bool, version:?string, route:?string,
     *     repository:string
     * }>
     */
    public function all(): array
    {
        $out = [];

        foreach (self::MODULES as $entry) {
            $module = Module::getInstanceByName($entry['name']);
            $installed = $module instanceof Module && Module::isInstalled($entry['name']);
            $active = $installed && Module::isEnabled($entry['name']);

            $out[] = [
                'name' => $entry['name'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'description' => $entry['description'],
                'present' => $module instanceof Module,
                'installed' => $installed,
                'active' => $active,
                'version' => $module instanceof Module ? (string) $module->version : null,
                'route' => $this->routeFor($entry),
                'repository' => self::repositoryFor($entry['name']),
            ];
        }

        return $out;
    }

    /**
     * Pozycje do nawigacji silnika: moduły, które zgłosiły własny ekran.
     *
     * Źródłem jest ZGŁOSZENIE MODUŁU, nie spis `MODULES`, żeby nowy moduł linii
     * pokazał się w menu bez wydawania nowej wersji silnika. Moduł sam podaje
     * nazwę, ikonę i trasę.
     *
     * Spis `MODULES` opisuje moduły, których w sklepie NIE MA. Tam sztywna lista
     * jest konieczna, bo silnik nie zapyta modułu, którego nie ma.
     *
     * Nazwa z modułu ma pierwszeństwo, ale spis służy jako zapas: nasze starsze
     * moduły zgłaszają się bez ikony i mają dostać swoją, a nie kółko zastępcze.
     *
     * @return array<int,array{label:string,icon:string,route:string}>
     */
    public function navItems(): array
    {
        $zeSpisu = [];

        foreach (self::MODULES as $entry) {
            $zeSpisu[$entry['name']] = $entry;
        }

        $out = [];

        foreach ($this->registry->panelEntries() as $pozycja) {
            if (!Module::isEnabled($pozycja['module']) || !$this->trasaIstnieje($pozycja['route'])) {
                continue;
            }

            $wpis = $zeSpisu[$pozycja['module']] ?? null;

            $out[] = [
                'label' => $wpis['label'] ?? $pozycja['label'],
                'icon' => $wpis['icon'] ?? $pozycja['icon'],
                'route' => $pozycja['route'],
            ];
        }

        return $out;
    }

    /**
     * Czy trasa o tej nazwie w ogóle istnieje.
     *
     * Trasę podaje moduł w zgłoszeniu, a może ją podać z literówką albo zgłosić
     * się, zanim doda kontroler.
     * Bez tego sprawdzenia szablon próbuje zbudować odnośnik i **kładzie cały
     * panel**, łącznie z ekranami, które z tym modułem nie mają nic wspólnego.
     *
     * Rejestr obiecuje, że moduł napisany pod nowszy silnik nie wywróci starszego.
     * Ta metoda jest miejscem, w którym ta obietnica jest dotrzymywana po stronie
     * menu: pominięta pozycja to brak jednego odnośnika, a nie martwy panel.
     */
    private function trasaIstnieje(string $nazwa): bool
    {
        // Bez routera nie umiemy sprawdzić, więc POKAZUJEMY pozycję. Ukrycie
        // wszystkiego, czego nie dało się zweryfikować, kasuje całą grupę
        // „Moduły" w miejscach, gdzie routera nie podano.
        if ($this->router === null) {
            return true;
        }

        return $this->router->getRouteCollection()->get($nazwa) !== null;
    }

    /** Nazwa repozytorium jest zawsze nazwą modułu. */
    public static function repositoryFor(string $moduleName): string
    {
        return 'https://github.com/' . self::REPOSITORY_OWNER . '/' . $moduleName;
    }

    /**
     * Trasa do ustawień bierze się ze zgłoszenia modułu, nie ze spisu: to moduł
     * wie, jak nazwał swoją stronę, a silnik ma się tego nie domyślać.
     *
     * @param array{name:string,type:?string} $entry
     */
    private function routeFor(array $entry): ?string
    {
        if ($entry['type'] === null) {
            return null;
        }

        foreach ($this->registry->providersFor($entry['type']) as $provider) {
            if ($provider['module'] === $entry['name']) {
                return $provider['route'];
            }
        }

        return null;
    }
}
