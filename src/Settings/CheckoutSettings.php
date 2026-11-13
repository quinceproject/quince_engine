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

use Configuration;

/**
 * Ustawienia kasy: wariant (krokowa/OPC), miejsce na hook pod potwierdzeniem
 * zamówienia i zakup na firmę.
 */
final class CheckoutSettings
{
    /**
     * Wariant kasy to klucz rdzenia, nie `QUINCE_*`: rdzeń czyta tylko
     * `PS_ONE_PAGE_CHECKOUT_ENABLED`, więc panel pisze wprost do niego.
     * Osobny klucz Quince byłby drugim źródłem prawdy.
     */
    private const MODE_CONFIG_KEY = 'PS_ONE_PAGE_CHECKOUT_ENABLED';

    public const MODE_STEP = 'step';
    public const MODE_OPC = 'opc';

    /**
     * Nazwa hooka w kolumnie formularza na stronie potwierdzenia zamówienia,
     * pod kartą „Order details". Mechanizm jak w `ProductPageSettings`: sklep
     * sam wpisuje nazwę, pod którą moduł (np. WiseBlock) się rejestruje. Puste
     * pole nic nie renderuje.
     */
    private const ORDER_CONFIRMATION_HOOK_CONFIG_KEY = 'QUINCE_CHECKOUT_ORDER_CONFIRMATION_HOOK';

    /**
     * Zakup na firmę: przycisk „Pobierz dane” przy polu NIP (zapytanie do
     * rejestru VIES) i sprawdzenie numeru przy zapisie adresu.
     *
     * Brak wiersza w konfiguracji czytamy jako „włączone”.
     */
    public const VIES_LOOKUP_CONFIG_KEY = 'QUINCE_CHECKOUT_VIES_LOOKUP';
    public const VAT_CHECK_CONFIG_KEY = 'QUINCE_CHECKOUT_VAT_CHECK';

    public function viesLookup(): bool
    {
        return $this->flag(self::VIES_LOOKUP_CONFIG_KEY);
    }

    public function vatCheck(): bool
    {
        return $this->flag(self::VAT_CHECK_CONFIG_KEY);
    }

    public function mode(): string
    {
        return Configuration::get(self::MODE_CONFIG_KEY) ? self::MODE_OPC : self::MODE_STEP;
    }

    public function orderConfirmationHook(): string
    {
        return (string) Configuration::get(self::ORDER_CONFIRMATION_HOOK_CONFIG_KEY);
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        $mode = (string) ($values['MODE'] ?? self::MODE_STEP);
        ConfigStore::set(self::MODE_CONFIG_KEY, $mode === self::MODE_OPC ? '1' : '0');

        $hook = trim((string) ($values['ORDER_CONFIRMATION_HOOK'] ?? ''));
        ConfigStore::set(self::ORDER_CONFIRMATION_HOOK_CONFIG_KEY, $hook);

        ConfigStore::set(self::VIES_LOOKUP_CONFIG_KEY, !empty($values['VIES_LOOKUP']) ? '1' : '0');
        ConfigStore::set(self::VAT_CHECK_CONFIG_KEY, !empty($values['VAT_CHECK']) ? '1' : '0');
    }

    private function flag(string $key): bool
    {
        $value = Configuration::get($key);

        return $value === false || $value === null || $value === '' ? true : $value === '1';
    }

    /**
     * Dane dla `$modules.quince_engine.checkout`, przekazywane szablonom przez
     * `hookActionFrontControllerSetVariables`.
     *
     * @return array<string,string>
     */
    public function forTemplate(): array
    {
        return [
            'orderConfirmationHook' => $this->orderConfirmationHook(),
        ];
    }
}
