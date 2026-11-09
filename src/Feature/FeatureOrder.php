<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Feature;

use Context;
use Module;

/**
 * Kolejność cech produktu wyliczona rankingiem.
 *
 * Ranking liczy moduł `quince_feature_rank`: sprawdza, które cechy naprawdę
 * odróżniają produkty w danej kategorii, i odrzuca te o jednej wartości
 * u wszystkich oraz numery katalogowe. Silnik pyta o niego przez rejestr
 * możliwości, a nie po nazwie klasy — sklep bez tego modułu ma działać dalej,
 * tylko z cechami w kolejności katalogu.
 *
 * Osobna klasa, bo pytają o to dwa miejsca w dwóch trybach. Karta produktu
 * dostaje kolejność raz, w zmiennych strony, bo produkt jest jeden. Zajawka na
 * listingu pyta osobno dla każdego produktu, bo każdy może stać w innej
 * kategorii i mieć inny ranking — stąd modyfikator Smarty `qcechy`.
 */
final class FeatureOrder
{
    /** @var array<int,string[]> odpowiedzi w obrębie jednego żądania */
    private static array $zapamietane = [];

    /**
     * Nazwy cech w kolejności rankingu.
     *
     * Zwracamy NAZWY, bo rdzeń podaje szablonowi `grouped_features` bez
     * identyfikatorów cech i to nazwa jest tam kluczem.
     *
     * Pusta tablica znaczy „nie ma rankingu": brak modułu albo kategoria
     * jeszcze nieprzeliczona. Szablon bierze wtedy cechy tak, jak przyszły
     * z katalogu.
     *
     * @return string[]
     */
    public static function forProduct(int $idProduct): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        // Jedna strona listingu pyta o ranking dla każdej zajawki z osobna,
        // a produkty tej samej kategorii dostają tę samą odpowiedź. Bez tego
        // zapisu dwunastu zajawkom odpowiadałoby dwanaście zapytań.
        if (isset(self::$zapamietane[$idProduct])) {
            return self::$zapamietane[$idProduct];
        }

        $kontekst = Context::getContext();
        $modul = self::modulRankingu();

        $kolejnosc = $modul === null ? [] : (array) $modul->nazwyCechWKolejnosci(
            $idProduct,
            (int) $kontekst->shop->id,
            (int) $kontekst->language->id
        );

        return self::$zapamietane[$idProduct] = $kolejnosc;
    }

    /** Moduł zgłoszony do rejestru jako dostawca rankingu cech. */
    private static function modulRankingu(): ?Module
    {
        foreach ((new FeatureRegistry())->providersFor(FeatureRegistry::TYPE_FEATURE_RANK) as $dostawca) {
            if ($dostawca['module'] === '') {
                continue;
            }

            $modul = Module::getInstanceByName($dostawca['module']);

            if ($modul && method_exists($modul, 'nazwyCechWKolejnosci')) {
                return $modul;
            }
        }

        return null;
    }
}
