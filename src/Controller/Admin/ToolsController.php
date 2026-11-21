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

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Quince\Engine\Catalog\ImageSetup;
use Quince\Engine\Ui\IconLibrary;
use Quince\Engine\Ui\IconPackManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * System → Tools: miejsce na czynności konserwacyjne silnika, które nie
 * pasują do żadnego ekranu ustawień. Dziś tylko aktualizacja paczki ikon.
 */
class ToolsController extends QuinceController
{
    public function __construct(private readonly IconPackManager $icons)
    {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/tools.html.twig', [
            'iconVersion' => $this->icons->version(),
            'iconUpdatedAt' => $this->icons->updatedAt(),
            'iconCount' => IconLibrary::count(),
            'images' => (new ImageSetup())->status(),
            'imagesBasis' => (new ImageSetup())->basis(),
            'imagesLink' => $this->generateUrl('admin_image_settings_index'),
            'layoutTitle' => 'Tools',
        ]);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to change this.')]
    public function imagesApplyAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_tools_images_apply', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_tools');
        }

        (new ImageSetup())->apply();

        $this->addFlash('success', $this->trans(
            'Image settings applied. Regenerate the thumbnails in Design → Image settings so that the photos already in the shop get the new sizes.',
            'Modules.Quinceengine.Admin'
        ));

        return $this->redirectToRoute('quince_tools');
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to change this.')]
    public function iconsUpdateAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_tools_icons_update', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_tools');
        }

        $plik = $request->files->get('paczka');

        if ($plik === null || !$plik->isValid()) {
            $this->addFlash('warning', $this->trans('No file was sent.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_tools');
        }

        try {
            $wersja = $this->icons->importFrom((string) $plik->getPathname());

            $this->addFlash('success', $this->trans(
                'Icon pack updated to version %version%.',
                'Modules.Quinceengine.Admin',
                ['%version%' => $wersja]
            ));

            // Pominięte pliki niosły kod: właściciel sklepu ma wiedzieć, że ich
            // ikon brakuje.
            if ($this->icons->skipped() > 0) {
                $this->addFlash('warning', $this->trans(
                    '%count% icon files were skipped because they contain code. They will not show in the shop.',
                    'Modules.Quinceengine.Admin',
                    ['%count%' => $this->icons->skipped()]
                ));
            }
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $this->trans($e->getMessage(), 'Modules.Quinceengine.Admin'));
        }

        return $this->redirectToRoute('quince_tools');
    }
}
