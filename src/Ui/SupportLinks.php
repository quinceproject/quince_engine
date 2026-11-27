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

if (!defined('_PS_VERSION_')) {
    exit;
}

use Db;

/**
 * Adresy ekranu „Support”: wsparcie projektu, strona projektu i zgłoszenia
 * w repozytoriach modułów.
 *
 * Każdy moduł Quince ma własne repozytorium w organizacji `quinceproject`
 * pod nazwą swojego katalogu, więc adres zgłoszeń wynika z nazwy technicznej
 * modułu i nie trzeba go nigdzie wpisywać.
 */
final class SupportLinks
{
    /** Strona wsparcia projektu na Ko-fi. */
    public const KOFI = 'https://ko-fi.com/quinceproject';

    /** Strona całego projektu. */
    public const PROJECT = 'https://quinceproject.github.io/';

    /** Organizacja na GitHubie, w której każdy moduł ma swoje repozytorium. */
    public const GITHUB = 'https://github.com/quinceproject';

    /** Adres zgłoszeń repozytorium o tej nazwie. */
    public static function issues(string $repozytorium): string
    {
        return self::GITHUB . '/' . rawurlencode($repozytorium) . '/issues';
    }

    /**
     * Zainstalowane moduły Quince, do których sklep może wysłać zgłoszenie.
     *
     * Brane z tabeli modułów, a nie ze spisu w silniku: spis zna tylko moduły,
     * które mają w nim miejsce, a zgłosić trzeba się dać także do CMP, GPSR,
     * Notifuse i reszty. Nazwa pokazywana to nazwa z pliku modułu, ta sama co
     * w menedżerze modułów.
     *
     * @return array<int,array{name:string,label:string,url:string}>
     */
    public static function installedModules(): array
    {
        $wiersze = Db::getInstance()->executeS(
            'SELECT `name` FROM `' . _DB_PREFIX_ . "module`
             WHERE `name` LIKE 'quince\\_%' AND `name` <> 'quince_engine'
             ORDER BY `name`"
        );

        $moduly = [];

        foreach (is_array($wiersze) ? $wiersze : [] as $wiersz) {
            $nazwa = (string) $wiersz['name'];

            // Tylko nazwy, z których da się zbudować adres repozytorium.
            if (preg_match('/^quince_[a-z0-9_]+$/', $nazwa) !== 1) {
                continue;
            }

            $modul = \Module::getInstanceByName($nazwa);
            $etykieta = $modul instanceof \Module ? trim((string) $modul->displayName) : '';

            // Przedrostek „Quince” albo „Quince:” mają wszystkie moduły, a na
            // liście pod nagłówkiem o Quince tylko wydłuża każdą pozycję. Nazwy
            // modułów nie są jednolite („Quince Search”, „Quince: slider”,
            // „Quince ranking cech”), więc zostaje sama nazwa, od wielkiej
            // litery. Wyjątkiem są nazwy pisane celowo od małej, z wielką
            // literą w środku słowa („reCAPTCHA”): te zostają jak są.
            if (preg_match('/^Quince:?\s+(.+)$/u', $etykieta, $m) === 1) {
                $etykieta = $m[1];

                if (preg_match('/^\p{Ll}+\p{Lu}/u', $etykieta) !== 1) {
                    $etykieta = mb_strtoupper(mb_substr($etykieta, 0, 1)) . mb_substr($etykieta, 1);
                }
            }

            $moduly[] = [
                'name' => $nazwa,
                'label' => $etykieta !== '' ? $etykieta : $nazwa,
                'url' => self::issues($nazwa),
            ];
        }

        usort($moduly, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $moduly;
    }
}
