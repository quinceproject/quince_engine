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

/**
 * 0.5.5: układ listingu.
 *
 * Silnik zaczyna rozstawiać elementy na stronach z listą produktów tak samo,
 * jak robi to na karcie produktu. Do tego potrzebuje jednego hooka, którego
 * dotąd nie używał.
 *
 * `filterProductSearch` odpala się tuż przed przekazaniem wyniku wyszukiwania
 * do szablonu i przekazuje go przez referencję. Liczymy w nim, ile zawężeń
 * wybrał klient. Rdzeń podaje szablonowi gotowy HTML pigułek, ale nie ich
 * liczbę, a ta stoi przy przycisku filtrów. Policzyć da się to tylko tam:
 * później zostaje sam kawałek HTML-a, bez obiektu wyniku.
 *
 * Świeża instalacja rejestruje ten hook sama, z `install()`. Ten plik jest dla
 * sklepów, które mają silnik już u siebie.
 *
 * @param Module $module
 */
function upgrade_module_0_5_5($module): bool
{
    if ($module->isRegisteredInHook('filterProductSearch')) {
        return true;
    }

    return (bool) $module->registerHook('filterProductSearch');
}
