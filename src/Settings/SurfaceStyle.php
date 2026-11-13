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

/**
 * Styl bloku: tło, ramka, cień i zaokrąglenie jednej powierzchni.
 *
 * Ten sam zestaw czterech ustawień opisuje kartę na stronie produktu, belkę
 * narzędzi na listingu i panel filtrów, więc liczy je jedna klasa, żeby
 * wartości znaczyły to samo na każdym ekranie.
 *
 * Powierzchnie i przedrostek tokenów podaje wywołujący.
 *
 * Pusta wartość znaczy „jak w ustawieniach globalnych" i nie wypisujemy dla
 * niej tokenu: motyw zostaje wtedy przy swojej wartości domyślnej.
 * Zapisanie dziedziczenia jako konkretnej barwy zamroziłoby blok na tym, co
 * akurat było globalnie w chwili zapisu.
 */
final class SurfaceStyle
{
    /**
     * Wartości, które wolno zapisać. Wszystko inne staje się pustym wyborem,
     * czyli „jak globalnie".
     *
     * @var array<string,string[]>
     */
    private const DOZWOLONE = [
        'border' => ['', 'on', 'off'],
        'shadow' => ['', 'flat', 'subtle', 'strong'],
        'radius' => ['', 'sharp', 'round'],
    ];

    /**
     * Wygląd powierzchni odczytany z zapisu.
     *
     * @param string[] $powierzchnie
     *
     * @return array<string,array<string,string>>
     */
    public static function read(string $json, array $powierzchnie): array
    {
        $zapisane = json_decode($json, true);
        $zapisane = is_array($zapisane) ? $zapisane : [];
        $wynik = [];

        foreach ($powierzchnie as $nazwa) {
            $wynik[$nazwa] = [
                'bg' => (string) ($zapisane[$nazwa]['bg'] ?? ''),
                'border' => (string) ($zapisane[$nazwa]['border'] ?? ''),
                'shadow' => (string) ($zapisane[$nazwa]['shadow'] ?? ''),
                'radius' => (string) ($zapisane[$nazwa]['radius'] ?? ''),
            ];
        }

        return $wynik;
    }

    /**
     * Wygląd powierzchni z wysłanego formularza, oczyszczony do zapisu.
     *
     * @param array<string,mixed> $values
     * @param string[]            $powierzchnie
     *
     * @return array<string,array<string,string>>
     */
    public static function fromForm(array $values, array $powierzchnie): array
    {
        $wyslane = is_array($values['wyglad'] ?? null) ? $values['wyglad'] : [];
        $wynik = [];

        foreach ($powierzchnie as $nazwa) {
            $pola = is_array($wyslane[$nazwa] ?? null) ? $wyslane[$nazwa] : [];

            // Tło: kod barwy albo nazwa stopnia skali. Nic innego.
            $tlo = trim((string) ($pola['bg'] ?? ''));

            if (!preg_match('/^(#[0-9a-fA-F]{3,8}|[a-z]+-\d{1,2})$/', $tlo)) {
                $tlo = '';
            }

            $wynik[$nazwa] = ['bg' => $tlo];

            foreach (self::DOZWOLONE as $klucz => $wartosci) {
                $wybor = (string) ($pola[$klucz] ?? '');
                $wynik[$nazwa][$klucz] = in_array($wybor, $wartosci, true) ? $wybor : '';
            }
        }

        return $wynik;
    }

    /**
     * Tokeny powierzchni do wypisania w nagłówku strony.
     *
     * Wypisujemy WYŁĄCZNIE to, co sklep ustawił. Motyw czyta je z wartością
     * zapasową (`var(--q-pdp-karta-bg, …)`), więc brak tokenu to nie brak
     * wyglądu, tylko wygląd domyślny.
     *
     * @param array<string,array<string,string>> $powierzchnie wygląd każdej z nich
     * @param string                             $przedrostek  np. `pdp-` albo `listing-`
     * @param array<string,string>               $paleta       stopnie skali z generatora palety
     * @param array<string,int>                  $przeskoki    o ile stopni kreska odchodzi od tła
     *
     * @return array<string,string>
     */
    public static function tokens(array $powierzchnie, string $przedrostek, array $paleta, array $przeskoki = []): array
    {
        $tokeny = [];

        foreach ($powierzchnie as $nazwa => $wyglad) {
            $klucz = $przedrostek . $nazwa . '-';

            // Kreski bloku liczone od JEGO tła, a nie od globalnego. Ustawienie
            // w Wyglądzie to różnica stopni, a nie odcień, więc kreska nigdy nie
            // wypada w kolorze tła bloku.
            $stopien = self::stopienZeSkali((string) $wyglad['bg']);

            if ($stopien !== null) {
                foreach (['kreska' => 'card', 'pole-kreska' => 'input', 'tabela-kreska' => 'table'] as $token => $rodzaj) {
                    $barwa = self::kreskaOdTla($stopien, (int) ($przeskoki[$rodzaj] ?? 0), $paleta);

                    if ($barwa !== '') {
                        $tokeny[$klucz . $token] = $barwa;
                    }
                }
            }

            if ($wyglad['bg'] !== '') {
                // Wybór z palety trzymamy jako STOPIEŃ, nie jako kod barwy:
                // po zmianie koloru marki blok ma pójść za nią, a nie zostać
                // przy odcieniu sprzed zmiany.
                $tokeny[$klucz . 'bg'] = str_starts_with((string) $wyglad['bg'], '#')
                    ? (string) $wyglad['bg']
                    : ($paleta[$wyglad['bg']] ?? '');
            }

            if ($wyglad['border'] !== '') {
                // Wyłączona ramka zostaje przezroczysta, a nie znika: blok
                // zachowuje wymiar, więc przełączenie niczego nie przesuwa.
                $tokeny[$klucz . 'border'] = $wyglad['border'] === 'on'
                    ? '1px solid var(--q-' . $przedrostek . $nazwa . '-kreska, var(--q-border-card))'
                    : '1px solid transparent';
            }

            if ($wyglad['shadow'] !== '') {
                $tokeny[$klucz . 'shadow'] = match ($wyglad['shadow']) {
                    'subtle' => 'var(--q-shadow-sm)',
                    'strong' => 'var(--q-shadow-lg)',
                    default => 'none',
                };
            }

            if ($wyglad['radius'] !== '') {
                $tokeny[$klucz . 'radius'] = $wyglad['radius'] === 'round'
                    ? 'var(--q-card-radius)'
                    : '0';
            }

            // Blok z tłem albo ramką dostaje też odstęp od krawędzi, bo bez
            // niego treść przykleja się do kreski. Osobnego pola na wcięcie nie ma.
            if ($wyglad['bg'] !== '' || $wyglad['border'] === 'on') {
                $tokeny[$klucz . 'pad'] = 'var(--q-space-md)';
            }
        }

        return array_filter($tokeny, static fn (string $v): bool => $v !== '');
    }

    /**
     * Numer stopnia skali z zapisanego wyboru powierzchni.
     *
     * Zapis ma postać `neutral-5`. Barwa wpisana kodem (`#fff`) stopnia nie ma,
     * więc kreski liczonej od niej nie da się wyprowadzić i blok zostaje przy
     * wartościach globalnych.
     */
    private static function stopienZeSkali(string $wybor): ?int
    {
        if (!preg_match('/^neutral-(\d{1,2})$/', $wybor, $trafienie)) {
            return null;
        }

        $stopien = (int) $trafienie[1];

        return $stopien >= 1 && $stopien <= 12 ? $stopien : null;
    }

    /**
     * Kreska o zadaną liczbę stopni od tła bloku.
     *
     * Na jasnych tłach schodzimy w dół skali, czyli ku ciemniejszym stopniom.
     * Na ciemnych idziemy w drugą stronę: kreska ciemniejsza od ciemnego tła
     * byłaby niewidoczna. Granicą jest koniec skali, więc blok w skrajnym
     * stopniu dostaje kreskę tak mocną, jak skala pozwala.
     *
     * @param array<string,string> $paleta
     */
    private static function kreskaOdTla(int $stopienTla, int $przeskok, array $paleta): string
    {
        if ($przeskok === 0) {
            return '';
        }

        // Stopnie 1–3 to najjaśniejsze tła, 12 to atrament. Ciemne zaczynają się
        // mniej więcej od dziewiątki i tam kreska ma iść ku jaśniejszym.
        $kierunek = $stopienTla >= 9 ? -1 : 1;
        $cel = $stopienTla + ($kierunek * abs($przeskok));
        $cel = max(1, min(12, $cel));

        return $paleta['neutral-' . $cel] ?? '';
    }
}
