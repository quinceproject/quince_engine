<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Appearance;

/**
 * Wylicza niekolorystyczne wartości bazowe motywu: typografię, przestrzeń, kształt,
 * cień i siatkę.
 *
 * Wydajemy WYŁĄCZNIE wartości bazowe. Pochodne — rozmiary nagłówków, stopnie promieni,
 * skala odstępów — liczy `tokens.css` przez `calc()`:
 *
 *     --q-fs-h6: calc(var(--q-base-size) * var(--q-scale));
 *     --q-radius-sm: calc(var(--q-radius-base) * 0.6);
 *
 * Powód jest praktyczny: przeglądarka przelicza to sama przy zmianie zmiennej, więc
 * podmiana rozmiaru bazowego natychmiast przestawia całą hierarchię nagłówków bez
 * generowania dwudziestu wartości po stronie serwera.
 *
 * Odwzorowuje kontrakt z mapy tokenów (docs/delivery/quince-token-map.html).
 */
final class MetricsGenerator
{
    /** Promień bazowy w pikselach dla każdego wyboru zaokrąglenia. */
    /**
     * Zaokrąglenie bazowe w pikselach dla dawnych wartości nazwanych.
     *
     * Panel podaje dziś `sharp` albo `round` plus wartość bazową. Te trzy nazwy
     * zostają wyłącznie po to, żeby sklep zapisany przed zmianą wyglądał po
     * aktualizacji tak samo — nowe zapisy ich nie używają.
     */
    private const RADIUS_LEGACY = [
        'soft' => 6,
        'rounded' => 10,
        'pill' => 999,
    ];


    /**
     * Jednostka odstępu w pikselach. Wszystkie odstępy w sklepie są jej wielokrotnością,
     * więc jedna decyzja przestawia rytm całej strony.
     */
    /**
     * Odstęp między sekcjami strony, w pikselach.
     *
     * Wartości odpowiadają dawnym 3 × jednostka (16/20/28), żeby sklepy nie
     * drgnęły po zmianie mechanizmu.
     */
    private const SPACING = [
        'tight'  => 48,
        'normal' => 60,
        'loose'  => 84,
    ];

    /**
     * Jednostka odstępów. STAŁA motywu, nie ustawienie: panel obiecuje „ile
     * miejsca dzieli kolejne sekcje", a jednostka niesie też wcięcia kart,
     * odstępy pól i światło w nagłówku — skalowanie jej ustawieniem sekcji
     * zmieniało cały sklep, czego nikt nie zamawiał.
     */
    private const SPACE_UNIT = 20;

    /**
     * Trzy poziomy wyniesienia. Wartości są stałe, nie wyliczane — cień zależy od tego,
     * jak daleko element „unosi się" nad tłem, a to jest decyzja projektowa, nie funkcja
     * jakiejkolwiek zmiennej.
     */
    private const SHADOW = [
        'flat' => [
            'sm' => 'none',
            'md' => 'none',
            'lg' => 'none',
        ],
        // Rozmyt jest tym, co decyduje o miękkości. Cień ciasny i twardy czyta się
        // jak druga ramka, a nie jak uniesienie — dlatego przy tej samej sile
        // kryjącej promień jest duży. Wartości sprawdzone na Flavorze.
        'subtle' => [
            'sm' => '0 1px 3px rgba(16, 24, 40, .06)',
            'md' => '0 4px 16px rgba(16, 24, 40, .08)',
            'lg' => '0 8px 30px rgba(16, 24, 40, .12)',
        ],
        'strong' => [
            'sm' => '0 2px 6px rgba(16, 24, 40, .10)',
            'md' => '0 8px 24px rgba(16, 24, 40, .14)',
            'lg' => '0 20px 50px rgba(16, 24, 40, .20)',
        ],
    ];

    /** Zapasowe kroje na wypadek, gdyby wskazany nie wczytał się w przeglądarce. */
    private const FONT_FALLBACK = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

    /**
     * Wysokość celu dotykowego. Stała motywu, nie ustawienie: WCAG 2.1 wymaga 44 × 44 px
     * dla przycisków i odnośników, a sprzedawca nie ma powodu markowego, by to zmieniać.
     */
    private const TOUCH_TARGET = 44;

    /**
     * @param array<string,string> $settings wartości z AppearanceSettings
     *
     * @return array<string,string> nazwa tokenu (bez prefiksu --q-) => wartość CSS
     */
    public function generate(array $settings): array
    {
        $radius  = $this->radiusBase($settings);
        $spacing = self::SPACING[$settings['SECTION_SPACING'] ?? 'normal'] ?? self::SPACING['normal'];
        $shadow  = self::SHADOW[$settings['SHADOW'] ?? 'subtle'] ?? self::SHADOW['subtle'];

        $out = [
            // typografia — z tych dwóch wartości tokens.css liczy h1–h6 i rozmiar drobny
            'font-heading' => $this->fontStack($settings['FONT_HEADING'] ?? 'Lexend'),
            'font-body'    => $this->fontStack($settings['FONT_BODY'] ?? 'Nunito Sans'),
            'base-size'    => $this->px($settings['TEXT_BASE'] ?? '15', 12, 20),
            'scale'        => $this->ratio($settings['TEXT_SCALE'] ?? '1.2'),

            // kształt — stopnie sm/md/lg wynikają z bazy przez calc()
            // Kreska wokół bloków jako jedna decyzja na całą stronę. `1px solid
            // transparent` zamiast `0`: blok zachowuje wymiar, więc przełączenie
            // niczego nie przesuwa i porównanie dotyczy samej kreski.
            'blok-ramka'   => ($settings['BLOCK_BORDERS'] ?? 'on') === 'off'
                ? '1px solid transparent'
                : '1px solid var(--q-border-card)',

            'radius-base'  => $radius . 'px',

            // Pigułka też słucha wyboru „ostre rogi". Sklep, który wybrał kanty,
            // dostawał je wszędzie poza plakietkami i polami z tym promieniem —
            // a wtedy licznik zdjęć w galerii i znaczniki wyglądały jak wstawka
            // z innego motywu.
            'radius-pill'  => $radius === 0 ? '0px' : '999px',

            // przestrzeń — jednostka, z której wynika cała skala odstępów,
            // oraz odstęp między sekcjami sterowany z panelu
            'space-unit'   => self::SPACE_UNIT . 'px',
            'section-gap'  => $spacing . 'px',

            // siatka
            'container'    => $this->px($settings['CONTAINER_WIDTH'] ?? '1200', 960, 1920),
            'grid-columns' => (string) $this->columns($settings['GRID_COLUMNS'] ?? '4'),

            // wyniesienie
            'shadow-sm'    => $shadow['sm'],
            'shadow-md'    => $shadow['md'],
            'shadow-lg'    => $shadow['lg'],

            // stała dostępności
            'touch-target' => self::TOUCH_TARGET . 'px',
        ];

        // Wyłączone kreski gaszą też składniki piszące własne `1px solid`
        // z własną barwą: kartę, akordeon, pasek narzędzi. Token istnieje
        // WYŁĄCZNIE przy wyłączonych — przy włączonych każdy składnik zostaje
        // przy swojej barwie przez wartość zapasową w `var()`. Token pusty
        // albo z barwą „domyślną" nie umie tego oddać: jedna barwa nie może
        // znaczyć „u każdego inna".
        if (($settings['BLOCK_BORDERS'] ?? 'on') === 'off') {
            $out['blok-ramka-barwa'] = 'transparent';
        }

        return $out;
    }

    /**
     * Krój z zapasem. Nazwa z ustawień idzie w cudzysłowie, bo bywa dwuczłonowa
     * („Space Grotesk") i bez niego przeglądarka odczyta ją jako dwa kroje.
     */
    /**
     * Zaokrąglenie bazowe: zero przy ostrych rogach, wartość z panelu przy
     * zaokrąglonych. Reszta promieni wychodzi z niej w stałych proporcjach
     * (patrz `radius-sm` i `radius-lg` niżej), bo ten sam piksel na przycisku
     * i na karcie daje zupełnie inny efekt.
     *
     * @param array<string,string> $settings
     */
    private function radiusBase(array $settings): int
    {
        $tryb = $settings['RADIUS'] ?? 'round';

        if ($tryb === 'sharp') {
            return 0;
        }

        if (isset(self::RADIUS_LEGACY[$tryb])) {
            return self::RADIUS_LEGACY[$tryb];
        }

        return max(0, min(24, (int) ($settings['RADIUS_BASE'] ?? 6)));
    }

    private function fontStack(string $font): string
    {
        $font = trim($font);

        if ($font === '' || !preg_match('/^[A-Za-z0-9 \-]{1,40}$/', $font)) {
            $font = 'Nunito Sans';
        }

        return "'" . $font . "', " . self::FONT_FALLBACK;
    }

    private function px(string $value, int $min, int $max): string
    {
        $n = (int) $value;

        return ($n < $min || $n > $max ? $min : $n) . 'px';
    }

    /** Mnożnik skali nagłówków — poza dopuszczonym zakresem hierarchia się rozjeżdża. */
    private function ratio(string $value): string
    {
        $n = (float) str_replace(',', '.', $value);

        return (string) ($n >= 1.1 && $n <= 1.4 ? $n : 1.2);
    }

    private function columns(string $value): int
    {
        $n = (int) $value;

        return in_array($n, [3, 4, 5], true) ? $n : 4;
    }
}
