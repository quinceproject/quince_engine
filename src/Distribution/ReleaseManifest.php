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

use Symfony\Component\HttpClient\HttpClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Lista wydań Quince: najnowsza wersja każdego modułu i motywu, adres paczki
 * i jej suma sha256. Plik buduje repozytorium quinceproject.github.io z wydań
 * na GitHubie, silnik tylko go czyta.
 *
 * Plik trzymamy w cache na dobę. Ekran modułów nie może czekać na GitHuba przy
 * każdym otwarciu, a brak sieci ma dać listę bez wersji, nie błąd panelu.
 */
final class ReleaseManifest
{
    public const URL = 'https://quinceproject.github.io/manifest.json';

    private const TTL = 86400;

    private ?array $data = null;

    private ?string $error = null;

    /**
     * Wydania modułów pod nazwą modułu.
     *
     * @return array<string,array{version:string,package:string,sha256:string,notes:string,repository:string}>
     */
    public function modules(): array
    {
        return $this->section('modules');
    }

    /**
     * Wydania motywów pod nazwą katalogu motywu.
     *
     * @return array<string,array{version:string,package:string,sha256:string,notes:string,repository:string}>
     */
    public function themes(): array
    {
        return $this->section('themes');
    }

    /** Powód, dla którego listy nie udało się pobrać, albo null. */
    public function error(): ?string
    {
        $this->load(false);

        return $this->error;
    }

    /** Data pobrania pliku z cache, albo null, gdy go nie ma. */
    public function fetchedAt(): ?int
    {
        $file = $this->cacheFile();

        return is_file($file) ? (int) filemtime($file) : null;
    }

    /** Pobiera listę od nowa, z pominięciem cache. */
    public function refresh(): void
    {
        $this->data = null;
        $this->load(true);
    }

    /** @return array<string,array<string,string>> */
    private function section(string $name): array
    {
        $this->load(false);

        $out = [];
        foreach ((array) ($this->data[$name] ?? []) as $key => $entry) {
            if (!is_array($entry) || empty($entry['version']) || empty($entry['package']) || empty($entry['sha256'])) {
                continue;
            }
            $out[(string) $key] = [
                'version' => (string) $entry['version'],
                'package' => (string) $entry['package'],
                'sha256' => strtolower((string) $entry['sha256']),
                'notes' => (string) ($entry['notes'] ?? ''),
                'repository' => (string) ($entry['repository'] ?? ''),
            ];
        }

        return $out;
    }

    private function load(bool $force): void
    {
        if ($this->data !== null && !$force) {
            return;
        }

        $file = $this->cacheFile();
        $fresh = is_file($file) && (time() - (int) filemtime($file)) < self::TTL;

        if (!$force && $fresh) {
            $this->data = $this->decode((string) file_get_contents($file)) ?? [];

            return;
        }

        try {
            $body = HttpClient::create(['timeout' => 10])->request('GET', self::URL)->getContent();
            $decoded = $this->decode($body);
            if ($decoded === null) {
                throw new \RuntimeException('The release list is not valid JSON.');
            }
            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            @file_put_contents($file, $body);
            $this->data = $decoded;
            $this->error = null;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            // Stara lista jest lepsza niż żadna: pokazuje przynajmniej wersje
            // z ostatniego udanego pobrania.
            $this->data = is_file($file) ? ($this->decode((string) file_get_contents($file)) ?? []) : [];
        }
    }

    private function decode(string $json): ?array
    {
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private function cacheFile(): string
    {
        return rtrim(_PS_CACHE_DIR_, '/') . '/quince/manifest.json';
    }
}
