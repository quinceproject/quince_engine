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
 * Daty z bazy w postaci użytecznej dla szablonu.
 *
 * PrestaShop podaje szablonowi surowe zapisy z bazy („2026-10-31 15:35:09"),
 * a Smarty nie umie ani policzyć różnicy dwóch dat, ani zapisać daty po ludzku.
 * Obie rzeczy potrzebne są przy pasie promocji: ile dni zostało decyduje o tym,
 * czy stoi zegar czy sama data.
 *
 * Klasa jedzie do Smarty jako modyfikatory `qdays` i `qdate`.
 */
final class DateFormat
{
    /**
     * Ile pełnych dni zostało do podanej chwili.
     *
     * Zwraca -1, gdy daty nie ma albo już minęła. Zero znaczy „dziś", więc te
     * dwa przypadki muszą się różnić: promocja kończąca się za godzinę wygląda
     * inaczej niż promocja bez końca.
     *
     * @param mixed $data zapis z bazy albo pusto
     */
    public static function days($data): int
    {
        $czas = self::czas($data);

        if ($czas === null || $czas <= time()) {
            return -1;
        }

        return (int) floor(($czas - time()) / 86400);
    }

    /**
     * Chwila w postaci znacznika czasu, w sekundach.
     *
     * Zegar na stronie odlicza sam, więc potrzebuje liczby, a nie napisu.
     *
     * @param mixed $data
     */
    public static function stamp($data): int
    {
        return self::czas($data) ?? 0;
    }

    /**
     * Data zapisana krótko: dzień, miesiąc, rok.
     *
     * @param mixed $data
     */
    public static function short($data): string
    {
        $czas = self::czas($data);

        return $czas === null ? '' : date('d.m.Y', $czas);
    }

    /**
     * Zapis z bazy na znacznik czasu.
     *
     * Pusta data w PrestaShopie to „0000-00-00 00:00:00", czyli napis, którego
     * nie da się przeliczyć. Taki zapis znaczy „bez terminu" i wychodzi stąd
     * jako brak wartości.
     *
     * @param mixed $data
     */
    private static function czas($data): ?int
    {
        $napis = trim((string) $data);

        if ($napis === '' || str_starts_with($napis, '0000-00-00')) {
            return null;
        }

        $czas = strtotime($napis);

        return $czas === false ? null : $czas;
    }
}
