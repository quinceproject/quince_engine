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

use Quince\Engine\Settings\ProductCardSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zajawka produktu: z czego się składa i ile pokazuje.
 *
 * Osobny ekran, bo zajawka stoi nie tylko na listingu: także w wynikach
 * wyszukiwania i w karuzelach na stronie głównej oraz na karcie produktu.
 * Ustawienia schowane w listingu nie obsłużyłyby tamtych miejsc.
 */
class ProductCardController extends QuinceController
{
    public function __construct(private readonly ProductCardSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        $ustawienia = [];

        foreach (array_keys($this->settings->variants()) as $rodzaj) {
            $ustawienia[$rodzaj] = $this->settings->forVariant($rodzaj);
        }

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/card.html.twig', [
            'variants' => $this->settings->variants(),
            'catalogue' => $this->settings->catalogue(),
            'values' => $ustawienia,
            'layoutTitle' => 'Product card',
            'quinceRoute' => 'quince_card',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_card_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_card');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_card');
    }
}
