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
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Wyszukiwanie ikon dla selecta w ustawieniach.
 *
 * Zestaw ma ponad dwa tysiące pozycji i 1,2 MB kodu SVG, więc nie jest wpisywany
 * w stronę panelu. Osobne zapytanie zwraca tylko to, co pasuje do wpisanej frazy.
 */
class IconController extends FrameworkBundleAdminController
{
    public function searchAction(Request $request): JsonResponse
    {
        $icons = IconLibrary::search((string) $request->query->get('q', ''));

        return new JsonResponse([
            'icons' => $icons,
            'total' => IconLibrary::count(),
        ]);
    }
}
