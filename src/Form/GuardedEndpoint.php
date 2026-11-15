<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Form;

use Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Które żądania muszą przejść przez strażnika formularzy.
 *
 * Strażnik nie może polegać na tym, że żądanie samo przyniesie jego pola: skrypt,
 * który ich nie wyśle, ominąłby pułapkę, limit i captchę. Dla formularzy, w które
 * motyw i moduły Quince zawsze wstawiają pola strażnika, brak znacznika znaczy
 * więc „nie z naszej strony” i kończy się odrzuceniem. Prawdziwy formularz zawsze
 * ma znacznik, więc klienta to nie dotyka.
 *
 * Nazwę formularza (klucz limitu powtórzeń) ustala serwer, nie żądanie. Inaczej
 * zmiana nazwy przy każdym wysłaniu zerowałaby licznik.
 */
final class GuardedEndpoint
{
    /** Żądanie bez strażnika: ani sprawdzenia, ani wliczenia do limitu. */
    public const SKIP = '';

    /** Nazwy formularzy, które wstawia motyw i moduły Quince (`form_id` w form-guard.tpl). */
    private const KNOWN_FORMS = [
        'quince_offer',
        'quince_expert',
        'quince_qa',
        'quince_review',
        'register',
        'checkout',
    ];

    /**
     * Nazwa formularza dla tego żądania, `SKIP`, albo null, gdy adres nie jest
     * na liście (wtedy strażnik sprawdza tylko zgłoszenia niosące jego pola).
     *
     * @param bool $quinceTheme czy aktywny jest motyw Quince; tylko on wstawia
     *                          pola strażnika do formularzy rdzenia
     */
    public static function formFor(object $controller, bool $quinceTheme): ?string
    {
        switch (strtolower(get_class($controller))) {
            case 'quince_offerssubmitmodulefrontcontroller':
                // Szuflada oferty dokłada pola strażnika do każdej akcji, ale
                // dodanie produktu czy odświeżenie listy to nie zgłoszenie
                // formularza: liczone do limitu zablokowałyby samą wysyłkę.
                return Tools::getValue('action') === 'send' ? 'quince_offer' : self::SKIP;

            case 'quince_contactsmessagemodulefrontcontroller':
                return 'quince_expert';

            case 'quince_qaaskmodulefrontcontroller':
                return 'quince_qa';
        }

        if (!$quinceTheme) {
            return null;
        }

        if (strtolower(get_class($controller)) === 'productcommentspostcommentmodulefrontcontroller') {
            return 'quince_review';
        }

        if ($controller instanceof \RegistrationControllerCore && Tools::isSubmit('submitCreate')) {
            return 'register';
        }

        if ($controller instanceof \OrderControllerCore && Tools::isSubmit('submitCreate')) {
            return 'checkout';
        }

        return null;
    }

    /** Nazwa z żądania tylko wtedy, gdy jest na liście; inaczej jedna wspólna. */
    public static function knownForm(string $formId): string
    {
        return in_array($formId, self::KNOWN_FORMS, true) ? $formId : 'form';
    }
}
