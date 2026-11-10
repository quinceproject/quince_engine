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

use Configuration;
use Quince\Engine\Settings\ConfigStore;

/**
 * Wgranie nowej paczki Bootstrap Icons z panelu.
 *
 * Robi to samo, co `tools/build-icons.php` uruchamiany ręcznie z konsoli, ale
 * z pliku .tgz wgranego przez sprzedawcę, nie pobranego z npm — sklep na
 * żywym hostingu nie musi mieć wyjścia do internetu ani włączonego
 * `allow_url_fopen`. Numer wersji nie pochodzi z nazwy pliku (sprzedawca mógł
 * ją zmienić), tylko z `package.json` wewnątrz archiwum.
 *
 * Wgrywanie zawsze zastępuje cały zestaw: ikona usunięta w nowej wersji
 * po prostu znika z dysku. Ustawienie, które na nią wskazywało, nie wywala
 * się — `IconLibrary::svg()` na nieznaną nazwę zwraca pusty ciąg.
 */
final class IconPackManager
{
    private const KEY_VERSION = 'QUINCE_ICON_PACK_VERSION';
    private const KEY_UPDATED = 'QUINCE_ICON_PACK_UPDATED';

    /** Jedyna paczka, którą przyjmujemy — inna nie ma ikon pod tą samą nazwą. */
    private const NAZWA_PACZKI = 'bootstrap-icons';

    public function version(): string
    {
        return (string) Configuration::get(self::KEY_VERSION);
    }

    public function updatedAt(): string
    {
        return (string) Configuration::get(self::KEY_UPDATED);
    }

    /**
     * @throws \RuntimeException gdy archiwum nie jest poprawną paczką Bootstrap Icons
     */
    public function importFrom(string $archivePath): string
    {
        $work = sys_get_temp_dir() . '/quince-icons-' . uniqid('', true);

        try {
            $icons = $this->extract($archivePath, $work);
            $version = $this->packageVersion($work);

            $this->replace($icons);

            ConfigStore::set(self::KEY_VERSION, $version);
            ConfigStore::set(self::KEY_UPDATED, date('Y-m-d H:i'));

            return $version;
        } finally {
            $this->usunCatalog($work);
        }
    }

    /** @return string[] ścieżki do plików .svg w rozpakowanym archiwum */
    private function extract(string $archivePath, string $work): array
    {
        try {
            $phar = new \PharData($archivePath);
            $phar->extractTo($work, null, true);
        } catch (\Exception $e) {
            throw new \RuntimeException('This is not a valid .tgz archive.', 0, $e);
        }

        $icons = glob($work . '/package/icons/*.svg') ?: [];

        if (!$icons) {
            throw new \RuntimeException('No icons found at the expected path inside the archive. This does not look like a Bootstrap Icons package.');
        }

        return $icons;
    }

    private function packageVersion(string $work): string
    {
        $manifest = $work . '/package/package.json';

        if (!is_file($manifest)) {
            throw new \RuntimeException('The archive has no package.json, so this is not an npm package.');
        }

        $dane = json_decode((string) file_get_contents($manifest), true);
        $nazwa = is_array($dane) ? (string) ($dane['name'] ?? '') : '';
        $wersja = is_array($dane) ? (string) ($dane['version'] ?? '') : '';

        if ($nazwa !== self::NAZWA_PACZKI || $wersja === '') {
            throw new \RuntimeException('This archive is not the "' . self::NAZWA_PACZKI . '" package.');
        }

        return $wersja;
    }

    /** @param string[] $icons */
    private function replace(array $icons): void
    {
        $target = $this->directory();

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        foreach (glob($target . '/*.svg') ?: [] as $stary) {
            unlink($stary);
        }

        foreach ($icons as $icon) {
            copy($icon, $target . '/' . basename($icon));
        }

        // PrestaShop oczekuje index.php w każdym katalogu modułu.
        $index = dirname($target) . '/index.php';

        if (is_file($index)) {
            copy($index, $target . '/index.php');
        }
    }

    private function usunCatalog(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $pliki = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($pliki as $plik) {
            $plik->isDir() ? rmdir((string) $plik) : unlink((string) $plik);
        }

        rmdir($dir);
    }

    private function directory(): string
    {
        return __DIR__ . '/../../views/icons';
    }
}
