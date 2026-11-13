<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Checkout;

/**
 * Odpytanie rejestru VIES o dane firmy.
 *
 * VIES oddaje nazwę i adres jednym ciągiem tekstu, inaczej sformatowanym
 * w każdym kraju. Rozbijamy go na ulicę, kod pocztowy i miasto wzorcem dla
 * tego kraju, a czego nie da się rozpoznać, zostawiamy klientowi do wpisania.
 * Zgadywanie na siłę wpisałoby mu w pola śmieci do poprawienia.
 *
 * Zapytanie idzie z serwera, nie z przeglądarki: VIES nie oddaje nagłówków
 * pozwalających odpytać go ze strony sklepu.
 */
final class ViesClient
{
    private const ADRES = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s';

    /** Rejestr bywa wolny; dłuższe czekanie i tak nie pomaga klientowi. */
    private const CZAS_OCZEKIWANIA = 8;

    /**
     * @return array{znaleziony: bool, nazwa: string, ulica: string, kod: string, miasto: string, powod: string}
     */
    public function pobierz(VatNumber $nip): array
    {
        $pusty = [
            'znaleziony' => false,
            'nazwa' => '',
            'ulica' => '',
            'kod' => '',
            'miasto' => '',
            'powod' => '',
        ];

        $odpowiedz = $this->zapytaj(sprintf(self::ADRES, $nip->kraj(), $nip->numer()));

        if ($odpowiedz === null) {
            return array_merge($pusty, ['powod' => 'rejestr_niedostepny']);
        }

        $dane = json_decode($odpowiedz, true);

        if (!is_array($dane) || empty($dane['isValid'])) {
            return array_merge($pusty, ['powod' => 'nie_znaleziono']);
        }

        $adres = $this->rozbijAdres((string) ($dane['address'] ?? ''), $nip->kraj());

        return [
            'znaleziony' => true,
            'nazwa' => $this->oczysc((string) ($dane['name'] ?? '')),
            'ulica' => $adres['ulica'],
            'kod' => $adres['kod'],
            'miasto' => $adres['miasto'],
            'powod' => '',
        ];
    }

    private function zapytaj(string $adres): ?string
    {
        $kontekst = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::CZAS_OCZEKIWANIA,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $tresc = @file_get_contents($adres, false, $kontekst);

        return is_string($tresc) && $tresc !== '' ? $tresc : null;
    }

    /**
     * Rozbija adres z rejestru na pola formularza.
     *
     * Dla Polski VIES oddaje zapis w postaci „ULICA 12\n00-001 MIASTO".
     * Kod pocztowy rozpoznajemy wzorcem kraju i wszystko przed nim traktujemy
     * jako ulicę, a wszystko po nim jako miasto.
     *
     * @return array{ulica: string, kod: string, miasto: string}
     */
    private function rozbijAdres(string $surowy, string $kraj): array
    {
        $pusty = ['ulica' => '', 'kod' => '', 'miasto' => ''];
        $tekst = trim(preg_replace('/\s*\R\s*/u', "\n", $surowy) ?? '');

        if ($tekst === '' || $tekst === '---') {
            return $pusty;
        }

        $wzorceKodu = [
            'PL' => '\d{2}-\d{3}',
            'DE' => '\d{5}',
            'CZ' => '\d{3} ?\d{2}',
            'SK' => '\d{3} ?\d{2}',
            'LT' => 'LT-?\d{5}',
        ];

        $wzor = $wzorceKodu[$kraj] ?? null;

        if ($wzor === null || preg_match('/(.*?)(' . $wzor . ')\s*(.*)$/su', $tekst, $m) !== 1) {
            // Bez rozpoznanego kodu nie zgadujemy: cały zapis idzie w ulicę,
            // klient poprawi resztę sam.
            return ['ulica' => $this->oczysc($tekst), 'kod' => '', 'miasto' => ''];
        }

        return [
            'ulica' => $this->oczysc($m[1]),
            'kod' => $this->oczysc($m[2]),
            'miasto' => $this->oczysc($m[3]),
        ];
    }

    private function oczysc(string $tekst): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\n", ' ', $tekst)) ?? '');
    }
}
