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
use Quince\Engine\Settings\FooterSettings;
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia stopki: dane firmy, kolumny, ikony społecznościowe, pas dolny.
 *
 * Kolumna ma nazwę i listę hooków — treść przynoszą moduły. Ekran nie pyta więc
 * „co wpisać w kolumnie", tylko „co ma w niej stanąć".
 */
class FooterController extends QuinceController
{
    public function __construct(
        private readonly FooterSettings $settings,
        private readonly AppearanceSettings $appearance
    ) {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/footer.html.twig', [
            'settings' => $this->settings->all(),
            'translations' => $this->settings->translations(),
            'columns' => $this->settings->columns(),
            'socials' => $this->socialRows(),
            'iconSvg' => $this->iconSvg(),
            'payments' => $this->settings->payments(),
            'barLinks' => $this->settings->barLinks(),
            'stripHooks' => $this->settings->stripHooks(),
            'palette' => $this->appearance->palette(),
            'prepared' => $this->appearance->isPrepared(),
            'languages' => $this->languages(),
            'iconsRoute' => 'quince_icons_search',
            // Nazwa sklepu jako podpowiedź w polach, które zwykle ją powtarzają.
            'shopName' => (string) \Configuration::get('PS_SHOP_NAME'),
            'layoutTitle' => 'Footer',
            'quinceRoute' => 'quince_footer',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_footer_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_footer');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_footer');
    }

    /**
     * Rysunki ikon wybranych przy wierszach kontaktu.
     *
     * @return array<string,string>
     */
    private function iconSvg(): array
    {
        $ustawienia = $this->settings->all();
        $out = [];

        foreach (['ADDRESS_ICON', 'PHONE_ICON', 'EMAIL_ICON', 'HOURS_ICON'] as $key) {
            $out[$key] = IconLibrary::svg($ustawienia[$key]);
        }

        return $out;
    }

    /**
     * Ikony społecznościowe z kodem SVG wybranej ikony — przycisk wyboru ma ją
     * rysować od razu, a nie dopiero po otwarciu listy.
     *
     * @return array<int,array<string,mixed>>
     */
    private function socialRows(): array
    {
        $out = [];

        foreach ($this->settings->savedSocials() as $wpis) {
            $wpis['iconSvg'] = $wpis['icon'] !== '' ? IconLibrary::svg($wpis['icon']) : '';
            $out[] = $wpis;
        }

        return $out;
    }
}
