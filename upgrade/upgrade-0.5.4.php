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

use Quince\Engine\Feature\MenuOrder;

/**
 * 0.5.4: gałąź „Quince project" w menu PrestaShopa.
 *
 * Do tej pory nasze moduły nie miały w menu sklepu nic: silnik zakładał gałąź
 * niewidoczną, a w niej dwie podstrony (topbar i kolory), więc sprzedawca
 * trafiał do modułu wyłącznie przez menedżer modułów.
 *
 * Po tej zmianie w „Ulepszaniu" stoi jedna gałąź z jednym wpisem na moduł.
 * Świeża instalacja zakłada te wpisy sama, z deklaracji `$this->tabs` każdego
 * modułu; sklep, który ma je już zainstalowane, dostaje je tutaj. Inaczej menu
 * pojawiłoby się dopiero po przeinstalowaniu wszystkiego.
 *
 * @param Module $module
 */
function upgrade_module_0_5_4($module): bool
{
    $idParent = zalozZakladke(MenuOrder::PARENT, 'IMPROVE', 'Quince project', true, '', 'quince_engine', 'nutrition');

    if ($idParent === 0) {
        return false;
    }

    // Zakładka od uprawnień wraca pod nową gałąź i znika z menu: wszystkie nasze
    // trasy mają `_legacy_controller: AdminQuince` i po tej nazwie PrestaShop
    // sprawdza prawo wejścia, ale jako pozycja menu dublowała wpis silnika.
    $idAnchor = (int) Tab::getIdFromClassName('AdminQuince');

    if ($idAnchor > 0) {
        $anchor = new Tab($idAnchor);
        $anchor->id_parent = $idParent;
        $anchor->active = false;
        $anchor->save();
    }

    // Podstrony silnika wypadają z menu sklepu: ma je we własnym bocznym pasku.
    foreach (['AdminQuinceTopbar', 'AdminQuinceAppearance'] as $klasa) {
        $idTab = (int) Tab::getIdFromClassName($klasa);

        if ($idTab > 0) {
            (new Tab($idTab))->delete();
        }
    }

    // Po jednym wpisie na moduł, ale tylko dla tych, które sklep ma u siebie.
    // Kolumna `module` decyduje o tym, że odinstalowanie modułu zabiera jego wpis.
    $wpisy = [
        ['quince_engine', 'AdminQuinceEngine', 'quince_appearance', 'Quince Engine'],
        ['quince_search', 'AdminQuinceSearch', 'quince_search_settings', 'Search'],
        ['quince_contacts', 'AdminQuinceContacts', 'quince_contacts', 'Contacts'],
        ['quince_offers', 'AdminQuinceOffers', 'quince_offers', 'Offers'],
        ['quince_linkblocks', 'AdminQuinceLinkblocks', 'quince_linkblocks', 'Link blocks'],
        ['quince_feature_rank', 'AdminQuinceFeatureRank', 'quince_feature_rank_settings', 'Feature rank'],
        ['quince_omnibus', 'AdminQuinceOmnibus', 'quince_omnibus_settings', 'Price history'],
        ['quince_recaptcha', 'AdminQuinceRecaptcha', 'quince_recaptcha', 'reCAPTCHA'],
        ['quince_robots', 'AdminQuinceRobots', 'quince_robots', 'Robots and AI'],
    ];

    foreach ($wpisy as [$nazwaModulu, $klasa, $trasa, $napis]) {
        if (!Module::isInstalled($nazwaModulu)) {
            continue;
        }

        zalozZakladke($klasa, MenuOrder::PARENT, $napis, true, $trasa, $nazwaModulu);
    }

    MenuOrder::apply();

    return true;
}

/**
 * Zakładka menu: zakłada brakującą, poprawia istniejącą.
 *
 * Nazwa jest jedna dla wszystkich języków. Tłumaczenia menu idą w PrestaShopie
 * przez `wording` z deklaracji modułu, a ta droga jest tylko dla sklepów, które
 * mają moduły już zainstalowane. Nazwa techniczna wystarczy, bo i tak zostaje
 * nadpisana przy najbliższej ponownej instalacji modułu.
 *
 * @return int identyfikator zakładki albo zero, gdy nie udało się jej założyć
 */
function zalozZakladke(
    string $klasa,
    string $rodzic,
    string $napis,
    bool $widoczna,
    string $trasa,
    string $modul,
    string $ikona = ''
): int {
    $idTab = (int) Tab::getIdFromClassName($klasa);

    // PrestaShop kopiuje rodzica, gdy trafia do niego pierwsze dziecko, i to
    // kopia z przyrostkiem `_MTR` staje się kontenerem. Szukamy więc obu nazw.
    $idRodzica = (int) Tab::getIdFromClassName($rodzic . '_MTR');

    if ($idRodzica === 0) {
        $idRodzica = (int) Tab::getIdFromClassName($rodzic);
    }

    $tab = $idTab > 0 ? new Tab($idTab) : new Tab();
    $tab->class_name = $klasa;
    $tab->module = $modul;
    $tab->route_name = $trasa;
    $tab->active = $widoczna;
    $tab->id_parent = $idRodzica;

    // Znak z czcionki Material Icons; wpisy modułów zostają bez ikony, bo menu
    // rysuje ją tylko przy gałęzi najwyższego poziomu.
    if ($ikona !== '') {
        $tab->icon = $ikona;
    }

    foreach (Language::getLanguages(false) as $jezyk) {
        $tab->name[(int) $jezyk['id_lang']] = $napis;
    }

    return $tab->save() ? (int) $tab->id : 0;
}
