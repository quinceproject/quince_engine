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

use Quince\Engine\Feature\ModuleCatalogue;
use Symfony\Component\HttpFoundation\Response;

/**
 * Przegląd naszych modułów: co jest wgrane, co zainstalowane, co działa.
 *
 * Osobno od Module Managera PrestaShopa, bo tamten pokazuje wszystkie kilkadziesiąt
 * modułów sklepu i nie wie, że nasze tworzą jedną całość. Tutaj sprzedawca widzi
 * także to, czego jeszcze nie ma — bo inaczej nie wie, że może mieć więcej.
 */
class ModulesController extends QuinceController
{
    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/modules.html.twig', [
            'modules' => (new ModuleCatalogue())->all(),
            'layoutTitle' => 'Quince modules',
            'quinceRoute' => 'quince_modules',
        ]);
    }
}
