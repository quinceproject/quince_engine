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
 * Konwersje barw i pomiar kontrastu.
 *
 * Dwie przestrzenie, każda do czego innego:
 *
 * HSL służy do WSKAZYWANIA barwy — odcień i nasycenie statusów, wariant szarości.
 * Tam podajemy liczby z palca i chcemy, żeby były czytelne dla człowieka.
 *
 * OKLCH służy do LICZENIA odmian — jaśniejszych i ciemniejszych stopni skali,
 * stanów najechania i wciśnięcia. Powód jest twardy: „jasność" w HSL nie ma nic
 * wspólnego z tym, co widzi oko. Żółty #fcc804 i granat #10224a mają w HSL 50%
 * i 18%, a wyglądają jak dzień i noc. Jeden wzór „zejdź o tyle a tyle" dawał
 * przez to raz brąz (żółty przyciemniony w HSL), raz zmianę niewidoczną (granat).
 * W OKLCH ta sama liczba znaczy tę samą różnicę dla każdej barwy.
 *
 * UWAGA na odcienie: `toHsl()` zwraca skalę HSL (czerwień ~4°, bursztyn ~38°,
 * zieleń ~142°, błękit ~212°), `toOklch()` zupełnie inną (żółty ~90°, czerwień ~29°).
 * Pomylenie ich sprawiło kiedyś, że ostrzeżenie wychodziło zielone.
 */
final class Color
{
    public const FALLBACK = '#2f6df6';

    public static function normalize(string $hex, string $fallback = self::FALLBACK): string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#' . strtolower($hex) : $fallback;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function toRgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex), '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @return array{0:float,1:float,2:float} odcień 0–360, nasycenie 0–1, jasność 0–1 */
    public static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(static fn (int $v): float => $v / 255, self::toRgb($hex));

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $h = 0.0;
        $s = 0.0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            $h = match (true) {
                $max === $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
                $max === $g => (($b - $r) / $d) + 2,
                default     => (($r - $g) / $d) + 4,
            };
            $h *= 60;
        }

        return [$h, $s, $l];
    }

    public static function fromHsl(float $h, float $s, float $l): string
    {
        $h = fmod(fmod($h, 360) + 360, 360);
        $s = self::clamp($s, 0.0, 1.0);
        $l = self::clamp($l, 0.0, 1.0);

        $a = $s * min($l, 1 - $l);
        $channel = static function (int $n) use ($h, $l, $a): string {
            $k = fmod($n + $h / 30, 12);
            $value = $l - $a * max(-1, min($k - 3, min(9 - $k, 1)));

            return str_pad(dechex((int) round(255 * $value)), 2, '0', STR_PAD_LEFT);
        };

        return '#' . $channel(0) . $channel(8) . $channel(4);
    }

    public static function luminance(string $hex): float
    {
        $channels = array_map(static function (int $v): float {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, self::toRgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** Kontrast wg WCAG 2.1: od 1:1 (identyczne) do 21:1 (czerń i biel). */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Czerń albo biel — ta, która ma większy kontrast z podanym tłem.
     *
     * Nie używamy czystego #000 i #fff: na kolorowym tle wyglądają jak dziura, a różnica
     * w kontraście wobec #111 i #fff jest pomijalna.
     */
    public static function readableOn(string $bg): string
    {
        return self::contrast('#111111', $bg) >= self::contrast('#ffffff', $bg) ? '#111111' : '#ffffff';
    }

    /**
     * Przesuwa jasność koloru, aż osiągnie zadany kontrast wobec tła.
     *
     * Kierunek wynika z tła: na jasnym schodzimy w dół, na ciemnym w górę. Zwraca
     * najlepszy osiągnięty wynik również wtedy, gdy progu nie da się spełnić — lepiej
     * oddać kolor bliski wymaganiu niż czerń w miejscu marki.
     */
    public static function adjustToContrast(float $h, float $s, float $l, string $bg, float $target): string
    {
        $down = self::luminance($bg) > 0.5;
        $candidate = self::fromHsl($h, $s, $l);
        $guard = 0;

        while (self::contrast($candidate, $bg) < $target && $guard++ < 100) {
            $l += $down ? -0.01 : 0.01;

            if ($l < 0.0 || $l > 1.0) {
                break;
            }

            $candidate = self::fromHsl($h, $s, $l);
        }

        return $candidate;
    }

    /** Mieszanie dwóch barw: 0.0 to `$from`, 1.0 to `$to`. */
    public static function mix(string $from, string $to, float $amount): string
    {
        [$fr, $fg, $fb] = self::toRgb($from);
        [$tr, $tg, $tb] = self::toRgb($to);
        $amount = self::clamp($amount, 0.0, 1.0);

        $channel = static fn (int $a, int $b): string => str_pad(
            dechex((int) round($a + ($b - $a) * $amount)),
            2,
            '0',
            STR_PAD_LEFT
        );

        return '#' . $channel($fr, $tr) . $channel($fg, $tg) . $channel($fb, $tb);
    }

    /** Odległość dwóch odcieni na kole barw, w stopniach (0–180). */
    public static function hueDistance(string $a, string $b): float
    {
        $d = fmod(abs(self::toHsl($a)[0] - self::toHsl($b)[0]), 360.0);

        return $d > 180 ? 360 - $d : $d;
    }

    /** Kolor przy zadanej jasności HSL, z zachowaniem odcienia i nasycenia. */
    public static function atLightness(string $hex, float $lightness): string
    {
        [$h, $s] = self::toHsl($hex);

        return self::fromHsl($h, $s, self::clamp($lightness, 0.0, 1.0));
    }

    /* ── przestrzeń percepcyjna ───────────────────────────────────────────── */

    /**
     * OKLCH: jasność 0–1, nasycenie ~0–0.4, odcień 0–360.
     *
     * @return array{0:float,1:float,2:float}
     */
    public static function toOklch(string $hex): array
    {
        [$r, $g, $b] = array_map(
            static function (int $v): float {
                $v /= 255;

                return $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            },
            self::toRgb($hex)
        );

        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a        = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb       = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return [
            $lightness,
            sqrt($a * $a + $bb * $bb),
            fmod(rad2deg(atan2($bb, $a)) + 360.0, 360.0),
        ];
    }

    /**
     * Z powrotem do sRGB, z przycięciem do gamutu.
     *
     * Nie każda para jasność–nasycenie istnieje w sRGB: mocno nasycony żółty przy
     * jasności 0.4 to kolor, którego monitor nie wyświetli. Zamiast oddać sieczkę
     * po obcięciu kanałów, zbijamy nasycenie do wartości, która się mieści — barwa
     * zostaje ta sama, tylko mniej krzyczy.
     */
    public static function fromOklch(float $lightness, float $chroma, float $hue): string
    {
        $lightness = self::clamp($lightness, 0.0, 1.0);
        $chroma = max(0.0, $chroma);

        for ($i = 0; $i < 60; $i++) {
            $hex = self::oklchToHex($lightness, $chroma, $hue);

            // Kolor jest w gamucie, gdy droga tam i z powrotem niczego nie zmieniła.
            [$l2, $c2] = self::toOklch($hex);

            if (abs($l2 - $lightness) < 0.02 && abs($c2 - $chroma) < 0.02) {
                return $hex;
            }

            $chroma -= 0.005;

            if ($chroma <= 0.0) {
                return self::oklchToHex($lightness, 0.0, $hue);
            }
        }

        return self::oklchToHex($lightness, max(0.0, $chroma), $hue);
    }

    /** Jasność percepcyjna, 0–1. Tego pojęcia używamy wszędzie, gdzie pytamy „jasny czy ciemny". */
    public static function tone(string $hex): float
    {
        return self::toOklch($hex)[0];
    }

    /**
     * Kolor przy zadanej jasności percepcyjnej, z zachowaniem odcienia.
     *
     * Odpowiednik `atLightness()`, tyle że liczony tam, gdzie liczby odpowiadają
     * widzeniu. Ciemny żółty zostaje złoty zamiast zszarzeć w brąz.
     *
     * `$chromaFactor` mnoży nasycenie. Domyślnie zostaje bez zmian — ale odmiana
     * bliska bieli MUSI je stracić, bo inaczej „prawie biały" wychodzi jaskrawym
     * pastelem: pełne nasycenie przy jasności 0.98 to nie jest tło strony, tylko
     * plama koloru.
     */
    public static function atTone(string $hex, float $tone, float $chromaFactor = 1.0): string
    {
        [, $chroma, $hue] = self::toOklch($hex);

        return self::fromOklch(self::clamp($tone, 0.0, 1.0), $chroma * max(0.0, $chromaFactor), $hue);
    }

    /** Ten sam kolor jaśniej (dodatnie) albo ciemniej (ujemne) o zadaną różnicę jasności. */
    public static function shiftTone(string $hex, float $delta): string
    {
        return self::atTone($hex, self::tone($hex) + $delta);
    }

    /**
     * Napis na tym tle: biel albo atrament, ta z większym kontrastem.
     *
     * Nie zmienia tła — w odróżnieniu od `resolveFillPair()`, która wolno poprawić
     * kolor administratora. Stany przycisku mają brać napis do tego, co wyszło,
     * a nie przesuwać kolor po raz drugi.
     */
    public static function labelFor(string $bg): string
    {
        $ink = '#1c1c1c';

        return self::contrast($bg, '#ffffff') >= self::contrast($bg, $ink) ? '#ffffff' : $ink;
    }

    /** Konwersja bez sprawdzania gamutu — do użytku wewnętrznego przez `fromOklch()`. */
    private static function oklchToHex(float $lightness, float $chroma, float $hue): string
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $channel = static function (float $linear): string {
            $v = $linear <= 0.0031308
                ? 12.92 * $linear
                : 1.055 * (max(0.0, $linear) ** (1 / 2.4)) - 0.055;

            return str_pad(dechex((int) round(self::clamp($v, 0.0, 1.0) * 255)), 2, '0', STR_PAD_LEFT);
        };

        return '#'
            . $channel(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s)
            . $channel(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s)
            . $channel(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s);
    }

    /**
     * Para wypełnienie + napis, doprowadzona do czytelności.
     *
     * Gdy ani biel, ani czerń nie osiągają 4.5:1 na kolorze administratora, przesuwamy
     * jasność wypełnienia o minimum potrzebne do progu — i zwracamy tę informację,
     * bo panel ma to powiedzieć wprost, a nie poprawiać po cichu.
     *
     * @return array{fill:string,on:string,adjusted:bool,contrast:float}
     */
    public static function resolveFillPair(string $hex): array
    {
        $white = '#ffffff';
        $ink   = '#1c1c1c';

        $onWhite = self::contrast($hex, $white);
        $onInk   = self::contrast($hex, $ink);
        $useWhite = $onWhite >= $onInk;

        if (max($onWhite, $onInk) >= 4.5) {
            return [
                'fill'     => $hex,
                'on'       => $useWhite ? $white : $ink,
                'adjusted' => false,
                'contrast' => round(max($onWhite, $onInk), 2),
            ];
        }

        // Biały napis wymaga ciemniejszego tła, czarny — jaśniejszego.
        $target = $useWhite ? $white : $ink;
        $fill   = $hex;

        for ($i = 0; $i < 40; $i++) {
            $l = self::tone($fill) + ($useWhite ? -0.03 : 0.03);

            if ($l <= 0.0 || $l >= 1.0) {
                break;
            }

            $fill = self::atTone($fill, $l);

            if (self::contrast($fill, $target) >= 4.5) {
                break;
            }
        }

        return [
            'fill'     => $fill,
            'on'       => $target,
            'adjusted' => true,
            'contrast' => round(self::contrast($fill, $target), 2),
        ];
    }

    /** Przesuwa jasność co `$step`, aż kolor osiągnie zadany kontrast wobec tła. */
    public static function shiftToContrast(string $hex, string $bg, float $target, float $step): string
    {
        $out = $hex;

        for ($i = 0; $i < 40 && self::contrast($out, $bg) < $target; $i++) {
            $l = self::tone($out) + $step;

            if ($l <= 0.0 || $l >= 1.0) {
                break;
            }

            $out = self::atTone($out, $l);
        }

        return $out;
    }

    public static function clamp(float $v, float $min, float $max): float
    {
        return max($min, min($max, $v));
    }
}
