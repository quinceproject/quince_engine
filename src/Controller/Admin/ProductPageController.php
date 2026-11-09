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
use Quince\Engine\Settings\ProductPageSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Układ karty produktu: sekcje, karty, obszary i elementy w nich.
 *
 * Ekran odwzorowuje strukturę strony — sekcje idą od lewej do prawej krawędzi,
 * w środku stoją karty, a w kartach obszary z wierszami. Odpowiada to makiecie
 * `quince/makiety/panel-karta-produktu.html`.
 */
class ProductPageController extends QuinceController
{
    public function __construct(
        private readonly ProductPageSettings $settings,
        private readonly AppearanceSettings $appearance,
    ) {
    }

    public function indexAction(): Response
    {
        $layout = $this->settings->layout();

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/product.html.twig', [
            'layout' => $layout,
            'split' => $this->settings->split($layout),
            'splits' => $this->settings->splitsFor($layout),
            'areas' => $this->settings->zones($layout),
            'catalogue' => $this->settings->catalogue(),
            'optionLabels' => $this->optionLabels(),
            'phone' => $this->settings->phone(),

            // Sklepy z własnym układem, gdy panel stoi na wszystkich sklepach
            // albo na grupie: ekran pokazuje wtedy ustawienie wspólne, a te
            // sklepy wyglądają inaczej.
            'shopsWithOwnSettings' => $this->settings->shopsWithOwnSettings(),

            // Zapisany wygląd powierzchni. Bez tego ekran rysował próbnik
            // z wartością wpisaną na sztywno i każdy zapis nadpisywał sklepowi
            // jego własne ustawienia tłem pierwszego odcienia z palety.
            'surfaces' => $this->settings->surfaces(),
            'palette' => $this->appearance->palette(),
            'layoutTitle' => 'Product page',
            'quinceRoute' => 'quince_product',
        ]);
    }

    /**
     * Nazwy wszystkich włączników szczegółów, jakie występują w katalogu.
     *
     * Szablon dostaje gotową mapę zamiast wołać silnik dla każdego klucza z osobna.
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
        if (!$this->isCsrfTokenValid('quince_product_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_product');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_product');
    }
}
