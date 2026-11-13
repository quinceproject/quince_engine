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
 * Numer identyfikacji podatkowej: rozbiór i sprawdzenie poprawności.
 *
 * Rdzeń PrestaShopa przyjmuje do pola `vat_number` dowolny tekst, więc błędny
 * NIP wchodzi do zamówienia i wychodzi na fakturze. Sprawdzamy go tutaj, zanim
 * adres się zapisze.
 *
 * Dla Polski liczy się cyfra kontrolna, bo tylko ona odróżnia literówkę od
 * numeru, który naprawdę istnieje. Dla pozostałych krajów sprawdzamy wyłącznie
 * długość: każdy kraj Unii ma własny wzór i własną cyfrę kontrolną, a wpisanie
 * ich wszystkich w kod sklepu znaczyłoby utrzymywanie dwudziestu siedmiu
 * algorytmów. Resztę rozstrzyga odpytanie rejestru.
 */
final class VatNumber
{
    /** Wagi cyfry kontrolnej polskiego NIP-u. */
    private const WAGI_PL = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    /** Minimalna długość numeru w pozostałych krajach, po zdjęciu przedrostka. */
    private const MIN_DLUGOSC = 4;

    private string $kraj;
    private string $numer;

    private function __construct(string $kraj, string $numer)
    {
        $this->kraj = $kraj;
        $this->numer = $numer;
    }

    /**
     * Rozbiera to, co wpisał klient.
     *
     * Przyjmujemy zarówno „PL1234563218", jak i „123-456-32-18" czy
     * „123 456 32 18": klient przepisuje numer z pieczątki albo z faktury,
     * a te zapisują go na kilka sposobów. Kraj bierzemy z przedrostka, a gdy
     * go nie ma, z kraju wybranego w adresie.
     */
    public static function zTekstu(string $tekst, string $krajAdresu = 'PL'): self
    {
        $czysty = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $tekst) ?? '');

        if (preg_match('/^([A-Z]{2})(.+)$/', $czysty, $m) === 1) {
            return new self($m[1], $m[2]);
        }

        return new self(strtoupper($krajAdresu), $czysty);
    }

    public function kraj(): string
    {
        return $this->kraj;
    }

    public function numer(): string
    {
        return $this->numer;
    }

    /** Postać, którą przyjmuje rejestr: przedrostek kraju i sam numer. */
    public function pelny(): string
    {
        return $this->kraj . $this->numer;
    }

    public function poprawny(): bool
    {
        if ($this->kraj === 'PL') {
            return $this->poprawnyPolski();
        }

        return strlen($this->numer) >= self::MIN_DLUGOSC;
    }

    private function poprawnyPolski(): bool
    {
        if (strlen($this->numer) !== 10 || !ctype_digit($this->numer)) {
            return false;
        }

        $suma = 0;
        foreach (self::WAGI_PL as $i => $waga) {
            $suma += $waga * (int) $this->numer[$i];
        }

        $kontrolna = $suma % 11;

        // Reszta równa 10 nie ma odpowiednika w jednej cyfrze, więc taki numer
        // po prostu nie istnieje.
        if ($kontrolna === 10) {
            return false;
        }

        return $kontrolna === (int) $this->numer[9];
    }
}
