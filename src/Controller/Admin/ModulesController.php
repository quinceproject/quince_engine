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
        $catalogue = new ModuleCatalogue();
        $modules = array_merge($catalogue->all(), $catalogue->others(array_keys($releases)));

        foreach ($modules as &$module) {
            $module['release'] = $releases[$module['name']] ?? null;
            $module['update'] = $module['release'] !== null && $module['installed'] && $module['version'] !== null
                && version_compare($module['release']['version'], (string) $module['version'], '>');
        }
        unset($module);

        $engine = Module::getInstanceByName('quince_engine');
        $engineRelease = $releases['quince_engine'] ?? null;
        $themeRelease = $this->manifest->themes()[self::THEME] ?? null;
        $themeVersion = $this->themeVersion();

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/modules.html.twig', [
            'modules' => $modules,
            'engine' => [
                'version' => $engine instanceof Module ? (string) $engine->version : null,
                'release' => $engineRelease,
                'update' => $engineRelease !== null && $engine instanceof Module
                    && version_compare($engineRelease['version'], (string) $engine->version, '>'),
            ],
            'theme' => [
                'name' => self::THEME,
                'version' => $themeVersion,
                'release' => $themeRelease,
                'update' => $themeRelease !== null && $themeVersion !== null
                    && version_compare($themeRelease['version'], $themeVersion, '>'),
            ],
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
                $version = $this->installer->installTheme($name);
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
