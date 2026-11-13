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
 * Tokeny wypisywane przy żądaniu poza generatorami palety i metryk.
 *
 * Warstwa pierwsza to wszystko, czego wartość zależy od ustawień sklepu. Większość
 * z niej powstaje w PaletteGenerator i MetricsGenerator, ale nie wszystko: bywają
 * wartości brane wprost z konfiguracji PrestaShopa, a nie z panelu wyglądu.
 * Ta klasa jest ich spisem.
 *
 * Tokenu warstwy pierwszej NIE WOLNO powtórzyć w tokens.css. Warstwa kaskady
 * `quince.tokens` stoi po `quince.engine`, więc powtórzona nazwa oznacza, że
 * plik nadpisuje wartość ze sklepu i ustawienie przestaje działać po cichu,
 * bo w kodzie wszystko wygląda poprawnie. Kontrola arkuszy
 * czyta ten spis i zgłasza taką kolizję jako błąd.
 *
 * Klasa celowo nie zna PrestaShopa: kontrola arkuszy wczytuje ją bezpośrednio,
 * bez uruchamiania sklepu.
 */
final class RuntimeTokens
{
    /**
     * Proporcja kadru zajawki produktu. Pochodzi z Design → Image Settings,
     * a nie z ustawień motywu; wylicza ją Catalog\ImageRatioProvider.
     */
    public const PCARD_MEDIA_RATIO = 'pcard-media-ratio';

    /** @var string[] nazwy bez przedrostka `--q-` */
    public const NAMES = [
        self::PCARD_MEDIA_RATIO,
    ];
}
