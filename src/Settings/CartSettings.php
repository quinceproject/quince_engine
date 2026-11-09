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
 * Ustawienia koszyka: widoczność bloku zaufania i miejsce na hook pod
 * dokupką („Nie zapomnij").
 */
final class CartSettings
{
    /**
     * Nieustawiony klucz ma znaczyć „pokazuj" — tak wygląda strona koszyka,
     * zanim ktokolwiek dotknie tego ustawienia.
     */
    private const SHOW_REASSURANCE_CONFIG_KEY = 'QUINCE_CART_SHOW_REASSURANCE';

    /**
     * Nazwa hooka pod dokupką — ten sam mechanizm, co hook pod „Order details"
     * na potwierdzeniu zamówienia (`CheckoutSettings`): sklep sam wpisuje
     * nazwę, pod którą moduł (np. WiseBlock) się rejestruje. Puste pole nic
     * nie renderuje.
     */
    private const BELOW_SUGGEST_HOOK_CONFIG_KEY = 'QUINCE_CART_BELOW_SUGGEST_HOOK';

    public function showReassurance(): bool
    {
        $raw = Configuration::get(self::SHOW_REASSURANCE_CONFIG_KEY);

        return $raw === false || $raw === '' || (bool) $raw;
    }

    public function belowSuggestHook(): string
    {
        return (string) Configuration::get(self::BELOW_SUGGEST_HOOK_CONFIG_KEY);
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        ConfigStore::set(self::SHOW_REASSURANCE_CONFIG_KEY, !empty($values['SHOW_REASSURANCE']) ? '1' : '0');

        $hook = trim((string) ($values['BELOW_SUGGEST_HOOK'] ?? ''));
        ConfigStore::set(self::BELOW_SUGGEST_HOOK_CONFIG_KEY, $hook);
    }

    /**
     * Dane dla `$modules.quince_engine.cart` — ten sam mechanizm, którym
     * silnik udostępnia szablonom resztę swoich ustawień
     * (`hookActionFrontControllerSetVariables`).
     *
     * @return array<string,bool|string>
     */
    public function forTemplate(): array
    {
        return [
            'showReassurance' => $this->showReassurance(),
            'belowSuggestHook' => $this->belowSuggestHook(),
        ];
    }
}
