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

use Module;
use Quince\Engine\Distribution\PackageInstaller;
use Quince\Engine\Distribution\ReleaseManifest;
use Quince\Engine\Feature\ModuleCatalogue;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Przegląd naszych modułów: co jest wgrane, co zainstalowane, co działa,
 * i instalacja albo aktualizacja z listy wydań Quince.
 *
 * Osobno od Module Managera PrestaShopa, bo tamten pokazuje wszystkie kilkadziesiąt
 * modułów sklepu i nie wie, że nasze tworzą jedną całość. Lista obejmuje także
 * moduły, których sklep jeszcze nie ma.
 */
class ModulesController extends QuinceController
{
    private const THEME = 'quince';

    public function __construct(
        private readonly ReleaseManifest $manifest,
        private readonly PackageInstaller $installer,
    ) {
    }

    public function indexAction(): Response
    {
        $releases = $this->manifest->modules();
        // Router pozwala sprawdzić, czy trasa zgłoszona przez moduł istnieje, zanim
        // szablon zbuduje z niej odnośnik.
        $catalogue = new ModuleCatalogue(null, $this->container->get('router'));

        $engine = Module::getInstanceByName('quince_engine');
        $themeVersion = $this->themeVersion();

        $modules = [];
        foreach (array_merge($catalogue->all(), $catalogue->others(array_keys($releases))) as $module) {
            $module['settings_url'] = $module['active'] ? $this->settingsUrl($module['name'], $module['route']) : null;
            $modules[] = $this->row($module['name'], $module['label'], 'module', $module['installed'], $module['active'],
                $module['version'], $releases[$module['name']] ?? null) + $module;
        }

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/modules.html.twig', [
            'engine' => $this->row('quince_engine', 'Quince Engine', 'module', true, true,
                $engine instanceof Module ? (string) $engine->version : null, $releases['quince_engine'] ?? null),
            'theme' => $this->row(self::THEME, 'Quince theme', 'theme', $themeVersion !== null, true,
                $themeVersion, $this->manifest->themes()[self::THEME] ?? null),
            'modules' => $modules,
            'manifestError' => $this->manifest->error(),
            'manifestFetchedAt' => $this->manifest->fetchedAt(),
            'layoutTitle' => 'Quince modules',
            'quinceRoute' => 'quince_modules',
        ]);
    }

    public function installAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_modules_install', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_modules');
        }

        $name = (string) $request->request->get('module', '');
        $kind = (string) $request->request->get('kind', 'module');

        try {
            if ($kind === 'theme') {
                if ($name !== self::THEME) {
                    throw new \RuntimeException('This theme is not on the release list.');
                }
                $isNew = $this->themeVersion() === null;
                $version = $this->installer->installTheme($name);
                if ($isNew) {
                    $this->addFlash('info', $this->trans('Switch the theme on in Design → Theme & Logo.', 'Modules.Quinceengine.Admin'));
                }
            } else {
                if (!preg_match('/^quince_[a-z_]+$/', $name)) {
                    throw new \RuntimeException('This module is not on the release list.');
                }
                $version = $this->installer->installModule($name);
            }

            $this->addFlash('success', $this->trans(
                '%name% %version% is installed.',
                'Modules.Quinceengine.Admin',
                ['%name%' => $name, '%version%' => $version]
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->trans($e->getMessage(), 'Modules.Quinceengine.Admin'));
        }

        return $this->redirectToRoute('quince_modules');
    }

    public function refreshAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_modules_refresh', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_modules');
        }

        $this->manifest->refresh();

        if ($this->manifest->error() !== null) {
            $this->addFlash('error', $this->trans('The release list could not be downloaded.', 'Modules.Quinceengine.Admin'));
        } else {
            $this->addFlash('success', $this->trans('The release list is up to date.', 'Modules.Quinceengine.Admin'));
        }

        return $this->redirectToRoute('quince_modules');
    }

    /**
     * Jeden wiersz tabeli: jeden stan i co najwyżej jedna czynność.
     *
     * Stan odpowiada na dwa pytania naraz: czy pakiet jest w sklepie i czy jest
     * aktualny. Wyłączony moduł ma osobny stan, bo „zainstalowany” bez tej
     * informacji wprowadzałby w błąd.
     *
     * @param array{version:string}|null $release
     *
     * @return array<string,mixed>
     */
    private function row(string $name, string $label, string $kind, bool $installed, bool $active, ?string $version, ?array $release): array
    {
        $latest = $release['version'] ?? null;

        if (!$installed) {
            $state = 'not_installed';
        } elseif ($latest !== null && $version !== null && version_compare($latest, $version, '>')) {
            $state = 'update';
        } elseif (!$active) {
            $state = 'disabled';
        } elseif ($latest === null) {
            $state = 'unknown';
        } else {
            $state = 'current';
        }

        $action = null;
        if ($release !== null && $state === 'not_installed') {
            $action = 'install';
        } elseif ($state === 'update') {
            $action = 'update';
        }

        return [
            'name' => $name,
            'label' => $label,
            'kind' => $kind,
            'version' => $installed ? $version : null,
            'latest' => $latest,
            'state' => $state,
            'action' => $action,
        ];
    }

    /**
     * Adres ekranu modułu: trasa z menu silnika, a gdy moduł ma ekran tylko
     * w menu PrestaShopa (np. Withdrawal pod Obsługą klienta), jego zakładka.
     * Null, gdy moduł nie ma żadnego ekranu (np. API).
     */
    private function settingsUrl(string $name, ?string $route): ?string
    {
        if ($route !== null) {
            return $this->generateUrl($route);
        }

        $link = \Context::getContext()->link;

        foreach (\Tab::getCollectionFromModule($name) as $tab) {
            if ($tab->active && $tab->class_name !== '') {
                return $link->getAdminLink($tab->class_name);
            }
        }

        // Moduł z ukrytymi zakładkami prowadzi do swoich ekranów przez „Konfiguruj”.
        $module = Module::getInstanceByName($name);
        if ($module instanceof Module && method_exists($module, 'getContent')) {
            return $link->getAdminLink('AdminModules', true, [], ['configure' => $name]);
        }

        return null;
    }

    /** Wersja motywu z jego theme.yml, albo null, gdy sklep go nie ma. */
    private function themeVersion(): ?string
    {
        $file = _PS_ALL_THEMES_DIR_ . self::THEME . '/config/theme.yml';
        if (!is_file($file)) {
            return null;
        }

        return preg_match('/^version:\s*([0-9][^\s]*)/m', (string) file_get_contents($file), $m) ? $m[1] : null;
    }
}
