<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Settings;

use Configuration;
use Quince\Engine\Appearance\MetricsGenerator;
use Quince\Engine\Appearance\PaletteGenerator;

/**
 * Ustawienia globalne wyglądu.
 *
 * Przechowuje WYŁĄCZNIE dwanaście decyzji administratora oraz ewentualne ręczne
 * nadpisania wyliczonych ról. Sama paleta (30 kolorów) nie jest zapisywana —
 * powstaje z tych wartości przy każdym odczycie. Dzięki temu zmiana reguły
 * wyliczania nie wymaga migracji danych.
 */
final class AppearanceSettings
{
    private const PREFIX = 'QUINCE_';

    /**
     * Dziewiętnaście decyzji administratora — pełna lista, patrz docs/07-co-ustawiamy.md.
     * Wszystko poza nimi jest wyliczane albo stałe w motywie.
     *
     * @var array<string,string> klucz => wartość domyślna
     */
    private const SCHEMA = [
        // Kolor (1–5)
        'PRIMARY'         => '#c99a3f',   // przyciski, linki, aktywne filtry, licznik koszyka
        'SECONDARY'       => '#5678bd',   // przycisk drugorzędny, ikona ulubionych
        'DARK'            => '#262a33',   // pasy ustawione na „ciemny"
        'PROMO'           => '#1f8a78',   // plakietka rabatu, cena po obniżce
        'NEUTRAL_TINT'    => 'pure',      // pure | warm | cool — charakter szarości

        // Pasy strony (6–9) — jedna decyzja na pas, sześć kolorów wylicza silnik
        'BAND_TOPBAR'     => 'dark',      // light | dark | brand
        'BAND_HEADER'     => 'light',
        'BAND_NAV'        => 'dark',
        'BAND_FOOTER'     => 'dark',

        // Powiadomienia wychodzą przy prawej górnej krawędzi, nad treścią strony,
        // więc ich tło jest decyzją o całym sklepie, a nie o pojedynczym pasie.
        'TOAST'           => 'dark',      // light | dark | brand

        // Typografia (10–13)
        // Kroje z makiet projektu. Sklep serwuje ich pliki sam, więc lista
        // w panelu kończy się tam, gdzie kończą się pliki w motywie
        // (`themes/quince/assets/css/fonts.css`).
        'FONT_HEADING'    => 'Lexend',
        'FONT_BODY'       => 'Nunito Sans',
        'TEXT_BASE'       => '15',        // px
        'TEXT_SCALE'      => '1.2',       // 1.15 | 1.2 | 1.25

        // Kształt i siatka (14–20)
        // Powierzchnie: numer stopnia skali neutralnej, nie kod barwy.
        'SURFACE_PAGE'    => '1',         // tło strony
        'SURFACE_CARD'    => '3',         // karty produktów, panele, formularze

        // Obramowania: też numer stopnia skali, z tego samego powodu co powierzchnie.
        // Kreska ma iść za paletą, a nie stać obok niej wpisana kodem barwy.
        'BORDER_CARD'     => '5',         // kreska bloków i linie dzielące
        'BORDER_FIELD'    => '6',         // pola formularzy, listy rozwijane
        'BORDER_TABLE'    => '5',         // siatka tabel, także tych z opisu
        'FIELD_ACCENT'    => 'ink',       // ink | brand — czym pole odpowiada na kursor i ognisko

        'RADIUS'          => 'round',     // sharp | round
        'RADIUS_BASE'     => '6',         // px; ile wynosi zaokrąglenie przy 'round'
        'BLOCK_BORDERS'   => 'on',        // on | off — kreska wokół karty, akordeonu, zestawów
        'SECTION_SPACING' => 'normal',    // tight | normal | loose
        'SHADOW'          => 'subtle',    // flat | subtle | strong
        'GRID_COLUMNS'    => '4',         // 3 | 4 | 5 — kolumny listingu na desktopie
        'CONTAINER_WIDTH' => '1200',      // px
    ];

    /** Ręcznie nadpisane role, JSON: {"link":"#0044cc"}. Generator ich nie rusza. */
    private const OVERRIDES = 'COLOR_OVERRIDES';

    public function __construct(
        private readonly PaletteGenerator $generator,
        private readonly MetricsGenerator $metrics,
    ) {
    }

    /**
     * Czy właściciel sklepu przygotował już kolory.
     *
     * Silnik umie policzyć paletę zawsze, bo schemat ma wartości domyślne — ale
     * dopóki nikt nie zapisał ustawień podstawowych, ta paleta jest zgadywanką
     * silnika, a nie decyzją sklepu. Ekrany, które każą wybierać Z palety, mają
     * o tym wiedzieć i nie udawać, że jest z czego wybierać.
     */
    public function isPrepared(): bool
    {
        return Configuration::get(self::PREFIX . 'PRIMARY') !== false;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];
        // Wartość domyślna wchodzi wyłącznie wtedy, gdy klucza nie ma w konfiguracji.
        // Pusty napis jest wyborem administratora — tak zapisuje się „bez ikony" —
        // i podmiana go na wartość domyślną przywracałaby skasowaną ikonę przy odczycie.
        foreach (self::SCHEMA as $key => $default) {
            $value = Configuration::get(self::PREFIX . $key);
            $out[$key] = ($value === false || $value === null) ? $default : (string) $value;
        }

        return $out;
    }

    public function get(string $key): string
    {
        return $this->all()[$key] ?? '';
    }

    /**
     * Paleta gotowa do użycia: wyliczona, z nałożonymi nadpisaniami.
     *
     * @return array<string,string>
     */
    public function palette(): array
    {
        return $this->generator->applyOverrides(
            $this->generator->generate($this->all()),
            $this->overrides()
        );
    }

    /**
     * Wyłącznie wyliczone wartości, bez nadpisań — potrzebne, żeby panel mógł pokazać
     * „przywróć wyliczony" i żeby dało się porównać, co administrator zmienił.
     *
     * @return array<string,string>
     */
    public function computed(): array
    {
        return $this->generator->generate($this->all());
    }

    /**
     * Paleta dla wartości jeszcze niezapisanych — używana przez podgląd w panelu.
     * Braki uzupełniane są bieżącymi ustawieniami, więc wystarczy przesłać zmienione pola.
     *
     * @param array<string,mixed> $values
     *
     * @return array<string,string>
     */
    public function previewPalette(array $values): array
    {
        $merged = array_merge($this->all(), array_map('strval', $values));

        return $this->generator->generate($merged);
    }

    /**
     * Komplet tokenów do wypisania w sklepie: role kolorystyczne z nadpisaniami
     * oraz wartości bazowe. Nazwy bez prefiksu — dokłada go warstwa wypisująca.
     *
     * @return array<string,string>
     */
    public function tokens(): array
    {
        return array_merge($this->palette(), $this->metrics->generate($this->all()));
    }

    /**
     * Co silnik skorygował i co budzi wątpliwość — dla bieżących ustawień.
     *
     * @return array{adjustments:array<string,array{from:string,to:string,contrast:float}>,warnings:string[]}
     */
    public function diagnostics(): array
    {
        return $this->generator->diagnostics($this->all());
    }

    /**
     * To samo dla wartości jeszcze niezapisanych — używane przez podgląd w panelu.
     *
     * @param array<string,mixed> $values
     *
     * @return array{adjustments:array<string,array{from:string,to:string,contrast:float}>,warnings:string[]}
     */
    public function previewDiagnostics(array $values): array
    {
        return $this->generator->diagnostics(array_merge($this->all(), array_map('strval', $values)));
    }

    /** @return array<string,string> */
    public function overrides(): array
    {
        $raw = Configuration::get(self::PREFIX . self::OVERRIDES);
        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $role => $colour) {
            if (is_string($role) && is_string($colour) && preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
                $out[$role] = strtolower($colour);
            }
        }

        return $out;
    }

    /**
     * Zapisuje wyłącznie klucze obecne w schemacie — nieznane pola formularza są ignorowane.
     *
     * @param array<string,mixed> $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::SCHEMA)) {
                continue;
            }
            ConfigStore::set(self::PREFIX . $key, (string) $value);
        }

        if (array_key_exists('COLOR_OVERRIDES', $values)) {
            $this->saveOverrides((string) $values['COLOR_OVERRIDES']);
        }
    }

    /**
     * Przyjmuje JSON z panelu. Odrzuca role spoza wyliczonej palety oraz wartości,
     * które są identyczne z wyliczonymi — nie ma sensu przypinać czegoś,
     * co i tak by wyszło.
     */
    private function saveOverrides(string $json): void
    {
        $decoded = json_decode($json, true);
        $computed = $this->computed();
        $clean = [];

        if (is_array($decoded)) {
            foreach ($decoded as $role => $colour) {
                if (!is_string($role) || !array_key_exists($role, $computed)) {
                    continue;
                }
                if (!is_string($colour) || !preg_match('/^#[0-9a-fA-F]{6}$/', $colour)) {
                    continue;
                }
                if (strtolower($colour) === strtolower($computed[$role])) {
                    continue;
                }
                $clean[$role] = strtolower($colour);
            }
        }

        ConfigStore::set(self::PREFIX . self::OVERRIDES, (string) json_encode($clean), true);
    }

    /** @return array<string,string> */
    public function defaults(): array
    {
        return self::SCHEMA;
    }

    /** Klucze konfiguracji do usunięcia przy odinstalowaniu modułu. */
    public static function configurationKeys(): array
    {
        $keys = array_map(static fn (string $k): string => self::PREFIX . $k, array_keys(self::SCHEMA));
        $keys[] = self::PREFIX . self::OVERRIDES;

        return $keys;
    }
}
