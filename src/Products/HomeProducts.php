<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Products;

use Category;
use Context;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenterFactoryInterface;
use Product;
use Quince\Engine\Ui\IconLibrary;
use Validate;
use ProductAssembler;
use ProductPresenterFactory;
use ProductSale;
use Tools;

/**
 * Produkty do kolumny sekcji strony głównej.
 *
 * Dwie rzeczy i nic poza tym: skąd wziąć numery produktów i jak przełożyć je
 * na postać, której oczekuje zajawka motywu. Ta sama postać co na listingu —
 * karta ma wyglądać wszędzie tak samo, a nie „prawie tak samo".
 *
 * Prezentacja jest przepisana z `quince_product_section`: tam działa i jest
 * sprawdzona, a dwa własne sposoby prezentowania tego samego rozjechałyby się
 * przy pierwszej zmianie w rdzeniu.
 */
final class HomeProducts
{
    public function __construct(private readonly Context $context)
    {
    }

    /**
     * Numery produktów dla kolumny.
     *
     * @param array<string,mixed> $kolumna
     *
     * @return int[]
     */
    public function ids(array $kolumna): array
    {
        $jezyk = (int) $this->context->language->id;
        $ile = max(1, (int) ($kolumna['count'] ?? 8));
        $zrodlo = (string) ($kolumna['source'] ?? 'newest');

        if ($zrodlo === 'manual') {
            return array_slice(array_map('intval', $kolumna['manual'] ?? []), 0, $ile);
        }

        if ($zrodlo === 'category') {
            $kategoria = new Category((int) ($kolumna['category'] ?? 0), $jezyk);

            return $this->numery($kategoria->id ? $kategoria->getProducts($jezyk, 1, $ile) : []);
        }

        if ($zrodlo === 'bestsellers') {
            return $this->numery(ProductSale::getBestSalesLight($jezyk, 0, $ile) ?: []);
        }

        if ($zrodlo === 'sale') {
            return $this->numery(Product::getPricesDrop($jezyk, 0, $ile) ?: []);
        }

        // „Ostatnio oglądane" bierze historię z ciastka odwiedzającego.
        // Przy pierwszej wizycie jest pusta i kolumna gaśnie sama.
        if ($zrodlo === 'viewed') {
            return $this->ostatnioOgladane($ile);
        }

        if ($zrodlo === 'searched') {
            return $this->najczesciejSzukane($ile);
        }

        return $this->numery(Product::getNewProducts($jezyk, 0, $ile) ?: []);
    }

    /**
     * Produkty w postaci, której oczekuje zajawka motywu.
     *
     * @param int[] $ids
     *
     * @return array<int,mixed>
     */
    public function present(array $ids): array
    {
        $ids = $this->zywe($ids);

        if ($ids === []) {
            return [];
        }

        $assembler = new ProductAssembler($this->context);
        $ustawienia = (new ProductPresenterFactory($this->context))->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever($this->context->link),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $surowe = $assembler->assembleProducts(array_map(
            static fn (int $id): array => ['id_product' => $id],
            $ids
        ));

        $wynik = [];

        foreach ($surowe as $surowy) {
            $wynik[] = $presenter->present($ustawienia, $surowy, $this->context->language);
        }

        return $wynik;
    }

    /**
     * Jeden produkt do duzej zajawki.
     *
     * Bez typu zwracanego: prezenter oddaje `ProductListingLazyArray`, ktory
     * zachowuje sie jak tablica, ale nia nie jest. Wymuszenie `?array` konczylo
     * sie bledem typu i bialym ekranem na stronie glownej.
     *
     * @return mixed produkt w postaci dla szablonu albo null
     */
    public function one(int $id)
    {
        $gotowe = $this->present($id > 0 ? [$id] : []);

        return $gotowe[0] ?? null;
    }

    /**
     * Kategorie na kafle: nazwa, adres, zdjęcie albo ikona.
     *
     * Nazwa i zdjęcie pochodzą z katalogu, więc zmiana w nim jest widoczna na
     * stronie głównej od razu. Ikona jest wyborem sklepu, bo katalog żadnej
     * nie trzyma.
     *
     * @param array<string,mixed> $kolumna
     *
     * @return array<int,array<string,mixed>>
     */
    public function categories(array $kolumna): array
    {
        $jezyk = (int) $this->context->language->id;
        $ikony = is_array($kolumna['icons'] ?? null) ? $kolumna['icons'] : [];
        $zIkonami = ($kolumna['image'] ?? 'photo') === 'icon';

        $kafle = [];

        foreach (array_map('intval', $kolumna['categories'] ?? []) as $id) {
            $kategoria = new Category($id, $jezyk);

            if (!Validate::isLoadedObject($kategoria)) {
                continue;
            }

            $nazwaIkony = (string) ($ikony[$id] ?? '');

            $kafle[] = [
                'id' => $id,
                'name' => (string) $kategoria->name,
                'url' => $this->context->link->getCategoryLink($kategoria),
                'lead' => $this->zapowiedz($kategoria),
                'image' => $zIkonami ? '' : $this->zdjecieKategorii($kategoria),
                'icon' => $zIkonami && $nazwaIkony !== '' ? IconLibrary::svg($nazwaIkony) : '',
            ];
        }

        return $kafle;
    }

    /**
     * Produkty z historii odwiedzającego, od ostatnio oglądanego.
     *
     * Ciastko `viewed` jest miejscem, w którym historię trzyma PrestaShop,
     * więc czytamy stamtąd: sklep, który ma włączony natywny blok ostatnio
     * oglądanych, pokazuje w obu miejscach to samo.
     *
     * @return int[]
     */
    private function ostatnioOgladane(int $ile): array
    {
        $ciastko = $this->context->cookie->viewed ?? '';

        if (!is_string($ciastko) || trim($ciastko) === '') {
            return [];
        }

        $numery = array_values(array_filter(array_map('intval', explode(',', $ciastko))));

        if ($numery === []) {
            return [];
        }

        // Od ostatnio oglądanego: ciastko dopisuje na końcu.
        $numery = array_slice(array_reverse($numery), 0, $ile);

        // Produkt zdjęty ze sprzedaży zostaje w ciastku, więc listę
        // przepuszczamy jeszcze przez katalog.
        $sklep = (int) $this->context->shop->id;
        $wiersze = \Db::getInstance()->executeS(
            'SELECT p.id_product FROM ' . _DB_PREFIX_ . 'product p
             JOIN ' . _DB_PREFIX_ . 'product_shop ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $sklep . '
             WHERE ps.active = 1 AND p.id_product IN (' . implode(',', $numery) . ')'
        ) ?: [];

        $zywe = array_map('intval', array_column($wiersze, 'id_product'));

        return array_values(array_filter($numery, static function (int $id) use ($zywe): bool {
            return in_array($id, $zywe, true);
        }));
    }

    /**
     * Produkty z najwyższym score z `quince_search_score` — opcjonalna
     * zależność. Bez zainstalowanego modułu kolumna gaśnie po cichu, tak samo
     * jak „ostatnio oglądane" bez historii: panel chowa tę opcję w dropdownie,
     * ale wcześniej zapisana sekcja (moduł odinstalowany już po wyborze) ma
     * dostać puste, nie błąd.
     *
     * @return int[]
     */
    private function najczesciejSzukane(int $ile): array
    {
        if (!\Module::getInstanceByName('quince_search_score')) {
            return [];
        }

        $sklep = (int) $this->context->shop->id;
        $wiersze = \Db::getInstance()->executeS(
            'SELECT p.id_product FROM ' . _DB_PREFIX_ . 'quince_search_score_products qss
             JOIN ' . _DB_PREFIX_ . 'product p ON p.id_product = qss.id_product
             JOIN ' . _DB_PREFIX_ . 'product_shop ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $sklep . '
             WHERE ps.active = 1 AND qss.score > 0
             ORDER BY qss.score DESC, p.id_product ASC
             LIMIT ' . $ile
        ) ?: [];

        return $this->numery($wiersze);
    }

    /**
     * Jedno zdanie pod nazwą działu.
     *
     * Bierzemy opis kategorii, a nie osobne pole w panelu: sprzedawca pisze
     * go raz, przy kategorii, i ten sam tekst pracuje na listingu.
     */
    private function zapowiedz(Category $kategoria): string
    {
        $opis = trim(strip_tags((string) $kategoria->description));

        if ($opis === '') {
            $opis = trim((string) $kategoria->meta_description);
        }

        if ($opis === '') {
            return '';
        }

        $opis = preg_replace('/\s+/u', ' ', $opis);

        return Tools::substr($opis, 0, 90) . (Tools::strlen($opis) > 90 ? '…' : '');
    }

    /** Zdjęcie kategorii, jeśli katalog je ma. */
    private function zdjecieKategorii(Category $kategoria): string
    {
        $plik = _PS_CAT_IMG_DIR_ . (int) $kategoria->id . '.jpg';

        // Plik zrodlowy, a nie miniatura: `category_default` ma 141 na 180
        // pikseli i na kaflu fotograficznym rozmywa sie do nieczytelnosci.
        return is_file($plik) ? __PS_BASE_URI__ . 'img/c/' . (int) $kategoria->id . '.jpg' : '';
    }

    /**
     * Numery produktów, które faktycznie istnieją i są aktywne w tym sklepie.
     *
     * Kolumna „pojedynczy produkt" i lista „ręcznie wybrane" trzymają numer
     * wpisany kiedyś w panelu — sprzedawca mógł od tego czasu produkt skasować
     * albo wyłączyć. Bez tego filtra taki numer trafiał do `ProductAssembler`,
     * który zwracał niepełny wiersz (bez `out_of_stock`, `minimal_quantity`),
     * a cena liczona na jego podstawie wychodziła `null` — `PriceFormatter`
     * tego nie przyjmuje i cała strona główna padała białym ekranem.
     *
     * @param int[] $ids
     *
     * @return int[] podzbiór $ids, w tej samej kolejności
     */
    private function zywe(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $sklep = (int) $this->context->shop->id;
        $wiersze = \Db::getInstance()->executeS(
            'SELECT p.id_product FROM ' . _DB_PREFIX_ . 'product p
             JOIN ' . _DB_PREFIX_ . 'product_shop ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $sklep . '
             WHERE ps.active = 1 AND p.id_product IN (' . implode(',', $ids) . ')'
        ) ?: [];

        $zywe = array_map('intval', array_column($wiersze, 'id_product'));

        return array_values(array_filter($ids, static function (int $id) use ($zywe): bool {
            return in_array($id, $zywe, true);
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $wiersze
     *
     * @return int[]
     */
    private function numery(array $wiersze): array
    {
        $ids = [];

        foreach ($wiersze as $wiersz) {
            $id = (int) ($wiersz['id_product'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
