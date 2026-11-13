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

/**
 * Zestaw ikon dostępnych w ustawieniach.
 *
 * Komplet Bootstrap Icons 1.11.3 (licencja MIT, https://icons.getbootstrap.com) leży
 * w `views/icons/` jako osobne pliki, bez fontu z cudzego serwera (adres IP
 * odwiedzającego nie wychodzi na zewnątrz).
 *
 * Osobne pliki, a nie jedna tablica: strona czyta tylko potrzebne ikony
 * (ok. 600 bajtów każda) zamiast kompletu 1,2 MB.
 *
 * Pliki wgrywa `tools/build-icons.php` albo IconPackManager; nie edytować ręcznie.
 */
final class IconLibrary
{
    /**
     * Propozycje pokazywane przy pustej frazie: kontakt, czas i dostawa.
     *
     * @var string[]
     */
    private const SUGGESTED = [
        'telephone', 'telephone-fill', 'phone', 'headset',
        'envelope', 'envelope-fill', 'envelope-at', 'chat-dots',
        'clock', 'clock-fill', 'calendar-week',
        'truck', 'box-seam', 'geo-alt', 'geo-alt-fill',
        'patch-check', 'arrow-counterclockwise', 'credit-card',
    ];

    /** Najwyższa liczba wyników wyszukiwania. */
    private const LIMIT = 60;

    /**
     * Wnętrze znacznika <svg>; pusty ciąg, gdy ikony nie ma.
     *
     * Nazwa jest sprawdzana wzorcem, nie tylko istnieniem pliku: bez tego
     * wartość z formularza mogłaby wskazać dowolny plik na dysku.
     */
    public static function svg(string $name): string
    {
        if (!self::isValidName($name)) {
            return '';
        }

        $file = self::directory() . $name . '.svg';

        if (!is_file($file)) {
            return '';
        }

        $raw = (string) file_get_contents($file);

        if (!preg_match('#<svg[^>]*>(.*)</svg>#s', $raw, $m)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', $m[1]));
    }

    public static function exists(string $name): bool
    {
        return self::isValidName($name) && is_file(self::directory() . $name . '.svg');
    }

    /**
     * Ikony pasujące do frazy. Pusta fraza zwraca propozycje.
     *
     * Dopasowanie jest po nazwie; nazwy w Bootstrap Icons opisują rzecz
     * („truck", „envelope"), a nie kształt.
     *
     * @return array<int,array{name:string,svg:string}>
     */
    public static function search(string $query): array
    {
        $query = strtolower(trim($query));

        if ($query === '') {
            return self::withSvg(self::SUGGESTED);
        }

        $exact = [];
        $starts = [];
        $contains = [];

        foreach (self::names() as $name) {
            if ($name === $query) {
                $exact[] = $name;
            } elseif (str_starts_with($name, $query)) {
                $starts[] = $name;
            } elseif (str_contains($name, $query)) {
                $contains[] = $name;
            }
        }

        // Kolejność: trafienie co do znaku, potem początek nazwy, na końcu środek.
        // Sortowanie alfabetyczne stawiałoby „telephone-fill" przed „telephone".
        return self::withSvg(array_slice(array_merge($exact, $starts, $contains), 0, self::LIMIT));
    }

    /** @return array<int,array{name:string,svg:string}> */
    public static function suggested(): array
    {
        return self::withSvg(self::SUGGESTED);
    }

    public static function count(): int
    {
        return count(self::names());
    }

    // ------------------------------------------------------------------ pomocnicze

    /**
     * @param string[] $names
     *
     * @return array<int,array{name:string,svg:string}>
     */
    private static function withSvg(array $names): array
    {
        $out = [];

        foreach ($names as $name) {
            $svg = self::svg($name);

            if ($svg !== '') {
                $out[] = ['name' => $name, 'svg' => $svg];
            }
        }

        return $out;
    }

    /**
     * Nazwy wszystkich ikon. Wynik zapamiętany na czas żądania.
     *
     * @return string[]
     */
    private static function names(): array
    {
        static $names = null;

        if ($names === null) {
            $names = array_map(
                static fn (string $path): string => basename($path, '.svg'),
                glob(self::directory() . '*.svg') ?: []
            );
        }

        return $names;
    }

    private static function isValidName(string $name): bool
    {
        return $name !== '' && (bool) preg_match('/^[a-z0-9-]+$/', $name);
    }

    private static function directory(): string
    {
        return __DIR__ . '/../../views/icons/';
    }
}
