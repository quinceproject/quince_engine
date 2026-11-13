<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Feature;

use Db;
use Language;
use Tab;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Gałąź „Quince project” w menu PrestaShopa.
 *
 * Silnik zakłada ją sam, zamiast deklarować w `$this->tabs`. Rejestr zakładek
 * rdzenia odrzuca zakładkę bez kontrolera i bez trasy („Class ... not found in
 * controllers/admin nor routing file”), a gałąź nie ma własnego ekranu. Bez niej
 * wpisy silnika i modułów lądowały w menu z rodzicem 0, poza gałęzią.
 *
 * Razem z gałęzią powstaje ukryta zakładka `AdminQuince`, po której rdzeń
 * sprawdza uprawnienia do naszych tras. Gałąź ma wtedy od razu dziecko, więc
 * rdzeń nie robi jej kopii z przyrostkiem `_MTR` przy pierwszym wpisie modułu.
 */
final class MenuBranch
{
    private const ANCHOR = 'AdminQuince';

    /** Zakłada gałąź i zakładkę uprawnień, jeśli ich nie ma. Zwraca id gałęzi. */
    public static function ensure(): int
    {
        $idParent = self::parentId();

        if ($idParent === 0) {
            $idParent = self::save(new Tab(), MenuOrder::PARENT, (int) Tab::getIdFromClassName('IMPROVE'), true, 'Quince project', 'nutrition');
        }

        if ($idParent === 0) {
            return 0;
        }

        $idAnchor = (int) Tab::getIdFromClassName(self::ANCHOR);
        self::save($idAnchor > 0 ? new Tab($idAnchor) : new Tab(), self::ANCHOR, $idParent, false, 'Quince');

        return $idParent;
    }

    /**
     * Przenosi do gałęzi wpisy naszych modułów, które trafiły do menu bez
     * rodzica. Tylko wpisy z trasą: zakładki bez trasy z rodzicem 0 to ukryte
     * zakładki uprawnień, które rdzeń zakłada sam z pliku tras, i tak mają zostać.
     */
    public static function adoptOrphans(): void
    {
        $idParent = self::parentId();

        if ($idParent === 0) {
            return;
        }

        Db::getInstance()->update(
            'tab',
            ['id_parent' => $idParent],
            // Nazwa klasy obok nazwy modułu: część naszych modułów zostawia kolumnę
            // `module` pustą przy swoim wpisie głównym.
            "id_parent = 0 AND route_name <> '' AND (module LIKE 'quince\\_%' OR class_name LIKE 'AdminQuince%')"
                . " AND class_name <> '" . pSQL(MenuOrder::PARENT) . "'"
        );
    }

    /** Id gałęzi, także wtedy, gdy rdzeń zrobił z niej kopię `_MTR`. */
    private static function parentId(): int
    {
        $id = (int) Tab::getIdFromClassName(MenuOrder::PARENT . '_MTR');

        return $id > 0 ? $id : (int) Tab::getIdFromClassName(MenuOrder::PARENT);
    }

    private static function save(Tab $tab, string $className, int $idParent, bool $visible, string $name, string $icon = ''): int
    {
        $tab->class_name = $className;
        $tab->module = 'quince_engine';
        $tab->id_parent = $idParent;
        $tab->active = $visible;
        $tab->enabled = true;
        $tab->wording = $name;
        $tab->wording_domain = 'Modules.Quinceengine.Admin';

        if ($icon !== '') {
            $tab->icon = $icon;
        }

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $name;
        }

        return $tab->save() ? (int) $tab->id : 0;
    }
}
