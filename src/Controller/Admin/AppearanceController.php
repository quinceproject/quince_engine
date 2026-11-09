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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia globalne wyglądu — kolory, typografia, kształt i siatka.
 */
class AppearanceController extends QuinceController
{
    /**
     * Grupy wynikowej palety w kolejności wyświetlania: klucz tłumaczenia => role.
     *
     * Pokazujemy warstwę semantyczną, nie stopnie skali — administratorowi nic nie mówi
     * `brand-9`, mówi mu „wypełnienie przycisku". Stopnie zostają pod spodem, do wglądu
     * w trybie ręcznym.
     */
    private const COLOUR_GROUPS = [
        'Buttons and links' => [
            'fill-primary', 'on-fill-primary',
            'fill-primary-hover', 'on-fill-primary-hover',
            'fill-primary-active', 'on-fill-primary-active',
            'surface-primary', 'surface-primary-active', 'text-primary',
            'link', 'link-hover', 'focus-ring',
        ],
        'Secondary actions' => [
            'fill-secondary', 'on-fill-secondary',
            'fill-secondary-hover', 'on-fill-secondary-hover',
            'fill-secondary-active', 'on-fill-secondary-active',
            'border-secondary', 'text-secondary',
        ],
        'Prices and promotions' => [
            'fill-promo', 'on-fill-promo',
        ],
        // Tła strony i karty mają własną kontrolkę (paski stopni wyżej) i są
        // zapisywane jako stopień skali. Gdyby dały się tu przypiąć drugi raz
        // jako kod barwy, przypięcie wygrywałoby z wyborem stopnia i ekran
        // pokazywałby jeden odcień, a sklep drugi.
        'Backgrounds and text' => [
            'surface-sunken', 'surface-hover', 'surface-active',
            'text-strong', 'text-body', 'text-muted', 'text-disabled',
            'border-subtle', 'border', 'border-field', 'border-hover',
        ],
        'Messages' => [
            'fill-success', 'surface-success', 'border-success', 'text-success', 'on-fill-success',
            'fill-warning', 'surface-warning', 'border-warning', 'text-warning', 'on-fill-warning',
            'fill-danger', 'surface-danger', 'border-danger', 'text-danger', 'on-fill-danger',
            'fill-info', 'surface-info', 'border-info', 'text-info', 'on-fill-info',
        ],
        // Pasy pokazujemy w podglądzie wyżej; tu tylko trzy role na pas, bo pozostałe
        // (tekst wygaszony, hover odnośnika, obramowanie) wynikają z nich wprost.
        'Page bands' => [
            'band-topbar-bg', 'band-topbar-fg', 'band-topbar-link',
            'band-header-bg', 'band-header-fg', 'band-header-link',
            'band-nav-bg', 'band-nav-fg', 'band-nav-link',
            'band-footer-bg', 'band-footer-fg', 'band-footer-link',
        ],
    ];

    public function __construct(private readonly AppearanceSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/appearance.html.twig', [
            'settings' => $this->settings->all(),
            'palette' => $this->settings->palette(),
            'computed' => $this->settings->computed(),
            // Rzutowanie na obiekt jest konieczne: panel indeksuje nadpisania nazwą roli,
            // a pusta tablica PHP zapisuje się jako `[]`. JavaScript dostałby wtedy tablicę,
            // pierwszy przypięty kolor wpadłby do niej jako właściwość spoza indeksu
            // i zniknął przy JSON.stringify — nie dałoby się zapisać żadnego nadpisania.
            'overrides' => (object) $this->settings->overrides(),
            'diagnostics' => $this->settings->diagnostics(),
            'colourGroups' => self::COLOUR_GROUPS,
            'layoutTitle' => 'Global settings',
            'quinceRoute' => 'quince_appearance',
        ]);
    }

    /**
     * Zwraca paletę wyliczoną z przesłanych wartości, bez zapisu.
     *
     * Dzięki temu przycisk „Generate palette" pokazuje dokładnie to, co zobaczy sklep —
     * ta sama klasa liczy kolory tutaj i przy zapisie.
     */
    public function previewAction(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('quince_appearance_preview', $request->request->get('_csrf_token'))) {
            return new JsonResponse(['error' => 'Invalid security token.'], 403);
        }

        $input = array_intersect_key(
            $request->request->all(),
            $this->settings->defaults()
        );

        return new JsonResponse([
            'palette'     => $this->settings->previewPalette($input),
            'diagnostics' => $this->settings->previewDiagnostics($input),
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_appearance_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_appearance');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_appearance');
    }
}
