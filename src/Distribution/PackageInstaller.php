<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Distribution;

use Module;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManager;
use PrestaShop\PrestaShop\Core\Module\ModuleManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Instaluje i aktualizuje moduły oraz motyw Quince z paczek z listy wydań.
 *
 * Paczkę pobieramy sami i sprawdzamy jej sumę sha256 z listy, zanim cokolwiek
 * trafi na dysk sklepu. Samą instalację robi rdzeń (`ModuleManager`), tak samo
 * jak przy wgraniu ZIP-a w Menedżerze modułów: te same haki, te same skrypty
 * aktualizacji modułu.
 */
final class PackageInstaller
{
    public function __construct(
        private readonly ReleaseManifest $manifest,
        private readonly ModuleManager $modules,
        private readonly ThemeManager $themes,
    ) {
    }

    /**
     * Instaluje moduł albo aktualizuje go do wersji z listy.
     *
     * @return string wersja po instalacji
     */
    public function installModule(string $name): string
    {
        $release = $this->manifest->modules()[$name] ?? null;
        if ($release === null) {
            throw new \RuntimeException('This module is not on the release list.');
        }

        $zip = $this->download($release['package'], $release['sha256'], $name);

        try {
            $installed = Module::isInstalled($name);
            $ok = $installed
                ? $this->modules->upgrade($name, $zip)
                : $this->modules->install($name, $zip);
        } finally {
            @unlink($zip);
        }

        $this->forgetCompiledCode(_PS_MODULE_DIR_ . $name);

        if (!$ok) {
            $errors = $this->modules->getError($name);
            throw new \RuntimeException($errors !== '' ? $errors : 'PrestaShop could not install the module.');
        }

        return $release['version'];
    }

    /**
     * Unieważnia OPcache dla plików PHP podmienionego modułu.
     *
     * Serwer z `opcache.validate_timestamps` sprawdza zmiany co
     * `opcache.revalidate_freq` sekund (często 60). Do tego czasu następne żądania
     * wykonują stary kod modułu, a ekran modułów pokazuje starą wersję, choć
     * aktualizacja się udała.
     */
    private function forgetCompiledCode(string $dir): void
    {
        if (!function_exists('opcache_invalidate') || !is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                @opcache_invalidate($file->getPathname(), true);
            }
        }
    }

    /**
     * Instaluje motyw, którego sklep jeszcze nie ma, albo podmienia pliki
     * istniejącego na wersję z listy.
     *
     * Rdzeń instaluje motyw tylko do pustego katalogu, a jego `upgrade()` nic
     * nie robi. Dlatego aktualizację robimy sami: pliki z paczki nadpisują
     * stare, a pliki spoza paczki zostają (np. dopisane przez sklep).
     *
     * @return string wersja po instalacji
     */
    public function installTheme(string $name): string
    {
        $release = $this->manifest->themes()[$name] ?? null;
        if ($release === null) {
            throw new \RuntimeException('This theme is not on the release list.');
        }

        $zip = $this->download($release['package'], $release['sha256'], $name);
        $dir = _PS_ALL_THEMES_DIR_ . $name;

        try {
            if (!is_dir($dir)) {
                $this->themes->install($zip);
            } else {
                $this->overwriteTheme($zip, $dir);
            }
        } finally {
            @unlink($zip);
        }

        return $release['version'];
    }

    private function overwriteTheme(string $zip, string $dir): void
    {
        $work = _PS_CACHE_DIR_ . 'quince/theme-' . bin2hex(random_bytes(6));
        $fs = new Filesystem();

        try {
            $archive = new \ZipArchive();
            if ($archive->open($zip) !== true || !$archive->extractTo($work)) {
                throw new \RuntimeException('The theme package could not be unpacked.');
            }
            $archive->close();

            // Paczka motywu ma pliki w katalogu głównym ZIP-a, bo tak czyta je
            // instalator rdzenia. Bez theme.yml to nie jest nasz motyw.
            if (!is_file($work . '/config/theme.yml')) {
                throw new \RuntimeException('The theme package has no config/theme.yml.');
            }

            $fs->mirror($work, $dir, null, ['override' => true]);

            // Rdzeń trzyma ustawienia motywu z theme.yml w osobnym pliku na sklep
            // i nie czyta theme.yml ponownie, dopóki ten plik istnieje.
            foreach (glob(_PS_CONFIG_DIR_ . 'themes/' . basename($dir) . '/shop*.json') ?: [] as $file) {
                @unlink($file);
            }
            $fs->remove(glob($dir . '/assets/cache/*') ?: []);
        } finally {
            $fs->remove($work);
        }
    }

    /**
     * Pobiera paczkę do pliku tymczasowego i sprawdza jej sumę.
     *
     * Tylko paczki z GitHuba organizacji Quince: adres pochodzi z pliku pobranego
     * z sieci, więc nie ufamy mu bardziej niż to konieczne.
     */
    private function download(string $url, string $sha256, string $name): string
    {
        if (!str_starts_with($url, 'https://github.com/quinceproject/')) {
            throw new \RuntimeException('The package address is not a Quince release.');
        }

        // Rdzeń rozpoznaje paczkę modułu po rozszerzeniu, a tempnam() go nie daje.
        $base = (string) tempnam(sys_get_temp_dir(), 'quince_');
        @unlink($base);
        $file = $base . '.zip';
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not create a temporary file for the package.');
        }

        try {
            $client = HttpClient::create(['timeout' => 60, 'max_redirects' => 5]);
            $response = $client->request('GET', $url);
            foreach ($client->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($file);
            throw new \RuntimeException('The package could not be downloaded: ' . $e->getMessage());
        }
        fclose($handle);

        if (!hash_equals($sha256, (string) hash_file('sha256', $file))) {
            @unlink($file);
            throw new \RuntimeException('The downloaded package does not match its checksum. Nothing was installed.');
        }

        return $file;
    }
}
