<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Ui;

use Quince\Engine\Feature\ModuleCatalogue;
use Symfony\Component\Routing\RouterInterface;

/**
 * Nawigacja panelu Quince.
 *
 * Powstaje w PHP, a nie w szablonie, z dwóch powodów. Po pierwsze pozycje modułów
 * zależą od tego, co jest zainstalowane — a tego szablon sam nie sprawdzi.
 * Po drugie sięgają po nią także nasze moduły: strona ustawień modułu ma mieć
 * ten sam sidebar co silnik, więc bierze go stąd, zamiast odtwarzać u siebie.
 *
 * Pozycje bez trasy są zaplanowane, ale jeszcze niezbudowane — pokazujemy je
 * wyszarzone, żeby od razu było widać docelowy kształt panelu zamiast jednej
 * samotnej pozycji.
 */
final class PanelNavigation
{
    private ModuleCatalogue $catalogue;

    public function __construct(?ModuleCatalogue $catalogue = null, ?RouterInterface $router = null)
    {
        $this->catalogue = $catalogue ?? new ModuleCatalogue(null, $router);
    }

    /**
     * @return array<int,array{label:string,items:array<int,array{label:string,icon:string,route?:string}>}>
     */
    public function groups(): array
    {
        // Grupa bez pozycji nie ma czego pokazać. Sam nagłówek „Modules" nad pustką
        // wygląda jak coś, co się nie doczytało.
        return array_values(array_filter($this->allGroups(), static fn (array $g): bool => $g['items'] !== []));
    }

    /**
     * @return array<int,array{label:string,items:array<int,array{label:string,icon:string,route?:string}>}>
     */
    private function allGroups(): array
    {
        return [
            [
                'label' => 'Design',
                'items' => [
                    ['label' => 'Global settings', 'icon' => 'settings', 'route' => 'quince_appearance'],
                ],
            ],
            [
                'label' => 'Sections',
                'items' => [
                    ['label' => 'Top bar', 'icon' => 'layout', 'route' => 'quince_topbar'],
                    ['label' => 'Header', 'icon' => 'layout', 'route' => 'quince_header'],
                    ['label' => 'Main menu', 'icon' => 'link', 'route' => 'quince_navigation'],
                    ['label' => 'Footer', 'icon' => 'layout', 'route' => 'quince_footer'],
                ],
            ],
            [
                'label' => 'Pages',
                'items' => [
                    ['label' => 'Home page', 'icon' => 'box', 'route' => 'quince_homepage'],
                    ['label' => 'Product page', 'icon' => 'puzzle', 'route' => 'quince_product'],
                    ['label' => 'Product card', 'icon' => 'box', 'route' => 'quince_card'],
                    ['label' => 'Product lists', 'icon' => 'folder', 'route' => 'quince_listing'],
                    ['label' => 'Cart', 'icon' => 'box', 'route' => 'quince_cart'],
                    ['label' => 'Checkout', 'icon' => 'box', 'route' => 'quince_checkout'],
                ],
            ],
            [
                // Ustawienia modułów, które sklep faktycznie ma u siebie. Moduł
                // niezainstalowany nie zostawia tu pustej pozycji — jest za to
                // widoczny na stronie zarządzania modułami razem z opisem.
                'label' => 'Modules',
                'items' => $this->catalogue->navItems(),
            ],
            [
                'label' => 'System',
                'items' => [
                    ['label' => 'Quince modules', 'icon' => 'puzzle', 'route' => 'quince_modules'],
                    ['label' => 'Brand & Schema', 'icon' => 'shield', 'route' => 'quince_brand_schema'],
                    ['label' => 'Tools', 'icon' => 'upload', 'route' => 'quince_tools'],
                ],
            ],
        ];
    }
}
