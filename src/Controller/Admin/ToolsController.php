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

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/tools.html.twig', [
            'iconVersion' => $this->icons->version(),
            'iconUpdatedAt' => $this->icons->updatedAt(),
            'iconCount' => IconLibrary::count(),
            'layoutTitle' => 'Tools',
        ]);
    }

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
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $this->trans($e->getMessage(), 'Modules.Quinceengine.Admin'));
        }

        return $this->redirectToRoute('quince_tools');
    }
}
