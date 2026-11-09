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

use Db;
use Quince\Engine\Settings\AppearanceSettings;
use Quince\Engine\Settings\NavigationSettings;
use Quince\Engine\Ui\IconLibrary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia paska nawigacji: kategorie i odnośniki własne.
 */
class NavigationController extends QuinceController
{
    public function __construct(
        private readonly NavigationSettings $settings,
        private readonly AppearanceSettings $appearance
    )
    {
    }

    public function indexAction(): Response
    {
        $idLang = (int) \Context::getContext()->language->id;

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/navigation.html.twig', [
            'settings' => $this->settings->all(),
            'translations' => $this->settings->translations(),
            'triggerIcon' => $this->settings->triggerIconSvg(),
            'palette' => $this->appearance->palette(),
            'prepared' => $this->appearance->isPrepared(),
            'providers' => $this->settings->menuProviders(),
            'providerMissing' => $this->settings->menuProviderMissing(),
            'links' => $this->linkRows(),
            'categories' => $this->categoryRows($idLang),
            'languages' => $this->languages(),
            'layoutTitle' => 'Main menu',
            'quinceRoute' => 'quince_navigation',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_navigation_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_navigation');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_navigation');
    }

    /**
     * Odnośniki własne z kodem SVG wybranej ikony — przycisk wyboru ma ją rysować
     * od razu, a nie dopiero po otwarciu listy.
     *
     * @return array<int,array<string,mixed>>
     */
    private function linkRows(): array
    {
        $out = [];

        foreach ($this->settings->links() as $link) {
            $link['iconSvg'] = $link['icon'] !== '' ? IconLibrary::svg($link['icon']) : '';
            $out[] = $link;
        }

        return $out;
    }

    /**
     * Kategorie główne sklepu w kolejności ustawionej w panelu.
     *
     * Najpierw te wybrane, w zapisanej kolejności, potem reszta katalogu — dzięki
     * temu przeciąganie wierszy układa menu, a kategorie dołożone w katalogu
     * czekają na końcu, zamiast wskakiwać w środek.
     *
     * @return array<int,array{id:int,name:string,icon:string,svg:string,visible:bool}>
     */
    private function categoryRows(int $idLang): array
    {
        $korzen = (int) \Configuration::get('PS_HOME_CATEGORY');

        $wiersze = Db::getInstance()->executeS(
            'SELECT c.id_category, cl.name FROM `' . _DB_PREFIX_ . 'category` c
             JOIN `' . _DB_PREFIX_ . 'category_lang` cl
               ON cl.id_category = c.id_category AND cl.id_lang = ' . $idLang . '
             WHERE c.active = 1 AND c.id_parent = ' . $korzen . '
             ORDER BY c.position ASC'
        ) ?: [];

        $nazwy = [];

        foreach ($wiersze as $wiersz) {
            $nazwy[(int) $wiersz['id_category']] = (string) $wiersz['name'];
        }

        $out = [];
        $uzyte = [];

        // Wybrane, w kolejności zapisanej w ustawieniach.
        foreach ($this->settings->categories() as $wybrana) {
            $id = (int) $wybrana['id'];

            if (!isset($nazwy[$id])) {
                continue;
            }

            $ikona = (string) $wybrana['icon'];

            $out[] = [
                'id' => $id,
                'name' => $nazwy[$id],
                'icon' => $ikona,
                'svg' => $ikona !== '' ? IconLibrary::svg($ikona) : '',
                'visible' => true,
            ];

            $uzyte[$id] = true;
        }

        // Reszta katalogu, niezaznaczona.
        foreach ($nazwy as $id => $nazwa) {
            if (isset($uzyte[$id])) {
                continue;
            }

            $out[] = [
                'id' => $id,
                'name' => $nazwa,
                'icon' => '',
                'svg' => '',
                'visible' => false,
            ];
        }

        return $out;
    }
}
