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
use Quince\Engine\Settings\AuthSettings;
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strony logowania i rejestracji: korzyści z konta obok formularza.
 */
class AuthController extends QuinceController
{
    public function __construct(
        private readonly AuthSettings $settings,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/auth.html.twig', [
            'benefits' => [
                'login' => $this->withSvg($this->settings->rows('login')),
                'register' => $this->withSvg($this->settings->rows('register')),
            ],
            'languages' => $this->languages(),
            'layoutTitle' => 'Sign-in and registration',
            'quinceRoute' => 'quince_auth',
        ]);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to change this.')]
    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_auth_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_auth');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_auth');
    }

    /**
     * Wiersze z kodem SVG wybranej ikony, żeby przycisk wyboru rysował ją od
     * razu, a nie dopiero po otwarciu listy.
     *
     * @param array<int,array<string,mixed>> $rows
     *
     * @return array<int,array<string,mixed>>
     */
    private function withSvg(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['iconSvg'] = $row['icon'] !== '' ? IconLibrary::svg((string) $row['icon']) : '';
        }

        return $rows;
    }
}
