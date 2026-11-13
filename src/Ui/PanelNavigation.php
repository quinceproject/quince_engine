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
 * Powstaje w PHP, a nie w szablonie, bo pozycje modułów zależą od tego, co jest
 * zainstalowane. Z tej klasy bierze sidebar także strona ustawień każdego
 * modułu Quince.
 *
 * Pozycja bez trasy jest pokazywana wyszarzona.
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
        // Grupa bez pozycji wypada razem z nagłówkiem.
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
                // Tylko zainstalowane moduły. Niezainstalowane widać na stronie
                // „Quince modules".
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
