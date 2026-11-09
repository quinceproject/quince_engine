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

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Quince\Engine\Ui\PanelNavigation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wspólna podstawa stron panelu Quince.
 *
 * Rozszerzają ją także kontrolery naszych modułów: dzięki temu strona ustawień
 * modułu dostaje ten sam sidebar, pasek zapisu i wygląd co silnik, bez kopiowania
 * czegokolwiek. Moduł podaje własny szablon i własne dane, resztę bierze stąd.
 *
 * Szablon modułu rozszerza layout silnika:
 *
 *     {% extends '@Modules/quince_engine/views/templates/admin/layout.html.twig' %}
 */
abstract class QuinceController extends FrameworkBundleAdminController
{
    /**
     * @param array<string,mixed> $parameters
     */
    protected function renderPanel(string $template, array $parameters): Response
    {
        // Nawigacja dokłada się tutaj raz, zamiast w każdym kontrolerze osobno.
        // Pozycje modułów zależą od tego, co sklep ma zainstalowane, więc muszą
        // powstać przy każdym żądaniu — zapamiętana lista szybko kłamie.
        // PrestaShop wstawia layoutTitle do nagłówka strony bez tłumaczenia,
        // więc robimy to tutaj — inaczej sklep ma polski sidebar i angielski
        // tytuł nad nim.
        if (isset($parameters['layoutTitle'])) {
            $parameters['layoutTitle'] = $this->trans(
                (string) $parameters['layoutTitle'],
                // Ekran modułu podaje własną domenę: jego napisy leżą w jego
                // pliku tłumaczeń, nie w silniku.
                (string) ($parameters['layoutTitleDomain'] ?? 'Modules.Quinceengine.Admin')
            );
        }

        // Nazwy zakładek tłumaczy kontroler, bo to on zna domenę modułu. Layout
        // je wypisuje — jedna zasada zamiast domeny przekazywanej do szablonu.
        if (!empty($parameters['quinceTabs'])) {
            $domena = (string) ($parameters['quinceTabsDomain'] ?? 'Modules.Quinceengine.Admin');

            $parameters['quinceTabs'] = array_map(
                fn (array $tab): array => ['label' => $this->trans((string) $tab['label'], $domena)] + $tab,
                $parameters['quinceTabs']
            );
        }

        // Router idzie z kontrolera, bo tylko tu jest pewny: menu sprawdza nim,
        // czy trasa zgłoszona przez moduł w ogóle istnieje, zamiast wywracać
        // cały panel na module z literówką albo bez kontrolera.
        return $this->render($template, $parameters + [
            'nav' => (new PanelNavigation(null, $this->container->get('router')))->groups(),
        ]);
    }

    /**
     * Języki sklepu do pól wielojęzycznych.
     *
     * @return array<int,array{id_lang:int,name:string,iso_code:string,default:bool}>
     */
    protected function languages(): array
    {
        $out = [];

        foreach (\Language::getLanguages(true) as $jezyk) {
            $out[] = [
                'id_lang' => (int) $jezyk['id_lang'],
                'name' => (string) $jezyk['name'],
                'iso_code' => (string) $jezyk['iso_code'],
                // Pola wielojęzyczne otwierają się na języku sklepu, nie na
                // pierwszym z listy.
                'default' => (int) $jezyk['id_lang'] === (int) \Configuration::get('PS_LANG_DEFAULT'),
            ];
        }

        return $out;
    }
}
