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
use Quince\Engine\Ui\SupportLinks;
use Symfony\Component\HttpFoundation\Response;

/**
 * System → Support: wsparcie projektu na Ko-fi i zgłoszenia do repozytoriów.
 *
 * Ekran dla kogoś, kto już ma Quince w sklepie: zachęca do zgłaszania błędów,
 * propozycji i uwag tam, gdzie trafią do właściwego modułu. Moduły działające
 * w panelu silnika (slider, bloki, blog i reszta) nie mają własnej zakładki,
 * więc lista zgłoszeń obejmuje wszystkie zainstalowane moduły Quince.
 */
class SupportController extends QuinceController
{
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/support.html.twig', [
            'kofi' => SupportLinks::KOFI,
            'project' => SupportLinks::PROJECT,
            // Silnik i motyw idą osobno, na początku listy: to dwie paczki,
            // które ma każdy sklep na Quince.
            'core' => [
                ['label' => 'Quince Engine', 'url' => SupportLinks::issues('quince_engine')],
                ['label' => 'Quince theme', 'url' => SupportLinks::issues('quince')],
            ],
            'modules' => SupportLinks::installedModules(),
            'layoutTitle' => 'Support',
        ]);
    }
}
