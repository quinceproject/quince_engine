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
use Quince\Engine\Settings\HeaderSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia nagłówka: logo, wyszukiwanie, akcje klienta.
 *
 * Tło pasa wybiera się tutaj, ze skal palety, osobno dla nagłówka stojącego
 * na swoim miejscu i dla przyklejonego. Resztę ról liczy silnik z tego tła.
 */
class HeaderController extends QuinceController
{
    public function __construct(
        private readonly HeaderSettings $settings,
        private readonly AppearanceSettings $appearance
    ) {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/header.html.twig', [
            'settings' => $this->settings->all(),
            'translations' => $this->settings->translations(),
            'providers' => $this->settings->searchProviders(),
            'iconSvg' => $this->settings->iconSvg(),
            'providerMissing' => $this->settings->searchProviderMissing(),
            'wishlistAvailable' => $this->settings->wishlistAvailable(),
            'moduleActionsAvailable' => $this->settings->moduleActionsAvailable(),
            'languages' => $this->languages(),
            'palette' => $this->appearance->palette(),
            'prepared' => $this->appearance->isPrepared(),
            'layoutTitle' => 'Header',
            'quinceRoute' => 'quince_header',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_header_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_header');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_header');
    }

}
