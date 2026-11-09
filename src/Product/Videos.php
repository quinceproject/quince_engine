<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Product;

use Db;

/**
 * Filmy produktu, czytane z pola dodatkowego i rozebrane na części.
 *
 * Sklep wpisuje adresy w jednym polu, po przecinku, bo tak wchodzą do importu
 * i do API bez rozbierania niczego. Galeria potrzebuje czego innego: dostawcy,
 * identyfikatora i adresu plakatu. Ta klasa jest tłumaczeniem jednego na drugie.
 *
 * Adres, którego nie rozpoznajemy, wypada po cichu. Literówka w jednym wierszu
 * nie może zabrać klientowi pozostałych filmów ani wywrócić galerii.
 */
final class Videos
{
    /** Nazwa kolumny w tabeli pola dodatkowego: umowa rdzenia, moduł i pole. */
    private const KOLUMNA = 'quince_engine_quince_videos';

    /**
     * Filmy jednego produktu w kolejności wpisanej przez sklep.
     *
     * @return array<int,array{dostawca:string,id:string,adres:string,plakat:string}>
     */
    public function dlaProduktu(int $idProduct, int $idShop, int $idLang): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        $sql = sprintf(
            'SELECT `%s` AS adresy FROM `%sproduct_extra_lang`
             WHERE id_product = %d AND id_shop = %d AND id_lang = %d',
            self::KOLUMNA,
            _DB_PREFIX_,
            $idProduct,
            $idShop,
            $idLang
        );

        // Pole dodatkowe zakłada dopiero instalacja silnika, a hook potrafi
        // odpalić wcześniej: brak tabeli nie może wywrócić strony produktu.
        $wynik = Db::getInstance()->executeS($sql, true, false);

        if (!is_array($wynik) || $wynik === []) {
            return [];
        }

        return $this->zAdresow((string) ($wynik[0]['adresy'] ?? ''));
    }

    /**
     * @return array<int,array{dostawca:string,id:string,adres:string,plakat:string}>
     */
    public function zAdresow(string $adresy): array
    {
        $filmy = [];

        foreach (explode(',', $adresy) as $adres) {
            $film = $this->rozpoznaj(trim($adres));

            if ($film !== null) {
                $filmy[] = $film;
            }
        }

        return $filmy;
    }

    /**
     * Wyciąga dostawcę i identyfikator z adresu.
     *
     * Wszystkie postacie, które sklep może skopiować z paska przeglądarki albo
     * z przycisku „Udostępnij": zwykły film, skrócony odnośnik, shorts,
     * osadzenie i Vimeo.
     *
     * @return array{dostawca:string,id:string,adres:string,plakat:string}|null
     */
    private function rozpoznaj(string $adres): ?array
    {
        if ($adres === '') {
            return null;
        }

        $wzory = [
            ['youtube', '~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{6,})~'],
            ['youtube', '~youtu\.be/([A-Za-z0-9_-]{6,})~'],
            ['youtube', '~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~'],
            ['youtube', '~youtube\.com/embed/([A-Za-z0-9_-]{6,})~'],
            ['vimeo', '~vimeo\.com/(?:video/)?([0-9]{6,})~'],
        ];

        foreach ($wzory as [$dostawca, $wzor]) {
            if (preg_match($wzor, $adres, $trafienie) === 1) {
                return [
                    'dostawca' => $dostawca,
                    'id' => $trafienie[1],
                    'adres' => $adres,

                    // Plakat YouTube'a wynika z identyfikatora, więc mamy go bez
                    // pytania nikogo. Vimeo oddaje go dopiero po zapytaniu, a to
                    // znaczyłoby połączenie na zewnątrz przy każdym wejściu na
                    // kartę produktu; tam plakat dociąga skrypt galerii.
                    'plakat' => $dostawca === 'youtube'
                        ? 'https://i.ytimg.com/vi/' . $trafienie[1] . '/hqdefault.jpg'
                        : '',
                ];
            }
        }

        return null;
    }
}
