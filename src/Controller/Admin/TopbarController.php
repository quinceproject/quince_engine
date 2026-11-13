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
use Quince\Engine\Settings\TopbarSettings;
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia topbara: treści, widoczność i pasek komunikatu.
 *
 * Kolorów tu nie ma. Wylicza je silnik z wyboru „pas jasny / ciemny / w kolorze
 * marki" w ustawieniach ogólnych.
 */
class TopbarController extends QuinceController
{
    public function __construct(
        private readonly TopbarSettings $settings,
        private readonly AppearanceSettings $appearance
    ) {
    }

    public function indexAction(): Response
    {
        $languages = $this->getLanguages();

        $settings = $this->settings->all();

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/topbar.html.twig', [
            'settings' => $settings,
            'translations' => $this->settings->translations(),
            'languages' => $languages,
            // Wybrana ikona rysuje się od razu, bez czekania na zapytanie:
            // select dopytuje serwer dopiero po otwarciu listy.
            'iconSvg' => [
                'PHONE_ICON' => IconLibrary::svg($settings['PHONE_ICON']),
                'EMAIL_ICON' => IconLibrary::svg($settings['EMAIL_ICON']),
                'HOURS_ICON' => IconLibrary::svg($settings['HOURS_ICON']),
            ],
            // Tło pasa wybiera się ze skal palety, więc ekran musi je znać. Wie też,
            // czy paleta jest decyzją sklepu, czy zgadywanką silnika. Bez zapisanych
            // ustawień podstawowych nie ma z czego wybierać.
            'palette' => $this->appearance->palette(),
            'prepared' => $this->appearance->isPrepared(),
            'layoutTitle' => 'Top bar',
            'quinceRoute' => 'quince_topbar',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_topbar_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_topbar');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_topbar');
    }

    /**
     * Języki sklepu do zakładek pól wielojęzycznych.
     *
     * @return array<int,array{id_lang:int,name:string,iso_code:string}>
     */
    private function getLanguages(): array
    {
        $out = [];

        foreach (\Language::getLanguages(true) as $language) {
            $out[] = [
                'id_lang' => (int) $language['id_lang'],
                'name' => (string) $language['name'],
                'iso_code' => (string) $language['iso_code'],
                // Pola wielojęzyczne otwierają się na języku sklepu, a nie na
                // pierwszym z listy. Inaczej sprzedawca pisze swój tekst
                // w rubryce obcego języka.
                'default' => (int) $language['id_lang'] === (int) \Configuration::get('PS_LANG_DEFAULT'),
            ];
        }

        return $out;
    }
}
