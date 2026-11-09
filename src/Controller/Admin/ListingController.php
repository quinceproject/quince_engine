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

use Quince\Engine\Settings\AppearanceSettings;
use Quince\Engine\Settings\ListingSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Układ listingu: strefy, elementy, umiejscowienie filtrów i miejsca
 * promocyjne w siatce.
 *
 * Jeden ekran na siedem kontrolerów PrestaShopa. Uzasadnienie i inwentarz
 * hooków: docs/25-strefy-i-elementy-listingu.md.
 */
class ListingController extends QuinceController
{
    public function __construct(
        private readonly ListingSettings $settings,
        private readonly AppearanceSettings $appearance,
    ) {
    }

    public function indexAction(): Response
    {
        $kolumny = (int) ($this->appearance->all()['GRID_COLUMNS'] ?? 4);

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/listing.html.twig', [
            'areas' => $this->settings->zones(),
            'catalogue' => $this->settings->catalogue(),
            'zoneCatalogue' => $this->settings->zoneCatalogue(),
            'optionLabels' => $this->optionLabels(),

            'filters' => $this->settings->filtersPlacement(),
            'filterPlacements' => ListingSettings::FILTER_PLACEMENTS,

            // Widok listy: które rodzaje zajawek sklep daje i który klient
            // widzi na wejściu.
            'pagination' => $this->settings->paginationMode(),
            'paginationModes' => ListingSettings::PAGINATION_MODES,
            'viewMode' => $this->settings->viewMode(),
            'viewModes' => ListingSettings::VIEW_MODES,
            'view' => $this->settings->defaultView(),
            'views' => ListingSettings::VIEWS,

            'placements' => $this->settings->placements(),
            'placementTypes' => ListingSettings::PLACEMENT_TYPES,
            'placementScopes' => ListingSettings::PLACEMENT_SCOPES,
            'placementViews' => ListingSettings::PLACEMENT_VIEWS,

            // Liczba kolumn i to, co z niej zostaje po oddaniu jednej filtrom.
            // Ekran mówi właścicielowi sklepu wprost, ile zajawek zobaczy
            // w rzędzie, zamiast kazać mu to policzyć.
            //
            // Wartość dla kolumny liczymy zawsze, także gdy filtry stoją dziś
            // gdzie indziej: komunikat ma odpowiadać na pytanie „co się stanie,
            // jeśli to wybiorę", a nie opisywać stan bieżący. Liczony ze stanu
            // dawał „4 zajawki zamiast 4".
            'gridColumns' => $kolumny,
            'listingColumns' => max(1, $kolumny - 1),
            'filterWidth' => $this->filterWidth($kolumny),

            'shopsWithOwnSettings' => $this->settings->shopsWithOwnSettings(),

            // Styl bloków: tło, ramka, cień i zaokrąglenie każdego z osobna,
            // tak samo jak na stronie produktu. Zapisany wygląd musi przyjść
            // z serwera, bo inaczej próbnik pokazuje pierwszy odcień palety
            // i każdy zapis nadpisuje sklepowi jego własne ustawienie.
            'surfaces' => $this->settings->surfaces(),
            'palette' => $this->appearance->palette(),

            'layoutTitle' => 'Product lists',
            'quinceRoute' => 'quince_listing',
        ]);
    }

    /**
     * Ile pikseli dostaną filtry stojące w kolumnie.
     *
     * Kolumna filtrów zabiera dokładnie jedną komórkę siatki, więc jej
     * szerokość wynika z szerokości strony i liczby kolumn:
     * (szerokość − wyściółka kontenera − odstępy) / liczba kolumn.
     */
    private function filterWidth(int $kolumny): int
    {
        $ustawienia = $this->appearance->all();

        $szerokosc = max(960, min(1920, (int) ($ustawienia['CONTAINER_WIDTH'] ?? 1200)));
        $odstep = 20;
        $wyscielka = 2 * $odstep;

        return (int) round(($szerokosc - $wyscielka - ($kolumny - 1) * $odstep) / max(1, $kolumny));
    }

    /**
     * Nazwy wszystkich włączników szczegółów, jakie występują w katalogu.
     *
     * @return array<string,string>
     */
    private function optionLabels(): array
    {
        $nazwy = [];

        foreach ($this->settings->catalogue() as $element) {
            foreach (array_keys($element['options'] ?? []) as $klucz) {
                $nazwy[$klucz] = $this->settings->optionLabel($klucz);
            }
        }

        return $nazwy;
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_listing_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_listing');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_listing');
    }
}
