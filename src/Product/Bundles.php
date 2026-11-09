<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Product;

use Context;
use Db;
use Image;
use Pack;
use Product;
use Tools;
use Validate;

/**
 * Zestawy, w których występuje oglądany produkt.
 *
 * Rdzeń daje szablonowi najwyżej jeden taki zestaw (`$packs` liczone z limitem
 * jednego wiersza) i bez jego składników. Sekcja pokazuje wszystkie, każdy jako
 * rząd „to plus to" z ceną zestawu obok sumy cen składników kupowanych osobno.
 *
 * Zestaw trafia tu tylko wtedy, gdy klient może go obejrzeć i zamówić:
 * aktywny, niewyłączony z katalogu i wystawiony do sprzedaży. Ceny liczy rdzeń,
 * z podatkiem albo bez, tak jak ta grupa klientów widzi je w całym sklepie.
 */
final class Bundles
{
    /**
     * Zdjęcie składnika. Miniatura ma 100 px, więc 250 wystarcza także na
     * ekran o podwójnej gęstości.
     */
    private const ZDJECIE = 'home_default';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function dlaProduktu(int $idProduct, Context $context): array
    {
        if ($idProduct <= 0 || !Pack::isFeatureActive()) {
            return [];
        }

        $wiersze = Db::getInstance()->executeS(sprintf(
            'SELECT DISTINCT pk.id_product_pack AS id
             FROM `%1$spack` pk
             INNER JOIN `%1$sproduct_shop` ps
                ON ps.id_product = pk.id_product_pack AND ps.id_shop = %3$d
             WHERE pk.id_product_item = %2$d
               AND ps.active = 1
               AND ps.available_for_order = 1
               AND ps.visibility <> "none"
             ORDER BY pk.id_product_pack',
            _DB_PREFIX_,
            $idProduct,
            (int) $context->shop->id
        ));

        if (!is_array($wiersze)) {
            return [];
        }

        $zPodatkiem = !Product::getTaxCalculationMethod((int) $context->cookie->id_customer);
        $zestawy = [];

        foreach ($wiersze as $wiersz) {
            $zestaw = $this->zestaw((int) $wiersz['id'], $idProduct, $zPodatkiem, $context);

            if ($zestaw !== null) {
                $zestawy[] = $zestaw;
            }
        }

        return $zestawy;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function zestaw(int $idPack, int $idProduct, bool $zPodatkiem, Context $context): ?array
    {
        $idLang = (int) $context->language->id;
        $pakiet = new Product($idPack, false, $idLang, (int) $context->shop->id);

        if (!Validate::isLoadedObject($pakiet)) {
            return null;
        }

        $precyzja = $context->getComputingPrecision();
        $skladniki = [];
        $suma = 0.0;

        foreach (Pack::getItems($idPack, $idLang) as $element) {
            $idWariantu = (int) $element->id_pack_product_attribute;
            $ile = max(1, (int) $element->pack_quantity);
            $cena = (float) Product::getPriceStatic((int) $element->id, $zPodatkiem, $idWariantu ?: null);

            $suma += Tools::ps_round($cena, $precyzja) * $ile;

            $skladniki[] = [
                'name' => (string) $element->name,
                'price' => $cena,
                'quantity' => $ile,
                'image' => $this->zdjecie($element, $idWariantu, $idLang, $context),
                'current' => (int) $element->id === $idProduct,
            ];
        }

        if ($skladniki === []) {
            return null;
        }

        // Oglądany produkt stoi pierwszy: od niego klient zaczyna czytać
        // „to plus to", a reszta jest tym, co dochodzi.
        usort($skladniki, static fn (array $a, array $b): int => (int) $b['current'] <=> (int) $a['current']);

        $promocja = null;
        $cena = (float) Product::getPriceStatic(
            $idPack, $zPodatkiem, null, 6, null, false, true, 1, false, null, null, null, $promocja
        );
        $bezObnizki = (float) Product::getPriceStatic($idPack, $zPodatkiem, null, 6, null, false, false);
        $minimum = max(1, (int) $pakiet->minimal_quantity);

        return [
            'id' => $idPack,
            'name' => (string) $pakiet->name,
            'url' => $context->link->getProductLink($pakiet),
            'price' => $cena,
            // Oszczędność liczy się od sumy składników kupowanych osobno, a nie
            // od ceny zestawu przed obniżką: to jest powód, dla którego ktoś
            // bierze zestaw zamiast pojedynczych sztuk.
            'regular' => $suma,
            'save' => max(0.0, Tools::ps_round($suma - $cena, $precyzja)),
            // Termin tylko wtedy, gdy sam zestaw ma obniżkę z datą końca.
            'end' => $cena < $bezObnizki && !empty($promocja['to']) ? (string) $promocja['to'] : '',
            'minimal' => $minimum,
            'orderable' => Pack::isInStock($idPack, $minimum),
            'items' => $skladniki,
        ];
    }

    /** Zdjęcie wariantu wskazanego w zestawie, a bez niego okładka produktu. */
    private function zdjecie(Product $element, int $idWariantu, int $idLang, Context $context): string
    {
        $idZdjecia = 0;

        if ($idWariantu > 0) {
            $zdjecia = Image::getImages($idLang, (int) $element->id, $idWariantu);
            $idZdjecia = (int) ($zdjecia[0]['id_image'] ?? 0);
        }

        if ($idZdjecia === 0) {
            $okladka = Image::getCover((int) $element->id);
            $idZdjecia = (int) ($okladka['id_image'] ?? 0);
        }

        if ($idZdjecia === 0) {
            return '';
        }

        return (string) $context->link->getImageLink((string) $element->link_rewrite, (string) $idZdjecia, self::ZDJECIE);
    }
}
