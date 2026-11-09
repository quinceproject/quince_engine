<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Controller\Admin;

use Db;
use Quince\Engine\Settings\HomepageSettings;
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strona główna: lista sekcji, z których sklep ją składa.
 *
 * Ekran różni się od pozostałych ustawień układu tym, że nie pokazuje stałych
 * stref do włączenia, tylko listę, do której sklep dokłada własne pozycje.
 * Dlatego cała zawartość jedzie jednym polem ukrytym jako JSON, a nie polami
 * formularza: liczba sekcji i kolumn zmienia się przy każdym przestawieniu.
 *
 * Projekt ekranu: `quince/widoki/panel-strona-glowna.html`.
 */
class HomepageController extends QuinceController
{
    public function __construct(
        private readonly HomepageSettings $settings,
    ) {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/homepage.html.twig', [
            'layoutTitle' => 'Home page',

            'sections' => $this->withIcons($this->settings->sections()),

            // Wybierak ikon dopytuje o pasujące nazwy pod tym adresem, tak samo
            // jak na pozostałych ekranach panelu.
            'iconsRoute' => 'quince_icons_search',

            // Ile zajawek stoi w rzędzie na listingach. Ekran liczy z tego
            // szerokości kolumn, więc musi dostać tę samą liczbę co listing.
            'gridColumns' => HomepageSettings::gridColumns(),

            // Słowniki do kontrolek. Ekran nie zna wartości na pamięć — bierze
            // je stąd, więc dołożenie skóry albo źródła produktów pokazuje się
            // w panelu bez ruszania szablonu ani skryptu.
            'modes' => HomepageSettings::MODES,
            'widths' => HomepageSettings::WIDTHS,
            'skins' => HomepageSettings::SKINS,
            'columnTypes' => HomepageSettings::COLUMN_TYPES,
            'sources' => $this->sources(),
            'displays' => HomepageSettings::DISPLAYS,
            'tileImages' => HomepageSettings::TILE_IMAGES,
            'iconColours' => HomepageSettings::ICON_COLOURS,
            'tileDirections' => HomepageSettings::TILE_DIRECTIONS,
            'productImages' => HomepageSettings::PRODUCT_IMAGES,
            'percentSteps' => HomepageSettings::PERCENT_STEPS,
            'maxColumns' => HomepageSettings::MAX_COLUMNS,
            'extraStyles' => HomepageSettings::EXTRA_STYLES,
            'maxExtras' => HomepageSettings::MAX_EXTRAS,
            'minPercent' => HomepageSettings::MIN_PERCENT,

            'categories' => $this->categories(),
            'languages' => $this->languages(),

            'shopsWithOwnSettings' => $this->settings->shopsWithOwnSettings(),
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_homepage_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_homepage');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_homepage');
    }

    /**
     * Sekcje z kodem SVG zapisanej ikony.
     *
     * Wybierak pokazuje ikonę od razu po otwarciu ekranu, a nie dopiero po
     * dopytaniu serwera — inaczej pole wyglądałoby na puste przy każdym wejściu.
     *
     * @param array<int,array<string,mixed>> $sekcje
     *
     * @return array<int,array<string,mixed>>
     */
    private function withIcons(array $sekcje): array
    {
        foreach ($sekcje as $i => $sekcja) {
            $sekcje[$i]['icon_svg'] = ($sekcja['icon'] ?? '') !== ''
                ? IconLibrary::svg((string) $sekcja['icon'])
                : '';
        }

        return $sekcje;
    }

    /**
     * Źródła produktów dostępne w dropdownie.
     *
     * „Najczęściej szukane" znika z listy, gdy `quince_search_score` nie jest
     * zainstalowany — wybranie trybu, który i tak nic by nie pokazał, tylko
     * myli sprzedawcę. Sekcja zapisana wcześniej, zanim moduł odinstalowano,
     * dalej działa (gaśnie po cichu — patrz `HomeProducts::najczesciejSzukane()`),
     * po prostu nie da się jej wybrać od nowa z tego ekranu.
     *
     * @return array<string,string>
     */
    private function sources(): array
    {
        $zrodla = HomepageSettings::SOURCES;

        if (!\Module::getInstanceByName('quince_search_score')) {
            unset($zrodla['searched']);
        }

        return $zrodla;
    }

    /**
     * Kategorie sklepu do wyboru na kaflach i jako źródło produktów.
     *
     * Kolejność jak w katalogu, a zagnieżdżenie widać po wcięciu w nazwie:
     * lista rozwijana nie umie pokazać drzewa, a bez wcięcia dwie kategorie
     * o tej samej nazwie w różnych gałęziach są nie do rozróżnienia.
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function categories(): array
    {
        $jezyk = (int) $this->getContext()->language->id;
        $korzen = (int) \Configuration::get('PS_HOME_CATEGORY');

        $wiersze = Db::getInstance()->executeS(
            'SELECT c.id_category, c.level_depth, cl.name
               FROM `' . _DB_PREFIX_ . 'category` c
               JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                 ON cl.id_category = c.id_category AND cl.id_lang = ' . $jezyk . '
              WHERE c.active = 1 AND c.id_category != ' . $korzen . '
                AND c.nleft > (SELECT nleft FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . $korzen . ')
                AND c.nright < (SELECT nright FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . $korzen . ')
              ORDER BY c.nleft ASC'
        ) ?: [];

        $kategorie = [];
        $najplycej = null;

        foreach ($wiersze as $wiersz) {
            $glebokosc = (int) $wiersz['level_depth'];
            $najplycej = $najplycej ?? $glebokosc;

            $kategorie[] = [
                'id' => (int) $wiersz['id_category'],
                'name' => str_repeat('— ', max(0, $glebokosc - $najplycej)) . (string) $wiersz['name'],
            ];
        }

        return $kategorie;
    }
}
