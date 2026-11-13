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

use Configuration;
use Context;
use Db;
use Module;

/**
 * Lista opinii o produkcie.
 *
 * Opinie prowadzi `productcomments`: jego tabele, jego moderacja, jego formularz
 * dodawania. Czytamy je i rysujemy sami, tak samo jak ocenę pod nazwą produktu
 * (patrz `Rating`), bo lista opinii ma wyglądać jak reszta strony.
 *
 * Czytamy z bazy, a nie z punktu końcowego modułu, bo pierwsza porcja opinii ma
 * stać w kodzie strony: bez tego opinii nie widzi ani wyszukiwarka, ani klient
 * z wyłączonym JavaScriptem. Kolejne porcje dokłada ten sam odczyt, wywołany
 * przez kontroler doładowania.
 */
final class Reviews
{
    /**
     * Ile opinii na porcję, gdy moduł nie ma własnego ustawienia.
     *
     * Moduł trzyma tę liczbę pod `PRODUCT_COMMENTS_NB_COMMENTS_PER_PAGE`
     * i szanujemy ją: sklep, który ustawił tam pięć, ma dostać pięć także tutaj.
     */
    private const NA_STRONE = 5;

    /**
     * Porcja opinii wraz z informacją, czy jest co doładowywać.
     *
     * @return array{items:array<int,array<string,mixed>>,page:int,perPage:int,total:int,hasMore:bool}|null
     */
    public static function forProduct(int $idProduct, int $strona = 1): ?array
    {
        if ($idProduct <= 0 || !self::modulJest()) {
            return null;
        }

        $strona = max(1, $strona);
        $naStrone = self::naStrone();
        $warunekProsty = self::warunek($idProduct);
        $warunekZlaczenia = self::warunek($idProduct, 'c.');

        $ile = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_comment WHERE ' . $warunekProsty
        );

        if ($ile === 0) {
            return ['items' => [], 'page' => 1, 'perPage' => $naStrone, 'total' => 0, 'hasMore' => false];
        }

        // Przydatność liczymy w tym samym zapytaniu, bo inaczej lista dziesięciu
        // opinii to jedenaście zapytań.
        $wiersze = Db::getInstance()->executeS(
            'SELECT c.id_product_comment, c.title, c.content, c.customer_name, c.grade, c.date_add,
                    cu.firstname, cu.lastname,
                    (SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_comment_usefulness u
                      WHERE u.id_product_comment = c.id_product_comment AND u.usefulness = 1) AS przydatne
             FROM ' . _DB_PREFIX_ . 'product_comment c
             LEFT JOIN ' . _DB_PREFIX_ . 'customer cu ON cu.id_customer = c.id_customer
             WHERE ' . $warunekZlaczenia . '
             ORDER BY c.date_add DESC
             LIMIT ' . (($strona - 1) * $naStrone) . ', ' . $naStrone
        ) ?: [];

        $opinie = [];

        foreach ($wiersze as $w) {
            $ocena = (int) round((float) $w['grade']);

            $opinie[] = [
                'id' => (int) $w['id_product_comment'],
                'author' => self::podpis($w),
                'initials' => self::inicjaly(self::podpis($w)),
                'date' => self::data((string) $w['date_add']),
                'dateIso' => substr((string) $w['date_add'], 0, 10),
                'grade' => $ocena,
                'stars' => self::gwiazdki($ocena),
                'title' => (string) ($w['title'] ?? ''),
                'content' => (string) $w['content'],
                'useful' => (int) $w['przydatne'],
            ];
        }

        return [
            'items' => $opinie,
            'page' => $strona,
            'perPage' => $naStrone,
            'total' => $ile,
            'hasMore' => $strona * $naStrone < $ile,
        ];
    }

    /**
     * Kryteria oceny, które ma dostać formularz opinii.
     *
     * Moduł pozwala oceniać w kilku wymiarach naraz i przypisuje kryteria albo
     * całemu katalogowi, albo kategorii, albo pojedynczemu produktowi. Warunek
     * przepisany z jego repozytorium, bo tamto chodzi przez kontener usług,
     * a my czytamy tu wprost z bazy, tak jak resztę opinii.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public static function criteria(int $idProduct, int $idLang): array
    {
        if ($idProduct <= 0 || !self::modulJest()) {
            return [];
        }

        $wiersze = Db::getInstance()->executeS(
            'SELECT k.id_product_comment_criterion AS id, kl.name
             FROM ' . _DB_PREFIX_ . 'product_comment_criterion k
             LEFT JOIN ' . _DB_PREFIX_ . 'product_comment_criterion_lang kl
                    ON kl.id_product_comment_criterion = k.id_product_comment_criterion
             LEFT JOIN ' . _DB_PREFIX_ . 'product_comment_criterion_product kp
                    ON kp.id_product_comment_criterion = k.id_product_comment_criterion
             LEFT JOIN ' . _DB_PREFIX_ . 'product_comment_criterion_category kc
                    ON kc.id_product_comment_criterion = k.id_product_comment_criterion
             LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp
                    ON cp.id_category = kc.id_category
             WHERE k.active = 1
               AND kl.id_lang = ' . $idLang . '
               AND (k.id_product_comment_criterion_type = 1
                    OR kp.id_product = ' . $idProduct . '
                    OR cp.id_product = ' . $idProduct . ')
             GROUP BY k.id_product_comment_criterion'
        ) ?: [];

        $kryteria = [];

        foreach ($wiersze as $w) {
            $kryteria[] = ['id' => (int) $w['id'], 'name' => (string) $w['name']];
        }

        return $kryteria;
    }

    /**
     * Podpis pod opinią: imię i pierwsza litera nazwiska.
     *
     * Nazwisko w całości jest daną osobową wystawioną publicznie bez potrzeby.
     * Do rozróżnienia dwóch Marków wystarczy inicjał. Gość, który nie ma konta,
     * zostaje przy nazwie, którą sam podał w formularzu.
     *
     * @param array<string,mixed> $wiersz
     */
    private static function podpis(array $wiersz): string
    {
        $imie = trim((string) ($wiersz['firstname'] ?? ''));
        $nazwisko = trim((string) ($wiersz['lastname'] ?? ''));

        if ($imie !== '') {
            return $nazwisko !== ''
                ? $imie . ' ' . mb_substr($nazwisko, 0, 1) . '.'
                : $imie;
        }

        return trim((string) ($wiersz['customer_name'] ?? ''));
    }

    /**
     * Inicjały do kółka przy nagłówku opinii.
     *
     * Dwie litery, bo jedna nie odróżnia dwóch Marków, a trzy przestają być
     * skrótem. Bez nazwiska zostaje sama pierwsza litera imienia.
     */
    private static function inicjaly(string $podpis): string
    {
        $czesci = array_values(array_filter(preg_split('/\s+/', trim($podpis)) ?: []));

        if ($czesci === []) {
            return '';
        }

        $pierwsza = mb_strtoupper(mb_substr($czesci[0], 0, 1));

        if (count($czesci) < 2) {
            return $pierwsza;
        }

        return $pierwsza . mb_strtoupper(mb_substr(end($czesci), 0, 1));
    }

    /**
     * Data zapisana po ludzku dla bieżącego języka.
     *
     * Poza żądaniem sklepu kontekst nie ma lokalizacji. Zostaje wtedy zapis
     * z bazy, bo lepszy niż błąd.
     */
    private static function data(string $data): string
    {
        $czas = strtotime($data);

        if ($czas === false) {
            return $data;
        }

        $locale = Context::getContext()->getCurrentLocale();

        return $locale !== null ? date('d.m.Y', $czas) : $data;
    }

    /**
     * Warunek wspólny dla zliczania i dla listy: ten sam zbiór w obu miejscach.
     *
     * Przedrostek kolumn podaje wywołujący, bo zapytanie liczące nie ma złączenia
     * i nie nazywa tabeli, a lista czyta z `c` złączonego z klientem.
     */
    private static function warunek(int $idProduct, string $przedrostek = ''): string
    {
        $tylkoZatwierdzone = (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE');

        return $przedrostek . 'id_product = ' . $idProduct
            . ' AND ' . $przedrostek . 'deleted = 0'
            . ($tylkoZatwierdzone ? ' AND ' . $przedrostek . 'validate = 1' : '');
    }

    /**
     * Pięć gwiazdek jako lista stanów.
     *
     * Ocena pojedynczej opinii jest liczbą całkowitą po zaokrągleniu, więc nie ma
     * tu połówek. Te ma tylko średnia ze wszystkich opinii.
     *
     * @return array<int,string> 'full' | 'empty'
     */
    private static function gwiazdki(int $ocena): array
    {
        $stany = [];

        for ($i = 1; $i <= 5; ++$i) {
            $stany[] = $i <= $ocena ? 'full' : 'empty';
        }

        return $stany;
    }

    /** Ile opinii na porcję: ustawienie modułu, gdy je ma. */
    private static function naStrone(): int
    {
        $zModulu = (int) Configuration::get('PRODUCT_COMMENTS_COMMENTS_PER_PAGE');

        return $zModulu > 0 ? $zModulu : self::NA_STRONE;
    }

    /**
     * Czy ten odwiedzający może w ogóle napisać opinię.
     *
     * Moduł ma własne ustawienie „opinie od gości" i przy wyłączonym odmawia
     * zapisu. Pytamy o to samo przed pokazaniem przycisku: przycisk, który
     * kończy się odmową, jest gorszy niż jego brak.
     */
    public static function canPost(): bool
    {
        if (!self::modulJest()) {
            return false;
        }

        if ((bool) Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')) {
            return true;
        }

        $klient = Context::getContext()->customer;

        return $klient !== null && (bool) $klient->isLogged();
    }

    /** Czy moduł zbiera głosy „przydatne". */
    public static function usefulnessEnabled(): bool
    {
        return self::modulJest() && (bool) Configuration::get('PRODUCT_COMMENTS_USEFULNESS');
    }

    /**
     * Czy opinia czeka na zatwierdzenie, zanim się pokaże.
     *
     * Pytamy ustawienia sklepu, a nie odpowiedzi modułu: po zapisie zwraca on
     * `success` i treść opinii, ale ani słowem nie mówi, czy jest już widoczna.
     * Bez tego potwierdzenie obiecywałoby publikację także tam, gdzie opinia idzie
     * do kolejki moderacji.
     */
    public static function moderated(): bool
    {
        return self::modulJest() && (bool) Configuration::get('PRODUCT_COMMENTS_MODERATE');
    }

    /** Czy moduł opinii jest zainstalowany i włączony. */
    private static function modulJest(): bool
    {
        return Module::isInstalled('productcomments') && Module::isEnabled('productcomments');
    }
}
