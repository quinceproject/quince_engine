<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Kontener menu „Quince".
 *
 * Sam nie renderuje żadnego widoku — istnieje wyłącznie po to, żeby PrestaShop miał klasę
 * dla zakładki-rodzica. Bez niej instalacja zgłasza „Class AdminQuinceController not found"
 * i podrzędne zakładki lądują z id_parent = 0, czyli w głównym menu zamiast pod Quince.
 *
 * Wejście przekierowuje na pierwszy realny panel.
 */
class AdminQuinceController extends ModuleAdminController
{
    public function initContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminQuinceAppearance')
        );
    }
}
