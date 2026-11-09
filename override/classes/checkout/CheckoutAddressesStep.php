<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

/**
 * Override: drugi adres (dostawa albo faktura, zależnie od „Kupuję jako")
 * zapisany w TYM SAMYM żądaniu co pierwszy, jednym Continue.
 *
 * Rdzeń umie zapisać tylko jeden adres na żądanie: pierwszy zapisuje adres
 * i, dopiero gdy checkbox był odznaczony, NASTĘPNE żądanie pokazuje drugi,
 * pusty formularz jako kolejny krok. W Quince panel drugiego adresu stoi
 * w DOM-ie od razu (`other_*` pola w tym samym formularzu, pokazywane JS-em),
 * więc oba adresy przychodzą w jednym POST-cie, a formularz zawsze deklaruje
 * rdzeniowi `use_same_address=1` — niech zapisze pierwszy adres i przejdzie
 * dalej jak zwykle. Role (który adres jest którym) i drugi adres poprawiamy
 * TUTAJ, PO `parent::handleRequest()`: rdzeń w swoim `saveAddress` sam wywołuje
 * `setIdAddressDelivery`/`setIdAddressInvoice` na końcu tej metody, więc
 * cokolwiek ustawilibyśmy wcześniej (np. z hooka odpalanego w trakcie zapisu),
 * on by nadpisał.
 */
class CheckoutAddressesStep extends CheckoutAddressesStepCore
{
    public function handleRequest(array $requestParams = [])
    {
        parent::handleRequest($requestParams);

        if (!isset($requestParams['saveAddress']) || $requestParams['saveAddress'] !== 'delivery') {
            return $this;
        }

        if ($this->getCheckoutProcess()->hasErrors()) {
            return $this;
        }

        $idGlowny = (int) $this->getCheckoutSession()->getIdAddressDelivery();

        if ($idGlowny <= 0) {
            return $this;
        }

        $firma = Tools::getValue('quince_buyer') === 'firm';
        $chceInnyAdres = (bool) Tools::getValue('quince_other');

        if (!$firma && !$chceInnyAdres) {
            // Osoba prywatna, bez osobnej faktury: rdzeń już ustawił oba
            // poprawnie (use_same_address=1 wysłane zawsze z formularza).
            return $this;
        }

        if ($firma && !$chceInnyAdres) {
            // Bez osobnej dostawy: ten sam adres jest i fakturą, i dostawą.
            // Formularz renderuje się rdzeniowi zawsze jako „delivery", ale
            // to, co klient wpisał, to dane do faktury (stąd NIP i VIES) —
            // jedyna poprawka to zamiana roli, nie id.
            $this->getCheckoutSession()->setIdAddressInvoice($idGlowny);

            return $this;
        }

        $drugi = $this->zapiszDrugiAdres($idGlowny);

        if (!$drugi instanceof Address || !Validate::isLoadedObject($drugi)) {
            // Błąd w drugim adresie: zostaw krok otwarty, nie przechodź dalej.
            $this->setCurrent(true);
            $this->setComplete(false);
            $this->getCheckoutProcess()->setHasErrors(true);

            return $this;
        }

        // Kolejność ma znaczenie: `setIdAddressDelivery()` woła
        // `Cart::updateAddressId()`, które podmienia KAŻDE wystąpienie
        // starego id dostawy na nowe — w tym fakturę, jeśli miała akurat to
        // samo id (bo rdzeń ustawił oba na `$idGlowny`, use_same_address=1).
        // Dlatego dostawę zmieniamy najpierw, a fakturę ustawiamy na końcu,
        // jako ostatnie, rozstrzygające słowo.
        if ($firma) {
            $this->getCheckoutSession()->setIdAddressDelivery((int) $drugi->id);
            $this->getCheckoutSession()->setIdAddressInvoice($idGlowny);
        } else {
            $this->getCheckoutSession()->setIdAddressInvoice((int) $drugi->id);
        }

        return $this;
    }

    /**
     * Drugi adres budowany ręcznie z pól `other_*`: formularz Symfony
     * rdzenia jest jeden obiekt i nie da się go wysłać dwa razy w jednym
     * żądaniu, więc dla drugiego adresu walidujemy pola samodzielnie, tymi
     * samymi regułami co `Address::$definition`.
     */
    private function zapiszDrugiAdres(int $idGlowny): ?Address
    {
        $idKraj = (int) Tools::getValue('other_id_country');
        $imie = trim((string) Tools::getValue('other_firstname'));
        $nazwisko = trim((string) Tools::getValue('other_lastname'));
        $adres1 = trim((string) Tools::getValue('other_address1'));
        $adres2 = trim((string) Tools::getValue('other_address2'));
        $kod = trim((string) Tools::getValue('other_postcode'));
        $miasto = trim((string) Tools::getValue('other_city'));
        $idStan = (int) Tools::getValue('other_id_state');
        $telefon = trim((string) Tools::getValue('other_phone'));

        if ($idKraj <= 0 || !Validate::isName($imie) || !Validate::isName($nazwisko)
            || !Validate::isAddress($adres1) || !Validate::isCityName($miasto)
        ) {
            return null;
        }

        $kraj = new Country($idKraj);

        if (!Validate::isLoadedObject($kraj)) {
            return null;
        }

        if ($kraj->need_zip_code && !$kraj->checkZipCode($kod)) {
            return null;
        }

        if ($kraj->contains_states && $idStan <= 0) {
            return null;
        }

        // Telefon jest wymagany tylko wtedy, gdy ten adres jest dostawą
        // (firma, z osobnym adresem dostawy): kurier musi mieć jak zadzwonić.
        if ($telefon !== '' && !Validate::isPhoneNumber($telefon)) {
            return null;
        }

        $adres = new Address();
        $adres->id_customer = (int) $this->context->customer->id;
        $adres->id_country = $idKraj;
        $adres->id_state = $kraj->contains_states ? $idStan : 0;
        $adres->firstname = $imie;
        $adres->lastname = $nazwisko;
        $adres->address1 = $adres1;
        $adres->address2 = $adres2;
        $adres->postcode = $kod;
        $adres->city = $miasto;
        $adres->phone = $telefon;
        $adres->alias = $this->getTranslator()->trans('My Address', [], 'Shop.Theme.Checkout');

        if (!$adres->add()) {
            return null;
        }

        return $adres;
    }
}
