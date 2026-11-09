<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use Quince\Engine\Checkout\VatNumber;
use Quince\Engine\Checkout\ViesClient;
use Quince\Engine\Settings\CheckoutSettings;

/**
 * Dane firmy po numerze VAT: punkt wejścia dla przycisku „Pobierz dane".
 *
 * Przeglądarka nie odpyta rejestru VIES bezpośrednio, więc zapytanie idzie
 * przez sklep. Odpowiedź ma zawsze ten sam kształt, także gdy numeru nie ma
 * w rejestrze albo rejestr nie odpowiada: strona klienta obsługuje jeden
 * wynik, a nie osobną ścieżkę na każdy przypadek.
 */
class Quince_EngineViesModuleFrontController extends ModuleFrontController
{
    /** @var bool nie ma tu nic do wyrenderowania poza JSON-em */
    public $ajax = true;

    public function initContent(): void
    {
        // Wyłączone w panelu: przycisku nie ma w sklepie, a zapytanie wpisane
        // ręcznie w adres nie dochodzi do rejestru.
        if (!(new CheckoutSettings())->viesLookup()) {
            $this->oddaj(['stan' => 'wylaczony']);
        }

        $wpisany = trim((string) Tools::getValue('vat_number'));
        $kraj = $this->kraj();

        if ($wpisany === '') {
            // Puste pole nie jest błędem: klient dopiero kliknął, zanim zaczął
            // pisać. Strona ustawia mu wtedy kursor w polu.
            $this->oddaj(['stan' => 'pusty']);
        }

        $nip = VatNumber::zTekstu($wpisany, $kraj);

        if (!$nip->poprawny()) {
            $this->oddaj([
                'stan' => 'niepoprawny',
                'komunikat' => $nip->kraj() === 'PL'
                    ? $this->trans('This VAT number has a wrong check digit. Look for a typo.', [], 'Modules.Quinceengine.Shop')
                    : $this->trans('Enter the full VAT number.', [], 'Modules.Quinceengine.Shop'),
            ]);
        }

        $dane = (new ViesClient())->pobierz($nip);

        if (!$dane['znaleziony']) {
            $this->oddaj([
                'stan' => $dane['powod'] === 'rejestr_niedostepny' ? 'rejestr_niedostepny' : 'nie_znaleziono',
                'komunikat' => $dane['powod'] === 'rejestr_niedostepny'
                    ? $this->trans('The register did not answer. Fill the company details in by hand.', [], 'Modules.Quinceengine.Shop')
                    : $this->trans('We could not find this number. Fill the company details in by hand.', [], 'Modules.Quinceengine.Shop'),
            ]);
        }

        $this->oddaj([
            'stan' => 'znaleziony',
            'komunikat' => $this->trans('Company name and registered address taken from VIES. Check them.', [], 'Modules.Quinceengine.Shop'),
            'dane' => [
                'company' => $dane['nazwa'],
                'address1' => $dane['ulica'],
                'postcode' => $dane['kod'],
                'city' => $dane['miasto'],
                'vat_number' => $nip->pelny(),
            ],
        ]);
    }

    /**
     * Kod kraju do zapytania.
     *
     * Lista krajów w formularzu rdzenia niesie identyfikator, nie kod ISO,
     * więc rozwiązujemy go tutaj. Gdy strona nic nie przysłała, bierzemy kraj
     * sklepu: numer bez przedrostka i tak należy do kraju, w którym klient
     * składa zamówienie.
     */
    private function kraj(): string
    {
        $idKraju = (int) Tools::getValue('id_country');

        if ($idKraju > 0) {
            $iso = Country::getIsoById($idKraju);

            if (is_string($iso) && $iso !== '') {
                return strtoupper($iso);
            }
        }

        $podany = strtoupper(trim((string) Tools::getValue('country')));

        if ($podany !== '') {
            return $podany;
        }

        $iso = Country::getIsoById((int) Configuration::get('PS_COUNTRY_DEFAULT'));

        return is_string($iso) && $iso !== '' ? strtoupper($iso) : 'PL';
    }

    /**
     * @param array<string, mixed> $tresc
     */
    private function oddaj(array $tresc): void
    {
        header('Content-Type: application/json');
        echo json_encode($tresc, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
