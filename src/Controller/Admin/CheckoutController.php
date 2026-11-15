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

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Quince\Engine\Settings\CheckoutSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wybór wariantu kasy: krokowa czy jednostronicowa.
 */
class CheckoutController extends QuinceController
{
    public function __construct(
        private readonly CheckoutSettings $settings,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/checkout.html.twig', [
            'mode' => $this->settings->mode(),
            'orderConfirmationHook' => $this->settings->orderConfirmationHook(),
            'viesLookup' => $this->settings->viesLookup(),
            'vatCheck' => $this->settings->vatCheck(),
            'layoutTitle' => 'Checkout',
            'quinceRoute' => 'quince_checkout',
        ]);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to change this.')]
    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_checkout_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_checkout');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_checkout');
    }
}
