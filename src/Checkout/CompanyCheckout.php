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

use Address;
use Carrier;
use CMS;
use Configuration;
use Context;
use Country;
use Media;
use Quince\Engine\Settings\CheckoutSettings;
use Tools;
use Validate;

/**
 * Zakup na firmę w kasie Quince.
 *
 * Trzy rzeczy, których nie da się zrobić w samym motywie:
 *
 * 1. Odpytanie rejestru VIES o nazwę i adres firmy po numerze VAT
 *    (kontroler `controllers/front/vies.php`). Przeglądarka nie zrobi tego
 *    sama, bo rejestr nie oddaje nagłówków pozwalających na zapytanie ze
 *    strony sklepu.
 *
 * 2. Sprawdzenie numeru VAT po stronie serwera. Rdzeń przyjmuje do pola
 *    `vat_number` dowolny tekst, więc błędny NIP wszedłby na fakturę.
 *
 * 3. Role adresów przy zakupie na firmę. Natywny krok adresu zapisuje
 *    pierwszy formularz zawsze jako adres dostawy. U firmy pierwszy formularz
 *    to dane do faktury. Zamianę ról i zapis drugiego adresu robi nadpisanie
 *    `override/classes/checkout/CheckoutAddressesStep.php`, ta klasa
 *    poprawia rolę przy zapisie z konta klienta.
 */
final class CompanyCheckout
{
    /** Strony z formularzem adresu albo danymi klienta. */
    private const STRONY = ['order', 'address', 'authentication', 'identity'];

    public function __construct(
        private readonly Context $context,
        private readonly CheckoutSettings $settings = new CheckoutSettings(),
    ) {
    }

    /**
     * Adres przycisku „Pobierz dane" i napisy dla strony klienta.
     *
     * Adres składamy w PHP, bo tylko tutaj znamy identyfikator sklepu
     * i język: w wielosklepowości każdy sklep ma własny.
     *
     * `$qViesLookup` mówi motywowi, czy w polu NIP stawiać przycisk. Formularz
     * adresu renderuje się z zakresu, którego rodzicem jest główny Smarty,
     * więc zmienna przypisana tutaj jest w nim widoczna.
     */
    public function prepareFrontPage(): void
    {
        $strona = $this->context->controller->php_self ?? '';

        if (!in_array($strona, self::STRONY, true)) {
            return;
        }

        $vies = $this->settings->viesLookup();
        $this->context->smarty->assign('qViesLookup', $vies);

        if ($vies) {
            Media::addJsDef([
                'quinceCheckout' => [
                    'vies' => $this->context->link->getModuleLink('quince_engine', 'vies', [], true),
                    'napisy' => [
                        'pobieram' => $this->trans('Fetching…', 'Modules.Quinceengine.Shop'),
                        'pobierz' => $this->trans('Fetch details', 'Modules.Quinceengine.Shop'),
                        'blad' => $this->trans('We could not reach the register. Fill the details in by hand.', 'Modules.Quinceengine.Shop'),
                    ],
                ],
            ]);
        }

        if ($strona === 'order') {
            $this->przypiszLinkRegulaminu();
            $this->przypiszStreszczeniaKrokow();
        }
    }

    /**
     * Sprawdzenie numeru VAT przy zapisie adresu.
     *
     * Rdzeń podaje do hooka sam formularz (`['form' => $this]`). Wynik hooka
     * nie zatrzymuje zapisu: `Hook::exec` skleja odpowiedzi modułów w tekst,
     * więc zwrócone `false` nigdy nie dociera jako `false`. Zapis zatrzymuje
     * błąd dopisany do pola: `AbstractForm::validate()` kończy się
     * `!$this->hasErrors()`, a komunikat staje pod polem NIP.
     *
     * Pusty numer przepuszczamy: o tym, czy jest wymagany, decyduje wybór
     * „Kupuję jako" w kasie, a nie format adresu, bo ten sam format
     * obowiązuje osoby prywatne.
     *
     * @param array{form?: \CustomerAddressForm} $parametry
     */
    public function validateAddress(array $parametry): void
    {
        $formularz = $parametry['form'] ?? null;

        if (!$formularz instanceof \CustomerAddressForm || !$this->settings->vatCheck()) {
            return;
        }

        $pole = $formularz->getField('vat_number');

        if ($pole === null) {
            return;
        }

        $wpisany = trim((string) $pole->getValue());

        if ($wpisany === '') {
            return;
        }

        $kraj = $this->isoKraju((int) $formularz->getValue('id_country'));

        if (VatNumber::zTekstu($wpisany, $kraj)->poprawny()) {
            return;
        }

        $pole->addError($this->trans(
            'This VAT number is not correct. Check it for a typo.',
            'Modules.Quinceengine.Shop'
        ));
    }

    /**
     * Odwrócenie ról adresów przy zakupie na firmę.
     *
     * Formularz przysyła `quince_buyer=firm`, gdy klient wybrał zakup na
     * firmę, oraz `quince_other=1`, gdy zaznaczył dostawę pod inny adres.
     * Pierwszy przypadek bez drugiego znaczy, że ten sam adres jest i fakturą,
     * i dostawą, czyli natywne zachowanie i nie ma co odwracać.
     *
     * @param array{address?: Address} $parametry
     */
    public function submitAddress(array $parametry): void
    {
        $adres = $parametry['address'] ?? null;

        if (!$adres instanceof Address || Tools::getValue('quince_buyer') !== 'firm') {
            return;
        }

        // Adres firmy bez nazwy firmy nie jest adresem firmy: zostawiamy go
        // tak, jak przyszedł, zamiast oznaczać zamówienie jako firmowe na
        // podstawie samego przełącznika.
        if (trim((string) $adres->company) === '') {
            return;
        }

        $koszyk = $this->context->cart;

        if (!Validate::isLoadedObject($koszyk) || !Tools::getValue('quince_other')) {
            return;
        }

        // Dostawa pod inny adres: to, co klient wpisał jako dane firmy, jest
        // adresem do faktury. Adres dostawy przyjdzie osobnym zapisem.
        $koszyk->id_address_invoice = (int) $adres->id;
        $koszyk->update();
    }

    /**
     * Jedno zdanie na krok ukończony: adres i sposób dostawy.
     *
     * Krok adresu renderuje się przez `{render}`, który dostaje własny,
     * odcięty zakres zmiennych: ze strony szablonu kroku nie da się sięgnąć
     * do wpisanych pól. Liczymy streszczenie tutaj, z koszyka, i oddajemy
     * gotowe zdanie, a szablon tylko je wypisuje.
     */
    private function przypiszStreszczeniaKrokow(): void
    {
        $koszyk = $this->context->cart;

        if (!Validate::isLoadedObject($koszyk)) {
            return;
        }

        if ((int) $koszyk->id_address_delivery > 0) {
            $adres = new Address((int) $koszyk->id_address_delivery);

            if (Validate::isLoadedObject($adres)) {
                $czesci = array_filter([
                    trim($adres->firstname . ' ' . $adres->lastname),
                    trim($adres->address1),
                    trim($adres->postcode . ' ' . $adres->city),
                ]);

                $this->context->smarty->assign('qAdresStreszczenie', implode(', ', $czesci));
            }
        }

        if ((int) $koszyk->id_carrier > 0) {
            $przewoznik = new Carrier((int) $koszyk->id_carrier);

            if (Validate::isLoadedObject($przewoznik)) {
                $koszt = (float) $koszyk->getTotalShippingCost(null, true);
                $locale = $this->context->getCurrentLocale();

                $kwota = $koszt > 0 && $locale
                    ? $locale->formatPrice($koszt, $this->context->currency->iso_code)
                    : $this->trans('Free', 'Shop.Theme.Checkout');

                $this->context->smarty->assign('qDostawaStreszczenie', $przewoznik->name . ' · ' . $kwota);
            }
        }
    }

    /**
     * Adres regulaminu dla kolumny zamówienia.
     *
     * Prawdziwy checkbox zgody na regulamin (`conditions_to_approve`) rdzeń
     * buduje tylko w kroku płatności. Do tego czasu kolumna pokazuje nieaktywny
     * podgląd z tym samym adresem, którego rdzeń używa w prawdziwym checkboksie:
     * `PS_CONDITIONS_CMS_ID`, jak w `ConditionsToApproveFinder`.
     */
    private function przypiszLinkRegulaminu(): void
    {
        $idCms = (int) Configuration::get('PS_CONDITIONS_CMS_ID');

        if ($idCms <= 0) {
            return;
        }

        $cms = new CMS($idCms, $this->context->language->id);

        if (!Validate::isLoadedObject($cms)) {
            return;
        }

        $this->context->smarty->assign('qRegulaminUrl', $this->context->link->getCMSLink($cms, $cms->link_rewrite));
    }

    private function isoKraju(int $idKraju): string
    {
        $iso = $idKraju > 0 ? Country::getIsoById($idKraju) : false;

        return is_string($iso) && $iso !== '' ? $iso : 'PL';
    }

    private function trans(string $tekst, string $domena): string
    {
        return $this->context->getTranslator()->trans($tekst, [], $domena);
    }
}
