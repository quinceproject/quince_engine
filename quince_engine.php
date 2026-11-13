<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

/**
 * Quince Engine: theme engine for PrestaShop 9.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Composer autoloader when the module ships with vendor/, hand-rolled PSR-4 otherwise.
// The fallback matters in development: src/ changes are picked up without running composer.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Quince\\Engine\\';
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

class Quince_Engine extends Module
{
    public function __construct()
    {
        $this->name = 'quince_engine';
        $this->tab = 'front_office_features';
        $this->version = '0.6.0';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        // Tabs declared here are created by PrestaShop on install and removed on uninstall.
        // class_name must match _legacy_controller from config/routes.yml: that is what
        // links the Symfony route to back-office permissions and to the menu entry.
        // Gałąź menu i wpisy modułów. Każdy nasz moduł zgłasza jeden wpis
        // prowadzący na swój ekran; podstrony ma we własnym bocznym pasku.
        // Kolejność wpisów ustala silnik, patrz `Quince\Engine\Feature\MenuOrder`.
        $this->tabs = [
            [
                // IMPROVE = sekcja „Ulepszanie" w głównym menu, na równi z Modułami,
                // Wyglądem i Wysyłką.
                'class_name' => \Quince\Engine\Feature\MenuOrder::PARENT,
                'visible' => true,
                'name' => 'Quince project',
                'parent_class_name' => 'IMPROVE',
                // Menu PrestaShopa rysuje ikonę czcionką Material Icons: w kolumnie
                // `icon` siedzi sama nazwa znaku.
                'icon' => 'nutrition',
                'wording' => 'Quince project',
                'wording_domain' => 'Modules.Quinceengine.Admin',
            ],
            [
                // Zakładka od uprawnień, nie od menu: wszystkie nasze trasy mają
                // `_legacy_controller: AdminQuince` i to po tej nazwie PrestaShop
                // sprawdza, czy pracownik ma prawo wejść. Niewidoczna, bo jako
                // pozycja menu dublowałaby wpis silnika.
                'class_name' => 'AdminQuince',
                'visible' => false,
                'name' => 'Quince',
                'parent_class_name' => \Quince\Engine\Feature\MenuOrder::PARENT,
                'wording' => 'Quince',
                'wording_domain' => 'Modules.Quinceengine.Admin',
            ],
            [
                'class_name' => 'AdminQuinceEngine',
                'visible' => true,
                'name' => 'Quince Engine',
                'parent_class_name' => \Quince\Engine\Feature\MenuOrder::PARENT,
                'route_name' => 'quince_appearance',
                'wording' => 'Quince Engine',
                'wording_domain' => 'Modules.Quinceengine.Admin',
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Quince Engine', [], 'Modules.Quinceengine.Admin');
        $this->description = $this->trans(
            'Theme engine for the Quince theme. Appearance, layout and page composition.',
            [],
            'Modules.Quinceengine.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayHome')
            && $this->registerHook('actionFrontControllerSetVariables')
            && $this->registerHook('filterProductSearch')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionFrontControllerInitAfter')
            && $this->registerHook('displayOverrideTemplate')
            && $this->registerHook('moduleRoutes')
            && $this->registerHook('actionQuinceRegisterFeature')
            && $this->registerHook('actionCategoryAdd')
            && $this->registerHook('actionCategoryUpdate')
            && $this->registerHook('actionCategoryDelete')
            && $this->registerFeatureHook()
            && $this->registerActionsHook()
            && $this->registerFormVerifyHook()
            && $this->installFormHitTable()
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionModuleInstallAfter')
            && $this->registerHook('actionModuleUninstallAfter')
            && $this->registerHook('actionValidateCustomerAddressForm')
            && $this->registerHook('actionSubmitCustomerAddressForm')
            && $this->installModuleGroups()
            && $this->installVideoField()
            && $this->wylaczUdostepnianie();
    }

    /**
     * Wyłącza natywne przyciski udostępniania.
     *
     * `ps_sharebuttons` wpina się w `displayProductAdditionalInfo`, więc jego
     * przyciski lądują tam, gdzie motyw wypisuje ten hook, i sklep nie ma jak
     * ich stamtąd ruszyć ani wyłączyć z panelu.
     *
     * Wyłączamy, a nie odinstalowujemy: sklep, który ich chce, włącza moduł
     * z powrotem. Odinstalowanie silnika ich nie przywraca.
     */
    public function wylaczUdostepnianie(): bool
    {
        $modul = $this->modulPoNazwie('ps_sharebuttons');

        if ($modul === null) {
            return true;
        }

        // We wszystkich sklepach, nie tylko w bieżącym: instalacja silnika idzie
        // raz, a przyciski wróciłyby w pozostałych sklepach tej instalacji.
        return (bool) $modul->disable(true);
    }

    /**
     * Moduł PrestaShopa gotowy do zmian, albo `null`, gdy go nie ma.
     *
     * Identyfikator bierzemy wprost z bazy. `getInstanceByName()` wywołane
     * w trakcie aktualizacji innego modułu oddaje obiekt z `id` równym zeru,
     * a wtedy `disable()` i `unregisterHook()` pracują na module o numerze zero,
     * czyli na niczym, i mimo to zwracają prawdę.
     */
    private function modulPoNazwie(string $nazwa): ?Module
    {
        if (!Module::isInstalled($nazwa)) {
            return null;
        }

        $modul = Module::getInstanceByName($nazwa);

        if (!$modul) {
            return null;
        }

        if ((int) $modul->id === 0) {
            $modul->id = (int) Module::getModuleIdByName($nazwa);
        }

        return (int) $modul->id === 0 ? null : $modul;
    }

    /** Nazwa pola dodatkowego z adresami filmów. */
    public const FIELD_VIDEOS = 'quince_videos';

    /** Ile filmów wolno wpisać przy jednym produkcie. */
    public const VIDEOS_LIMIT = 5;

    /**
     * Kolejność wpisów w menu po instalacji dowolnego modułu.
     *
     * PrestaShop dokłada wpis na koniec gałęzi, więc bez tego menu układałoby
     * się w kolejności instalowania. Silnik przestawia je z powrotem na swoją.
     */
    public function hookActionModuleInstallAfter(): void
    {
        \Quince\Engine\Feature\MenuOrder::apply();
    }

    /** To samo po odinstalowaniu: po wypadnięciu wpisu zostaje dziura w numeracji. */
    public function hookActionModuleUninstallAfter(): void
    {
        \Quince\Engine\Feature\MenuOrder::apply();
    }

    /**
     * Kontrolka pola filmów na karcie produktu.
     *
     * PrestaShop rysuje tam zwykłe pole tekstowe, bo tyle mówi definicja pola
     * dodatkowego. Skrypt zamienia je na wiersze z miniaturami, a wartość pisze
     * z powrotem do tego samego pola, więc zapis formularza zostaje rdzenia.
     *
     * Wyłącznie na karcie produktu.
     */
    public function hookActionAdminControllerSetMedia(): void
    {
        $kontroler = (string) Tools::getValue('controller');

        if (!in_array($kontroler, ['AdminProducts', 'AdminProductsV2'], true)) {
            return;
        }

        // Języki dla przełącznika przy wierszu: kontrolka rysuje własny, bo
        // PrestaShop daje jeden na całe pole, a u nas język ustawia się przy
        // każdym filmie osobno.
        $jezyki = [];

        foreach (Language::getLanguages(true, $this->context->shop->id) as $jezyk) {
            $jezyki[] = [
                'id' => (int) $jezyk['id_lang'],
                'iso' => Tools::strtoupper((string) $jezyk['iso_code']),
                'name' => (string) $jezyk['name'],
            ];
        }

        Media::addJsDef([
            'quinceVideos' => [
                'limit' => self::VIDEOS_LIMIT,
                'multistore' => Shop::isFeatureActive(),
                'languages' => $jezyki,
                'defaultLanguage' => (int) Configuration::get('PS_LANG_DEFAULT'),
                'labels' => [
                    'dodaj' => $this->trans('Add another film', [], 'Modules.Quinceengine.Admin'),
                    'usun' => $this->trans('Remove', [], 'Modules.Quinceengine.Admin'),
                    'placeholder' => $this->trans('Paste the address from YouTube or Vimeo', [], 'Modules.Quinceengine.Admin'),
                    'nieznany' => $this->trans('This address is not a YouTube or Vimeo film', [], 'Modules.Quinceengine.Admin'),
                    'wszystkieSklepy' => $this->trans('The same films in every shop', [], 'Modules.Quinceengine.Admin'),
                ],
            ],
        ]);

        $this->context->controller->addCSS($this->_path . 'views/css/produkt-filmy.css');
        $this->context->controller->addJS($this->_path . 'views/js/produkt-filmy.js');
    }

    /**
     * Rozsyła adresy filmów do pozostałych sklepów.
     *
     * Karta produktu otwiera się wyłącznie w jednym sklepie, więc bez tego sklep
     * z czterema rynkami wpisywałby ten sam adres cztery razy. Kopiujemy tylko
     * wtedy, gdy sprzedawca zaznaczył to przy polu, i tylko do sklepów, w których
     * produkt w ogóle jest.
     *
     * Zapis idzie wprost do tabeli pola dodatkowego. Nazwa kolumny jest umową
     * rdzenia: nazwa modułu i nazwa pola, tak jak w `ps_product_extra_lang`.
     *
     * @param array<string,mixed> $params
     */
    public function hookActionProductSave(array $params): void
    {
        if (!Shop::isFeatureActive() || !Tools::getValue('quince_videos_all_shops')) {
            return;
        }

        $idProduct = (int) ($params['id_product'] ?? 0);
        $idShop = (int) $this->context->shop->id;

        if ($idProduct <= 0 || $idShop <= 0) {
            return;
        }

        $tabela = _DB_PREFIX_ . 'product_extra_lang';
        $kolumna = pSQL($this->name . '_' . self::FIELD_VIDEOS);

        $zrodlo = Db::getInstance()->executeS(sprintf(
            'SELECT id_lang, `%s` AS adresy FROM `%s` WHERE id_product = %d AND id_shop = %d',
            $kolumna,
            $tabela,
            $idProduct,
            $idShop
        ));

        if (!is_array($zrodlo) || $zrodlo === []) {
            return;
        }

        $wiersze = Db::getInstance()->executeS(sprintf(
            'SELECT id_shop FROM `%sproduct_shop` WHERE id_product = %d AND id_shop <> %d',
            _DB_PREFIX_,
            $idProduct,
            $idShop
        ));

        $sklepy = array_map(
            static fn (array $w): int => (int) $w['id_shop'],
            is_array($wiersze) ? $wiersze : []
        );

        if ($sklepy === []) {
            return;
        }

        foreach ($zrodlo as $wiersz) {
            $idLang = (int) $wiersz['id_lang'];
            $adresy = $wiersz['adresy'] === null ? 'NULL' : "'" . pSQL((string) $wiersz['adresy']) . "'";

            foreach ($sklepy as $idObcy) {
                Db::getInstance()->execute(sprintf(
                    'INSERT INTO `%s` (id_product, id_shop, id_lang, `%s`) VALUES (%d, %d, %d, %s)
                     ON DUPLICATE KEY UPDATE `%s` = VALUES(`%s`)',
                    $tabela,
                    $kolumna,
                    $idProduct,
                    $idObcy,
                    $idLang,
                    $adresy,
                    $kolumna,
                    $kolumna
                ));
            }
        }
    }

    /**
     * Pole na filmy przy produkcie.
     *
     * Adresy wpisuje się jednym polem, po przecinku, a silnik rozbiera je przy
     * wyświetlaniu karty.
     *
     * `display_front` wypuszcza wartość na kartę produktu, więc motyw dostaje
     * ją bez dodatkowego zapytania.
     *
     * Powiązanie z formularzem to napis `formularz:ścieżka:before|after`. Bez
     * ścieżki pole dostaje własną zakładkę na końcu karty produktu. Stąd
     * `description.images`: adresy filmów stoją tam, gdzie zdjęcia, bo dotyczą
     * tego samego.
     *
     * Zakres językowy, a nie sklepowy, choć film bywa inny na innym rynku.
     * Tabela pola powstaje przez odwzorowanie klucza głównego tabeli źródłowej,
     * a `product_lang` ma klucz `(id_product, id_shop, id_lang)`, więc pole
     * językowe przy produkcie ma osobną wartość dla każdej pary język plus sklep.
     * Zakres sklepowy dałby mniej: jedną wartość na sklep, wspólną dla języków.
     */
    private function installVideoField(): bool
    {
        return $this->registerExtraProperty(new \PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition(
            entityName: 'product',
            propertyName: self::FIELD_VIDEOS,
            type: \PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType::STRING,
            scope: \PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope::LANG,
            size: 1000,
            displayFront: true,
            associatedForms: ['product:description.images:after'],
            associatedApis: ['/products', '/products/{productId}'],
            labelWording: 'Video links',
            labelDomain: 'Modules.Quinceengine.Admin',
            descriptionWording: 'Addresses of YouTube or Vimeo films, separated by commas. They show up in the gallery next to the photos.',
            descriptionDomain: 'Modules.Quinceengine.Admin'
        )) !== false;
    }

    /**
     * Nazwa hooka, w który moduły wstawiają własne akcje nagłówka.
     *
     * Silnik nie zna z góry nazw tych modułów: ogłasza miejsce, a one same się
     * w nie wpinają.
     */
    public const HOOK_HEADER_ACTIONS = 'displayQuinceHeaderActions';

    /**
     * Zasoby blockwishlista, których motyw nie używa.
     *
     * Jego frontend to aplikacja Vue budująca własne przyciski w znacznikach
     * motywu klasycznego. Model i tabele zostają jego; podmieniamy wyłącznie
     * warstwę widoczną.
     *
     * @var string[]
     */
    private const WISHLIST_ASSETS = [
        'blockwishlistController',
        'blockwishlistGraphql',
        'blockwishlistVendors',
    ];

    /**
     * Własne strony listy życzeń modułu: tu `blockwishlistController` to nie
     * zbędny duplikat spod karty produktu, tylko jedyny skrypt, który w ogóle
     * rysuje treść strony (`<div class="wishlist-container">` jest pusty bez
     * niego). Zdejmowanie zasobów niżej musi je omijać, inaczej strona zostaje
     * z nieprzeliczonymi znacznikami Vue (`((title))` zamiast treści).
     *
     * @var string[]
     */
    private const WISHLIST_OWN_CONTROLLERS = [
        'BlockWishlistListsModuleFrontController',
        'BlockWishlistViewModuleFrontController',
    ];

    /**
     * Zdjęcie warstwy widocznej listy życzeń.
     *
     * Skrypt blockwishlista szuka na każdej karcie produktu elementu
     * `.thumbnail-container`, klasy z motywu klasycznego, której Quince nie ma.
     * `querySelector` zwraca null, `append` wywala się i skrypt przerywa pracę,
     * zostawiając błąd w konsoli na każdej stronie z listingiem.
     *
     * Zdejmujemy jego pliki, a nie jego hooki: rejestracje trzeba by odtwarzać
     * po każdej reinstalacji tamtego modułu, a jego dane w JS (adresy działań
     * i lista zapisanych produktów) są nam potrzebne: publikuje je ten sam hook
     * i zostają na miejscu.
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        if (!($this->context->controller instanceof FrontController)) {
            return;
        }

        // Zakup na firmę: przycisk „Pobierz dane”, streszczenia kroków kasy,
        // link regulaminu. Szczegóły w `Quince\Engine\Checkout\CompanyCheckout`.
        (new \Quince\Engine\Checkout\CompanyCheckout($this->context))->prepareFrontPage();

        $qNaWlasnejStronieWishlist = in_array(
            get_class($this->context->controller),
            self::WISHLIST_OWN_CONTROLLERS,
            true
        );

        if (!$qNaWlasnejStronieWishlist) {
            foreach (self::WISHLIST_ASSETS as $id) {
                $this->context->controller->unregisterJavascript($id);
            }

            $this->context->controller->unregisterStylesheet('blockwishlistController');
        }

        // Adresy dla naszego serca na karcie. Adres działania publikuje moduł
        // list życzeń (`blockwishlistController`), ale tylko jako zmienną JS.
        // Wyspa dostaje go tutaj razem z napisami i adresem logowania, żeby nie
        // musiała znać nazw tamtego modułu.
        Media::addJsDef(['quinceWishlist' => [
            'adres' => $this->context->link->getModuleLink('blockwishlist', 'action'),
            'logowanie' => $this->context->link->getPageLink(
                'authentication',
                true,
                null,
                ['back' => $this->context->link->getPageLink('my-account')]
            ),
            'dodaj' => $this->trans('Add to your saved products', [], 'Shop.Theme.Actions'),
            'usun' => $this->trans('Remove from your saved products', [], 'Shop.Theme.Actions'),
        ]]);

        // Podstrony konta, które są kontrolerami MODUŁÓW (dane osobowe
        // w `psgdpr`, „Moje powiadomienia” w `ps_emailalerts`), nie
        // ustawiają własnego `php_self`, więc `theme.yml` nigdy ich nie
        // dopasuje po kluczu strony (rdzeń woła `getPageSpecificAssets(
        // $this->php_self)`, a nie po nazwie widocznej w `$page.page_name`/
        // breadcrumbie, która u kontrolerów modułu bez `php_self` pochodzi
        // z zupełnie innej, zapasowej ścieżki). Dopinamy ten sam arkusz
        // i wyspę sidebaru ręcznie, jak dla reszty podstron konta.
        // Porównanie po nazwie klasy, nie `instanceof`: moduł może być
        // wyłączony, a `instanceof` na nieznanej klasie wywaliłby żądanie
        // fatalnym błędem.
        $qKontroleryModulowKonta = array_merge(
            [
                'psgdprgdprModuleFrontController',
                'Ps_EmailAlertsAccountModuleFrontController',
            ],
            self::WISHLIST_OWN_CONTROLLERS
        );

        if (in_array(get_class($this->context->controller), $qKontroleryModulowKonta, true)) {
            $this->context->controller->registerStylesheet(
                'quince-account-on-module-page',
                'assets/css/components/account.css',
                ['media' => 'all', 'priority' => 40]
            );
            $this->context->controller->registerJavascript(
                'quince-account-nav-on-module-page',
                'assets/js/islands/account-nav.js',
                ['position' => 'bottom', 'priority' => 100]
            );
        }

        // Tworzenie/zmiana nazwy/udostępnianie/usuwanie listy stoi tylko
        // na stronie z listą list, nie na podglądzie pojedynczej listy.
        if (get_class($this->context->controller) === 'BlockWishlistListsModuleFrontController') {
            $this->context->controller->registerJavascript(
                'quince-wishlist-lists',
                'assets/js/islands/wishlist-lists.js',
                ['position' => 'bottom', 'priority' => 100]
            );
        }

        // Podgląd pojedynczej listy dziedziczy po tym samym kontrolerze co
        // kategoria (`ProductListingFrontController`), ale ma własny klucz
        // strony, więc `theme.yml` nie dopasuje mu arkusza i wysp listingu
        // po nazwie (`category`, `manufacturer`…). Dopinamy je tak samo
        // ręcznie, jak arkusz konta wyżej.
        if (get_class($this->context->controller) === 'BlockWishlistViewModuleFrontController') {
            $this->context->controller->registerStylesheet(
                'quince-listing-on-wishlist-view',
                'assets/css/components/listing.css',
                ['media' => 'all', 'priority' => 40]
            );
            $this->context->controller->registerJavascript(
                'quince-select-on-wishlist-view',
                'assets/js/islands/select.js',
                ['position' => 'bottom', 'priority' => 99]
            );
            $this->context->controller->registerJavascript(
                'quince-listing-on-wishlist-view',
                'assets/js/islands/listing.js',
                ['position' => 'bottom', 'priority' => 100]
            );
        }
    }

    /**
     * Sprawdzenie numeru VAT przy zapisie adresu (zakup na firmę).
     *
     * @param array<string,mixed> $params
     */
    public function hookActionValidateCustomerAddressForm(array $params): void
    {
        (new \Quince\Engine\Checkout\CompanyCheckout($this->context))->validateAddress($params);
    }

    /**
     * Role adresów przy zakupie na firmę z osobnym adresem dostawy.
     *
     * @param array<string,mixed> $params
     */
    public function hookActionSubmitCustomerAddressForm(array $params): void
    {
        (new \Quince\Engine\Checkout\CompanyCheckout($this->context))->submitAddress($params);
    }

    /**
     * Rdzeń wywołuje stronę listy produktów wishlisty przez `doProductSearch()`
     * z GOŁĄ ścieżką względną (`../../../modules/blockwishlist/…`), nie przez
     * zasób `module:`, jak robi to `lists.php`. `TemplateFinder` taką ścieżkę
     * trawi dosłownie: przy katalogu szablonów motywu `../../../` zawsze
     * zwija się z powrotem do PLIKU MODUŁU, więc żaden motyw nie potrafi jej
     * podmienić zwykłym nadpisaniem pliku w `themes/<motyw>/modules/…`.
     *
     * Ten hook to oficjalna furtka rdzenia na dokładnie ten przypadek: zwrócona
     * ścieżka zasobem `module:` przechodzi już normalne szukanie nadpisania,
     * więc trafia w nasz plik w motywie.
     */
    public function hookDisplayOverrideTemplate(array $params): ?string
    {
        if ($params['controller'] instanceof \BlockWishlistViewModuleFrontController) {
            return 'module:blockwishlist/views/templates/pages/products-list.tpl';
        }

        return null;
    }

    /**
     * Czysty adres dla `/llms.txt` (Brand & Schema). Bez tego siedziałby pod
     * `index.php?fc=module&module=quince_engine&controller=llmstxt`, a
     * silniki AI szukają go dokładnie pod `/llms.txt`. PrestaShop liczy tę
     * trasę osobno dla każdego sklepu i każdego jego języka
     * (`Dispatcher::loadRoutes()`), więc wielosklepowość i wielojęzyczność
     * załatwia sam rdzeń, a kontroler dostaje już właściwy kontekst.
     *
     * @return array<string,array{controller:string,rule:string,keywords:array<string,mixed>,params:array<string,string>}>
     */
    public function hookModuleRoutes(): array
    {
        return [
            'module-quince_engine-llmstxt' => [
                'controller' => 'llmstxt',
                'rule' => 'llms.txt',
                'keywords' => [],
                'params' => [
                    'fc' => 'module',
                    'module' => 'quince_engine',
                    'controller' => 'llmstxt',
                ],
            ],
        ];
    }

    /**
     * Ogłoszenie miejsca na akcje modułów.
     *
     * `Hook::add()` przed rejestracją z tego samego powodu, co przy zgłoszeniach
     * możliwości: PrestaShop po cichu pomija rejestrację na hook, którego nie ma
     * jeszcze w tabeli.
     */
    public function registerActionsHook(): bool
    {
        if (Hook::getIdByName(self::HOOK_HEADER_ACTIONS)) {
            return true;
        }

        $hook = new Hook();
        $hook->name = self::HOOK_HEADER_ACTIONS;
        $hook->title = 'Quince: akcje modułów w nagłówku';
        $hook->description = 'Miejsce obok konta, ulubionych i koszyka. Moduł rysuje własną '
            . 'akcję klasami motywu (q-action, q-action__icon, q-action__count), '
            . 'żeby wyglądała jak reszta pasa.';
        $hook->position = false;

        return (bool) $hook->add();
    }

    /**
     * Ogłoszenie miejsca dla modułów captchy.
     *
     * Strażnik formularzy pyta ten hook o zdanie po swoich własnych zaporach.
     * Bez zainstalowanego dostawcy nikt się nie zgłasza i formularze działają
     * dalej: captcha jest warstwą, nie warunkiem.
     */
    public function registerFormVerifyHook(): bool
    {
        if (Hook::getIdByName(Quince\Engine\Form\FormGuard::HOOK_VERIFY)) {
            return true;
        }

        $hook = new Hook();
        $hook->name = Quince\Engine\Form\FormGuard::HOOK_VERIFY;
        $hook->title = 'Quince: ocena zgłoszenia formularza';
        $hook->description = 'Moduł captchy zwraca true (przepuszczam), false (odrzucam) '
            . 'albo nic, gdy nie jest skonfigurowany. Jedno „odrzucam" zatrzymuje zgłoszenie.';
        $hook->position = false;

        return (bool) $hook->add();
    }

    /**
     * Licznik przyjętych zgłoszeń, na którym stoi limit powtórzeń z jednego adresu.
     *
     * Adres trzymamy jako skrót i kasujemy wiersze starsze niż okno limitu, więc
     * tabela nie rośnie i nie zbiera danych, których nikt nie potrzebuje.
     */
    public function installFormHitTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'quince_form_hit` (
                `ip_hash` VARCHAR(40) NOT NULL,
                `form_id` VARCHAR(64) NOT NULL,
                `submitted_at` INT(11) UNSIGNED NOT NULL,
                INDEX `idx_okno` (`ip_hash`, `form_id`, `submitted_at`),
                INDEX `idx_sprzatanie` (`submitted_at`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Hook, którym nasze moduły zgłaszają silnikowi, co dostarczają.
     *
     * `Hook::add()` MUSI iść przed rejestracją: PrestaShop po cichu pomija
     * rejestrację na hook, którego nie ma jeszcze w tabeli. Błąd widać dopiero
     * na czystym sklepie, gdzie hook nie został po wcześniejszej instalacji.
     */
    public function registerFeatureHook(): bool
    {
        if (!Hook::getIdByName(Quince\Engine\Feature\FeatureRegistry::HOOK)) {
            $hook = new Hook();
            $hook->name = Quince\Engine\Feature\FeatureRegistry::HOOK;
            $hook->title = 'Quince: moduł zgłasza, co dostarcza';
            $hook->description = 'Zbiera deklaracje modułów Quince: wyszukiwarka, menu, karuzela, lista życzeń.';
            $hook->position = false;

            if (!$hook->add()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dopisuje otwarty produkt do historii odwiedzającego.
     *
     * Historię trzyma ciastko `viewed`, to samo, z którego korzysta natywny
     * blok ostatnio oglądanych. Zapisujemy je sami, żeby pas „ostatnio
     * oglądane" na stronie głównej działał także w sklepie, który tamtego
     * bloku nie włączył.
     */
    private function zapiszOgladany(): void
    {
        if (!($this->context->controller instanceof ProductController)) {
            return;
        }

        $id = (int) Tools::getValue('id_product');

        if ($id <= 0) {
            return;
        }

        $ciastko = $this->context->cookie->viewed ?? '';
        $numery = is_string($ciastko) && $ciastko !== ''
            ? array_values(array_filter(array_map('intval', explode(',', $ciastko))))
            : [];

        if (in_array($id, $numery, true)) {
            return;
        }

        $numery[] = $id;

        // Ostatnie kilkanaście wystarczy: ciastko ma swój limit długości,
        // a dłuższej historii i tak nikt nie pokazuje.
        $this->context->cookie->viewed = implode(',', array_slice($numery, -20));
    }

    /**
     * Sprawdzenie wysyłanego formularza, zanim kontroler go przetworzy.
     *
     * Interesują nas wyłącznie wysyłki niosące pola strażnika; resztę ruchu
     * przepuszczamy bez dotykania. Odrzucone zgłoszenie kończy się opróżnieniem
     * danych żądania: żaden formularz nie zobaczy wtedy swojego przycisku jako
     * wciśniętego, więc nikt niczego nie przetworzy, a klient dostaje jedno
     * zdanie wyjaśnienia zamiast pustej strony.
     */
    public function hookActionFrontControllerInitAfter(): void
    {
        // Filmy produktu prosto do szablonu. NIE przez zmienne frontu: te
        // PrestaShop wypisuje także jako zmienne JavaScriptu strony, a moduł
        // zgód dopasowuje reguły blokowania po treści skryptów, więc adres
        // z YouTube wycina tam skrypt rdzenia razem z sobą.
        $this->context->smarty->assign('qFilmy', $this->filmyProduktu());
        $this->context->smarty->assign('qZestawy', $this->zestawyProduktu());
        $this->zapisKwot();
        $this->zapiszOgladany();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $straznik = new \Quince\Engine\Form\FormGuard();
        $token = (string) ($_POST[\Quince\Engine\Form\FormGuard::FIELD_TOKEN] ?? '');

        if ($token === '') {
            return;
        }

        $formularz = (string) ($_POST[\Quince\Engine\Form\FormGuard::FIELD_FORM] ?? 'form');
        $wynik = $straznik->check($formularz, $_POST);

        if ($wynik->passed) {
            $straznik->accept($formularz);

            return;
        }

        PrestaShopLogger::addLog($wynik->logLine($formularz), 2, null, 'Quince', null, true);

        $_POST = [];
        $_REQUEST = $_GET;

        $this->context->controller->errors[] = $this->trans(
            'We could not accept this form. Please reload the page and try again.',
            [],
            'Modules.Quinceengine.Shop'
        );
    }

    /**
     * Filmy oglądanego produktu, prosto do szablonu.
     *
     * NIE przez `actionFrontControllerSetVariables`: tamte dane PrestaShop
     * wypisuje także jako zmienne JavaScriptu strony, a moduł zgód dopasowuje
     * swoje reguły po treści skryptów. Adres z YouTube w zmiennych JS pasuje
     * do reguły blokowania i moduł zgód wycina skrypt rdzenia razem z nim,
     * przez co sklep traci cały JavaScript.
     *
     * Liczone tylko na karcie produktu.
     *
     * @return array<int,array<string,string>>
     */
    private function filmyProduktu(): array
    {
        $kontroler = $this->context->controller->php_self ?? '';

        if ($kontroler !== 'product') {
            return [];
        }

        return (new \Quince\Engine\Product\Videos())->dlaProduktu(
            (int) Tools::getValue('id_product'),
            (int) $this->context->shop->id,
            (int) $this->context->language->id
        );
    }

    /**
     * Zestawy, w których występuje oglądany produkt, do sekcji pod kartą.
     *
     * Tą samą drogą co filmy i z tego samego powodu: nazwy i adresy zestawów
     * nie mają czego szukać w zmiennych JavaScriptu strony. Odświeżenie po
     * zmianie wariantu sekcji zestawów nie rysuje, więc wtedy nic nie liczymy.
     *
     * @return array<int,array<string,mixed>>
     */
    private function zestawyProduktu(): array
    {
        if (($this->context->controller->php_self ?? '') !== 'product' || Tools::getValue('ajax')) {
            return [];
        }

        return (new \Quince\Engine\Product\Bundles())->dlaProduktu(
            (int) Tools::getValue('id_product'),
            $this->context
        );
    }

    /**
     * Modyfikator `qprice`: surowa liczba zapisana walutą sklepu.
     *
     * PrestaShop formatuje szablonowi tylko część kwot: cena netto produktu
     * przychodzi jako goła liczba zmiennoprzecinkowa. Zapis zależy od języka
     * i waluty odwiedzającego, więc szablon nie może go złożyć sam.
     *
     * Rejestracja przy każdym żądaniu, bo Smarty gubi ją razem z kontekstem;
     * ponowne zgłoszenie tej samej nazwy Smarty uznaje za błąd, stąd sprawdzenie.
     */
    private function zapisKwot(): void
    {
        $modyfikatory = [
            'qprice' => [\Quince\Engine\Ui\PriceFormat::class, 'format'],

            // Daty przychodzą do szablonu surowe, a Smarty nie umie ani policzyć
            // różnicy dwóch dat, ani zapisać daty po ludzku. Obie rzeczy są
            // potrzebne przy pasie promocji.
            'qdays' => [\Quince\Engine\Ui\DateFormat::class, 'days'],
            'qstamp' => [\Quince\Engine\Ui\DateFormat::class, 'stamp'],
            'qdate' => [\Quince\Engine\Ui\DateFormat::class, 'short'],

            // Pasek darmowej dostawy w szufladzie koszyka: próg sklepu i wartość
            // koszyka liczone tak, jak liczy je kasa.
            'qfreeshipping' => [\Quince\Engine\Cart\FreeShipping::class, 'forTemplate'],

            // Miejsca promocyjne w siatce listingu, przeliczone z numeru rzędu
            // na numer kafla. Zależą od liczby kolumn i od tego, ile produktów
            // pokazały wcześniejsze strony, więc liczy się je dopiero tutaj.
            'qslots' => [\Quince\Engine\Settings\ListingSettings::class, 'slots'],

            // Kolejność cech dla JEDNEGO produktu, liczona rankingiem. Na karcie
            // produktu silnik podaje ją raz, w zmiennych strony; na listingu
            // produktów jest wiele i każdy ma własną kategorię, więc szablon
            // pyta o nią osobno dla każdej zajawki.
            'qcechy' => [\Quince\Engine\Feature\FeatureOrder::class, 'forProduct'],

            // Odcień plakietki statusu zamówienia (panel konta, podgląd
            // zamówienia): zawsze z naszych czterech tokenów, nigdy
            // z dowolnego koloru wpisanego w panelu przy statusie.
            'qstatustone' => [\Quince\Engine\Feature\OrderStatusTone::class, 'forState'],
        ];

        $smarty = $this->context->smarty;

        foreach ($modyfikatory as $nazwa => $wywolanie) {
            if (isset($smarty->registered_plugins['modifier'][$nazwa])) {
                continue;
            }

            $smarty->registerPlugin('modifier', $nazwa, $wywolanie);
        }
    }

    /**
     * Liczba zawężeń, którymi klient zwęził listę.
     *
     * Rdzeń podaje szablonowi gotowy HTML pigułek, ale nie ich liczbę, a ta
     * stoi przy przycisku filtrów i mówi, czy klient patrzy na całą kategorię,
     * czy na jej wycinek. Policzyć da się tylko tutaj: `$listing` jest wtedy
     * jeszcze tablicą z obiektem wyniku, a w szablonie zostaje z niego sam
     * kawałek HTML-a.
     *
     * Hook przekazuje tablicę przez referencję, więc dopisujemy do niej klucz
     * zamiast cokolwiek zwracać.
     *
     * @param array<string,mixed> $params
     */
    public function hookFilterProductSearch(array $params): void
    {
        if (!isset($params['searchVariables']['result'])) {
            return;
        }

        $wynik = $params['searchVariables']['result'];

        if (!$wynik instanceof \PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult) {
            return;
        }

        $kolekcja = $wynik->getFacetCollection();
        $ile = 0;

        if ($kolekcja !== null) {
            foreach ($kolekcja->getFacets() as $grupa) {
                foreach ($grupa->getFilters() as $zawezenie) {
                    if ($zawezenie->isActive()) {
                        ++$ile;
                    }
                }
            }
        }


        // Oceny wszystkich produktów tej strony jednym zapytaniem. Moduł opinii
        // dociąga je skryptem, przez co średnia nie trafia do kodu strony,
        // lista podskakuje po wczytaniu i przy włączonym sklejaniu plików
        // skrypt w ogóle nie dojeżdża. Liczymy więc sami i podajemy gotowe.
        $idProduktow = [];

        foreach ($params['searchVariables']['products'] ?? [] as $produkt) {
            $idProduktow[] = (int) ($produkt['id_product'] ?? 0);
        }

        $params['searchVariables']['quince_ratings']
            = \Quince\Engine\Feature\ListingRatings::forProducts($idProduktow);
        $params['searchVariables']['quince_active_filters'] = $ile;
    }

    /**
     * Przekazuje szablonom dane, których nie da się wyliczyć w Smarty.
     *
     * Treści topbara przychodzą tu gotowe: teksty rozwiązane dla bieżącego języka,
     * widoczność zamieniona na klasy CSS. Szablon ma je tylko wypisać.
     *
     * @return array<string,mixed>
     */
    public function hookActionFrontControllerSetVariables(array $params = []): array
    {
        $idLang = (int) $this->context->language->id;

        // Brand & Schema: od PS 9.2 rdzeń sam liczy Organization/WebSite/WebPage
        // (`FrontController::getStructuredData()`) i podaje gotowy `structured_data`
        // w tych samych `templateVars`, zanim ten hook się wykona. Tablica
        // przychodzi przez referencję, więc dopisanie do niej tutaj jest dokładnie
        // tym rozszerzeniem, o którym mówi dokumentacja tamtej metody. Starszy
        // szablon (`_partials/microdata/head-jsonld.tpl`) renderuje się tylko
        // wtedy, gdy `structured_data` jest puste; tam ta sama treść ma osobną
        // ścieżkę, liczoną wprost w Smarty.
        if (isset($params['templateVars']['structured_data']['organization'])) {
            $params['templateVars']['structured_data']['organization'] = array_merge(
                $params['templateVars']['structured_data']['organization'],
                (new \Quince\Engine\Settings\BrandSchemaSettings())->organizationOverrides()
            );
        }

        // PrestaShop wkłada zwrócone dane pod $modules.quince_engine, więc własne
        // zagnieżdżanie tylko wydłużyłoby ścieżkę w szablonie.
        return [
            'topbar' => (new \Quince\Engine\Settings\TopbarSettings())->forTemplate($idLang),
            'header' => (new \Quince\Engine\Settings\HeaderSettings())->forTemplate($idLang),
            'navigation' => (new \Quince\Engine\Settings\NavigationSettings())->forTemplate($idLang),
            'footer' => (new \Quince\Engine\Settings\FooterSettings())->forTemplate($idLang),

            // Wariant kasy nie trafia tu: szablon go nie potrzebuje, decyduje
            // o nim sam rdzeń. Hook pod „Order details" na potwierdzeniu
            // zamówienia owszem, bo to zwykłe miejsce na treść modułu.
            'checkout' => (new \Quince\Engine\Settings\CheckoutSettings())->forTemplate(),

            // Koszyk: widoczność bloku zaufania i hook pod dokupką.
            'cart' => (new \Quince\Engine\Settings\CartSettings())->forTemplate(),

            // Panel klienta: liczniki i ostatnie zamówienia. Tylko na tej
            // stronie; poza nią zwracamy null tym samym sposobem, co ocena
            // produktu poza kartą produktu.
            'account' => $this->panelKlienta($idLang),

            // Układ strony produktu: strefy z listą elementów w kolejności.
            // Szablon renderuje z tych list, więc przestawienie w panelu nie
            // wymaga ruszania motywu.
            'product' => (new \Quince\Engine\Settings\ProductPageSettings())->forTemplate(),

            // Układ listingu. Jeden zestaw na siedem stron z listą produktów:
            // kategorię, markę, dostawcę, wyszukiwarkę, nowości, promocje
            // i bestsellery. Wszystkie rozszerzają ten sam szablon PrestaShopa
            // i różnią się wyłącznie nagłówkiem.
            'listing' => (new \Quince\Engine\Settings\ListingSettings())->forTemplate(),

            // Zajawka produktu: co pokazuje kafel, a co rząd. Oba zestawy naraz,
            // bo ten sam szablon renderuje jedno i drugie, a o tym, co widać,
            // decyduje arkusz.
            'card' => (new \Quince\Engine\Settings\ProductCardSettings())->forTemplate(),

            // Ocena produktu: średnia i liczba opinii z modułu opinii, policzone
            // po naszemu. Tylko na stronie produktu: na listingu ocenę rysuje
            // karta produktu z własnych danych.
            'rating' => $this->ocenaProduktu(),

            // Pierwsza porcja opinii. Stoi w kodzie strony, żeby widziała ją
            // wyszukiwarka i klient bez JavaScriptu; kolejne dokłada kontroler
            // `reviews` na kliknięcie.
            'reviews' => $this->opinieProduktu(),


            // Kolejność cech podstawowych. Silnik sam jej nie liczy: to statystyka
            // z całej kategorii, czyli własna tabela i zadanie w tle, więc robi to
            // moduł. Bez niego zostaje `null`, a szablon bierze cechy w kolejności
            // katalogu.
            'features' => $this->kolejnoscCech(),

            // Pola strażnika są te same dla wszystkich formularzy na stronie:
            // znacznik podpisuje moment wydania strony, nie pojedynczy formularz.
            'formGuard' => (new \Quince\Engine\Form\FormGuard())->fields(),
        ];
    }

    /**
     * Ocena produktu, którego dotyczy bieżąca strona.
     *
     * Poza stroną produktu zwracamy null: hook wypisuje zmienne na każdej
     * stronie sklepu, a zapytanie o opinie ma sens tylko przy jednym produkcie.
     *
     * @return array<string,mixed>|null
     */
    private function ocenaProduktu(): ?array
    {
        if (!($this->context->controller instanceof ProductController)) {
            return null;
        }

        $produkt = $this->context->controller->getProduct();

        return $produkt ? \Quince\Engine\Product\Rating::forProduct((int) $produkt->id) : null;
    }

    /**
     * Liczniki i ostatnie zamówienia na panelu klienta (`my-account`).
     *
     * Poza tą stroną zwracamy null z tego samego powodu, co przy ocenie
     * produktu: policzenie zamówień i kuponów to osobne zapytania do bazy,
     * zbędne na każdej innej stronie sklepu.
     *
     * @return array<string,mixed>|null
     */
    private function panelKlienta(int $idLang): ?array
    {
        if (!($this->context->controller instanceof MyAccountController)) {
            return null;
        }

        if (!$this->context->customer || !$this->context->customer->isLogged()) {
            return null;
        }

        return \Quince\Engine\Feature\AccountDashboard::forCustomer(
            (int) $this->context->customer->id,
            $idLang
        );
    }

    /**
     * Pierwsza porcja opinii o produkcie, którego dotyczy bieżąca strona.
     *
     * Poza stroną produktu zwracamy null z tego samego powodu, co przy ocenie.
     *
     * @return array<string,mixed>|null
     */
    private function opinieProduktu(): ?array
    {
        if (!($this->context->controller instanceof ProductController)) {
            return null;
        }

        $produkt = $this->context->controller->getProduct();

        if (!$produkt) {
            return null;
        }

        $porcja = \Quince\Engine\Product\Reviews::forProduct((int) $produkt->id);

        if ($porcja === null) {
            return null;
        }

        // Kryteria oceny idą razem z listą, bo potrzebuje ich formularz stojący
        // w tej samej sekcji. Osobne wywołanie z szablonu wymagałoby, żeby
        // szablon wiedział o istnieniu modułu opinii.
        $porcja['criteria'] = \Quince\Engine\Product\Reviews::criteria(
            (int) $produkt->id,
            (int) $this->context->language->id
        );

        // Dwa ustawienia modułu, o które pyta szablon: czy ten odwiedzający może
        // napisać opinię i czy sklep zbiera głosy „przydatne".
        $porcja['canPost'] = \Quince\Engine\Product\Reviews::canPost();
        $porcja['usefulness'] = \Quince\Engine\Product\Reviews::usefulnessEnabled();
        $porcja['moderated'] = \Quince\Engine\Product\Reviews::moderated();

        return $porcja;
    }

    /**
     * Nazwy cech produktu w kolejności, w jakiej mają stanąć pod opisem.
     *
     * Pytamy modułu rankingu przez rejestr możliwości, a nie po nazwie klasy:
     * silnik nie zna z góry nazw naszych modułów, a sklep bez tego modułu ma
     * działać dalej. Zwracamy NAZWY, bo rdzeń podaje szablonowi `grouped_features`
     * bez identyfikatorów cech, a kluczem jest tam nazwa.
     *
     * @return string[]|null
     */
    private function kolejnoscCech(): ?array
    {
        if (!($this->context->controller instanceof ProductController)) {
            return null;
        }

        $produkt = $this->context->controller->getProduct();

        if (!$produkt) {
            return null;
        }

        $dostawcy = (new \Quince\Engine\Feature\FeatureRegistry())
            ->providersFor(\Quince\Engine\Feature\FeatureRegistry::TYPE_FEATURE_RANK);

        foreach ($dostawcy as $dostawca) {
            $modul = $dostawca['module'] !== '' ? Module::getInstanceByName($dostawca['module']) : null;

            if ($modul && method_exists($modul, 'nazwyCechWKolejnosci')) {
                return $modul->nazwyCechWKolejnosci(
                    (int) $produkt->id,
                    (int) $this->context->shop->id,
                    (int) $this->context->language->id
                );
            }
        }

        return null;
    }

    /**
     * Zapamiętane drzewo kategorii przestaje być prawdą, gdy katalog się zmieni.
     *
     * Czas życia by tu nie wystarczył: nowa kategoria ma być w menu od razu.
     * Kasujemy więc zapis przy każdej zmianie w katalogu; kosztuje to jedno
     * przeliczenie przy najbliższym wejściu na sklep.
     */
    public function hookActionCategoryAdd(): void
    {
        \Quince\Engine\Catalog\CategoryTreeCache::flush();
    }

    public function hookActionCategoryUpdate(): void
    {
        \Quince\Engine\Catalog\CategoryTreeCache::flush();
    }

    public function hookActionCategoryDelete(): void
    {
        \Quince\Engine\Catalog\CategoryTreeCache::flush();
    }

    /**
     * Silnik jako dostawca listy życzeń.
     *
     * Nakładka na blockwishlist: jego tabele i model zostają, podmieniamy warstwę
     * widoczną, bo jego własny frontend jest aplikacją Vue, której nie da się
     * czysto ostylować. Zgłaszamy się tylko wtedy, gdy on w sklepie jest: bez
     * jego tabel nie ma gdzie zapisywać produktów.
     *
     * @return array<int,array<string,string>>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        if (!Module::isInstalled('blockwishlist')) {
            return [];
        }

        return [[
            'type' => \Quince\Engine\Feature\FeatureRegistry::TYPE_WISHLIST,
            'id' => 'quince_engine',
            'label' => 'Wishlist',
        ]];
    }

    /**
     * Strona główna złożona z sekcji.
     *
     * Silnik mówi GDZIE: stawia pasy, dzieli je na kolumny i daje im tło.
     * CO w nich stanie, przychodzi z hooków (slajder, bloki WiseBlocka,
     * karuzele modułów). Dzięki temu dołożenie sekcji nie wymaga zmiany
     * w motywie, a dołożenie modułu nie wymaga zmiany tutaj.
     *
     * Ustawienia: `Wygląd → Quince → Strona główna`.
     */
    public function hookDisplayHome(): string
    {
        // Obiekt tworzymy wprost, bez kontenera: usługi z config/services.yml
        // rejestrują się w kontenerze back-office, a na froncie `$this->get()`
        // zwraca null.
        $sekcje = (new \Quince\Engine\Settings\HomepageSettings())->forTemplate();

        if ($sekcje === []) {
            return '';
        }

        $jezyk = (int) $this->context->language->id;

        foreach ($sekcje as $i => $sekcja) {
            $sekcje[$i]['title'] = \Quince\Engine\Settings\HomepageSettings::text($sekcja['title'], $jezyk);
            $sekcje[$i]['link_label'] = \Quince\Engine\Settings\HomepageSettings::text($sekcja['link_label'], $jezyk);

            // Ikona nagłówka: kod SVG z biblioteki silnika, a nie nazwa;
            // szablon ma go tylko wstawić.
            $sekcje[$i]['icon_svg'] = ($sekcja['icon'] ?? '') !== ''
                ? \Quince\Engine\Ui\IconLibrary::svg((string) $sekcja['icon'])
                : '';
        }

        // Kolumny z katalogu dostaja gotowe zajawki w postaci, ktorej oczekuje
        // motyw. Pobranie idzie tutaj, a nie w szablonie: szablon ma ustawiac,
        // a nie odpytywac bazy.
        $produkty = new \Quince\Engine\Products\HomeProducts($this->context);

        foreach ($sekcje as $i => $sekcja) {
            foreach ($sekcja['columns'] as $j => $kolumna) {
                $rodzaj = (string) ($kolumna['type'] ?? '');

                if ($rodzaj === 'products') {
                    $sekcje[$i]['columns'][$j]['items'] = $produkty->present($produkty->ids($kolumna));
                } elseif ($rodzaj === 'product') {
                    $sekcje[$i]['columns'][$j]['item'] = $produkty->one((int) ($kolumna['product'] ?? 0));
                    $sekcje[$i]['columns'][$j]['extras'] = $this->zapewnienia($kolumna, $jezyk);
                } elseif ($rodzaj === 'categories') {
                    $kafle = $produkty->categories($kolumna);

                    $sekcje[$i]['columns'][$j]['tiles'] = $kafle;
                    $sekcje[$i]['columns'][$j]['button'] = \Quince\Engine\Settings\HomepageSettings::text(
                        $kolumna['button'] ?? [],
                        $jezyk
                    );

                    // Kafle stoja w jednym rzedzie, dopoki miesci je siatka
                    // sklepu; wiecej wybranych dzialow schodzi do drugiego
                    // rzedu, zamiast zwezac kazdy kafel do paska.
                    $sekcje[$i]['columns'][$j]['per_row'] = min(
                        count($kafle),
                        \Quince\Engine\Settings\HomepageSettings::gridColumns()
                    );
                }
            }
        }

        // Sekcja, w ktorej zadna kolumna nic nie pokazuje, nie zostawia pustego
        // pasa: tak gasnie „ostatnio ogladane" u kogos, kto wchodzi pierwszy raz.
        $sekcje = array_values(array_filter($sekcje, static function (array $sekcja): bool {
            foreach ($sekcja['columns'] as $kolumna) {
                $rodzaj = (string) ($kolumna['type'] ?? '');

                if ($rodzaj === 'hook' && ($kolumna['hook'] ?? '') !== '') {
                    return true;
                }

                if ($rodzaj === 'products' && ($kolumna['items'] ?? []) !== []) {
                    return true;
                }

                if ($rodzaj === 'product' && !empty($kolumna['item'])) {
                    return true;
                }

                if ($rodzaj === 'categories' && ($kolumna['tiles'] ?? []) !== []) {
                    return true;
                }
            }

            return false;
        }));

        $this->smarty->assign(['quince_sections' => $sekcje]);

        return $this->display(__FILE__, 'views/templates/hook/homepage.tpl');
    }

    /**
     * Zapewnienia przy dużej zajawce, przełożone na język odwiedzającego.
     *
     * Wyróżniona wartość bywa pusta: wtedy wychodzi zwykła pigułka, a nie blok
     * z pustym miejscem nad napisem.
     *
     * @param array<string,mixed> $kolumna
     *
     * @return array<int,array<string,string>>
     */
    private function zapewnienia(array $kolumna, int $jezyk): array
    {
        $gotowe = [];

        foreach ($kolumna['extras'] ?? [] as $zapewnienie) {
            $tekst = \Quince\Engine\Settings\HomepageSettings::text($zapewnienie['text'] ?? [], $jezyk);

            if ($tekst === '') {
                continue;
            }

            $gotowe[] = [
                'lead' => \Quince\Engine\Settings\HomepageSettings::text($zapewnienie['lead'] ?? [], $jezyk),
                'text' => $tekst,
            ];
        }

        return $gotowe;
    }

    /**
     * Wypisuje warstwę pierwszą tokenów do nagłówka sklepu.
     *
     * Role kolorystyczne i wartości bazowe zależą od ustawień sprzedawcy, więc nie mogą
     * leżeć w pliku na dysku: muszą powstawać przy każdym żądaniu. Warstwy druga
     * i trzecia (pochodne i tokeny komponentu) siedzą w themes/quince/assets/css/tokens.css,
     * bo są stałe.
     *
     * Deklaracja idzie w warstwie kaskady `quince.engine`, zadeklarowanej przed
     * `quince.tokens`. Dzięki temu kolejność jest jednoznaczna niezależnie od tego,
     * w którym miejscu <head> wyląduje arkusz motywu.
     */
    public function hookDisplayHeader(): string
    {
        // Obiekty tworzymy wprost, bez kontenera: usługi z config/services.yml modułu
        // rejestrują się w kontenerze back-office, a na froncie `$this->get()` zwraca null.
        $settings = new \Quince\Engine\Settings\AppearanceSettings(
            new \Quince\Engine\Appearance\PaletteGenerator(),
            new \Quince\Engine\Appearance\MetricsGenerator()
        );

        $tokens = $settings->tokens();

        // Pasy topbara mają własny kolor, ustawiany na ekranie topbara. Ich role
        // dokładamy PO tokenach palety, żeby nadpisały te wyliczone z decyzji
        // `BAND_TOPBAR`. Inaczej wybór ze skali nie miałby żadnego skutku.
        $paleta = $settings->palette();

        $tokens = array_merge(
            $tokens,
            (new \Quince\Engine\Settings\TopbarSettings())->bandTokens($paleta),
            (new \Quince\Engine\Settings\HeaderSettings(
                new \Quince\Engine\Feature\FeatureRegistry()
            ))->bandTokens($paleta),
            (new \Quince\Engine\Settings\NavigationSettings(
                new \Quince\Engine\Feature\FeatureRegistry()
            ))->bandTokens($paleta),
            (new \Quince\Engine\Settings\FooterSettings())->bandTokens($paleta),

            // Toasty wychodzą nad treścią każdej strony, więc ich tło jest
            // decyzją globalną, stąd tutaj, a nie w ustawieniach pasa.
            (new \Quince\Engine\Appearance\PaletteGenerator())->toastTokens(
                $settings->all()['TOAST'] ?? 'dark',
                $paleta
            )
        );

        // Proporcja obrazu pochodzi z konfiguracji sklepu, nie z ustawień motywu,
        // dlatego dokładana jest tutaj, a nie w generatorach. Nazwa idzie ze spisu
        // tokenów wypisywanych przy żądaniu, bo tego samego spisu pilnuje kontrola
        // arkuszy: powtórzenie tej nazwy w tokens.css unieważniłoby tę wartość.
        $tokens[\Quince\Engine\Appearance\RuntimeTokens::PCARD_MEDIA_RATIO]
            = (new \Quince\Engine\Catalog\ImageRatioProvider())->forListing();

        // Wygląd powierzchni strony produktu: tło, ramka, cień i zaokrąglenie
        // każdej karty z osobna. Wypisujemy tylko to, co sklep ustawił;
        // powierzchnia bez własnego ustawienia zostaje przy wartości motywu.
        // Przeskok kreski: różnica między stopniem wskazanym w Wyglądzie a globalnym
        // tłem kart. Blok z własnym tłem dostaje kreskę o tyle samo stopni od SWOJEGO
        // tła, więc ustawienie znaczy to samo na białej karcie i na szarym pasie.
        $wyglad = $settings->all();
        $tloKart = (int) ($wyglad['SURFACE_CARD'] ?? 3);
        $przeskoki = [
            'card' => (int) ($wyglad['BORDER_CARD'] ?? 5) - $tloKart,
            'input' => (int) ($wyglad['BORDER_FIELD'] ?? 6) - $tloKart,
            'table' => (int) ($wyglad['BORDER_TABLE'] ?? 5) - $tloKart,
        ];

        $tokens = array_merge(
            $tokens,
            (new \Quince\Engine\Settings\ProductPageSettings())->surfaceTokens($paleta, $przeskoki),
            // Ten sam zestaw czterech ustawień dla bloków listingu: belki
            // narzędzi, panelu filtrów, kafelka podkategorii i pasa z zapowiedzią.
            (new \Quince\Engine\Settings\ListingSettings())->surfaceTokens($paleta, $przeskoki)
        );

        $lines = [];
        foreach ($tokens as $name => $value) {
            // Wartości pochodzą z generatorów i z bazy. Do CSS trafiają wyłącznie znaki,
            // które mogą wystąpić w wartości właściwości; reszta wypada.
            $safe = preg_replace('/[^a-zA-Z0-9#().,%\/\s\'"-]/', '', (string) $value);
            $lines[] = sprintf('--q-%s:%s', $name, trim((string) $safe));
        }

        $output = '<style id="quince-tokens">@layer quince.engine{:root{'
            . implode(';', $lines)
            . ';}}</style>' . "\n";

        // Brand & Schema: wzbogacony Organization JSON-LD trafia do Smarty tutaj,
        // bo `_partials/microdata/head-jsonld.tpl` renderuje się w tym samym
        // przebiegu co ten hook, ale dalej w kolejce, więc zmienna jest już gotowa,
        // zanim szablon po nią sięgnie.
        $brandSchema = new \Quince\Engine\Settings\BrandSchemaSettings();
        $this->context->smarty->assign('quince_organization_jsonld', $brandSchema->organizationJsonLd($this->context));

        // Domyślne <meta>, tylko gdy sklep je ustawił w Brand & Schema.
        foreach ($brandSchema->defaultMetaTags($this->context) as $tag) {
            $output .= '<meta ' . $tag['attr'] . ' content="' . htmlspecialchars($tag['content'], ENT_QUOTES) . "\">\n";
        }

        return $output;
    }

    /**
     * Odinstalowanie zabiera wszystko, co instalacja założyła.
     *
     * Bez tego sklep, który przestał używać silnika, zostaje z dwiema tabelami
     * i ponad setką kluczy konfiguracji.
     * Klucze kasujemy z nazw wyliczonych ze schematów, nigdy wzorcem
     * `LIKE 'QUINCE_%'`: pod ten wzorzec podpadają też klucze innych modułów
     * rodziny, których silnik nie zakładał i nie ma prawa ruszać.
     */
    public function uninstall(): bool
    {
        (new \Quince\Engine\Form\FormGuard())->dropTable();

        (new \Quince\Engine\Form\FormGuard())->removeSettings();

        foreach ($this->settingKeys() as $klucz) {
            Configuration::deleteByName($klucz);
        }

        // Definicja pola znika, wpisane adresy zostają: ponowna instalacja
        // przywraca je na kartach zamiast kazać wpisywać wszystko od nowa.
        $this->unregisterExtraProperty(new \PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition(
            entityName: 'product',
            propertyName: self::FIELD_VIDEOS,
            type: \PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType::STRING,
            scope: \PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope::LANG,
            moduleName: $this->name
        ), false);

        return parent::uninstall();
    }

    /**
     * Klucze konfiguracji założone przez ekrany silnika.
     *
     * Każda klasa ustawień wylicza swoje nazwy ze schematu, więc dołożenie pola
     * nie wymaga dopisywania go tutaj i nie zostawia po sobie klucza.
     *
     * @return string[]
     */
    private function settingKeys(): array
    {
        $klucze = [];

        foreach ([
            \Quince\Engine\Settings\AppearanceSettings::class,
            \Quince\Engine\Settings\TopbarSettings::class,
            \Quince\Engine\Settings\HeaderSettings::class,
            \Quince\Engine\Settings\NavigationSettings::class,
            \Quince\Engine\Settings\FooterSettings::class,
            \Quince\Engine\Settings\ProductPageSettings::class,
            \Quince\Engine\Settings\HomepageSettings::class,
        ] as $klasa) {
            $klucze = array_merge($klucze, $klasa::configurationKeys());
        }

        return array_unique($klucze);
    }

    /**
     * Grant access to every customer group.
     *
     * Without rows in ps_module_group, Hook::getHookModuleExecList() filters the module out
     * on the frontend even though it is enabled and its hooks are registered.
     */
    public function installModuleGroups(): bool
    {
        $db = Db::getInstance();
        $idModule = (int) $this->id ?: (int) Module::getModuleIdByName($this->name);
        if ($idModule <= 0) {
            return false;
        }

        foreach (Shop::getShops(false, null, true) as $idShop) {
            $groups = $db->executeS(
                'SELECT id_group FROM ' . _DB_PREFIX_ . 'group_shop WHERE id_shop = ' . (int) $idShop
            );
            foreach ($groups ?: [] as $group) {
                $exists = (int) $db->getValue(
                    'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'module_group
                     WHERE id_module = ' . $idModule . '
                       AND id_shop = ' . (int) $idShop . '
                       AND id_group = ' . (int) $group['id_group']
                );
                if (!$exists) {
                    $db->insert('module_group', [
                        'id_module' => $idModule,
                        'id_shop' => (int) $idShop,
                        'id_group' => (int) $group['id_group'],
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * Send the "Configure" button to the Symfony controller instead of rendering
     * a legacy configuration page.
     */
    public function getContent(): void
    {
        Tools::redirectAdmin(
            $this->get('router')->generate('quince_appearance')
        );
    }
}
