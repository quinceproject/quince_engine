<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Catalog;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use Db;
use Shop;

/**
 * Zestaw zdjęć, którego potrzebuje motyw Quince: rozmiary typów obrazu
 * i formaty plików.
 *
 * Rozmiary te same co w `config/theme.yml` motywu. Motyw ustawia je tylko przy
 * swojej instalacji, a sklep, który już działa, zostaje przy swoich. Ta klasa
 * ustawia je przy instalacji silnika (gdy sklep nie ma jeszcze zdjęć produktów)
 * i na przycisk w Narzędziach.
 *
 * Wszystkie typy kwadratowe, dopasowanie bez przycinania (`fit`), jak
 * w PrestaShopie. Zakresy (produkty, kategorie…) zostają takie, jakie są.
 */
final class ImageSetup
{
    /**
     * Rozmiary w pikselach (kwadrat) i zakres nowego typu, gdyby sklep go nie miał.
     *
     * @var array<string,array{size:int,scopes:string[]}>
     */
    private const SIZES = [
        'small_default' => ['size' => 200, 'scopes' => ['products', 'categories', 'manufacturers', 'suppliers']],
        'cart_default' => ['size' => 250, 'scopes' => ['products']],
        'home_default' => ['size' => 450, 'scopes' => ['products']],
        'medium_default' => ['size' => 800, 'scopes' => ['products', 'manufacturers', 'suppliers']],
        'large_default' => ['size' => 1600, 'scopes' => ['products', 'manufacturers', 'suppliers']],
        'category_default' => ['size' => 300, 'scopes' => ['categories']],
    ];

    /** Plik podstawowy JPEG; PNG tylko tam, gdzie sklep wybrał „PNG dla wszystkich”. */
    private const BASE_FORMAT = 'jpg';

    /**
     * Stan sklepu obok zestawu, dla ekranu Narzędzia.
     *
     * @return array{types: array<int,array{name:string,current:?string,recommended:string,ok:bool}>, formats: array{current:string,recommended:string,ok:bool}, base: array{current:string,recommended:string,ok:bool}, webp:bool, ok:bool}
     */
    public function status(): array
    {
        $obecne = $this->currentTypes();
        $typy = [];
        $wszystkoOk = true;

        foreach (self::SIZES as $nazwa => $opis) {
            $teraz = isset($obecne[$nazwa]) ? $obecne[$nazwa]['width'] . ' × ' . $obecne[$nazwa]['height'] : null;
            $ok = isset($obecne[$nazwa])
                && $obecne[$nazwa]['width'] === $opis['size']
                && $obecne[$nazwa]['height'] === $opis['size'];
            $wszystkoOk = $wszystkoOk && $ok;

            $typy[] = [
                'name' => $nazwa,
                'current' => $teraz,
                'recommended' => $opis['size'] . ' × ' . $opis['size'],
                'ok' => $ok,
            ];
        }

        $formaty = (string) Configuration::get('PS_IMAGE_FORMAT');
        $zalecane = $this->recommendedFormats($formaty);
        $formatyOk = $this->sameList($formaty, $zalecane);

        $baza = (string) Configuration::get('PS_IMAGE_QUALITY');
        $bazaOk = $baza === self::BASE_FORMAT;

        return [
            'types' => $typy,
            'formats' => ['current' => $formaty, 'recommended' => $zalecane, 'ok' => $formatyOk],
            'base' => ['current' => $baza, 'recommended' => self::BASE_FORMAT, 'ok' => $bazaOk],
            'webp' => $this->webpSupported(),
            'ok' => $wszystkoOk && $formatyOk && $bazaOk,
        ];
    }

    /**
     * Przy instalacji silnika: tylko w sklepie bez zdjęć produktów. W sklepie ze
     * zdjęciami zmiana rozmiarów bez przegenerowania miniatur rozjechałaby
     * pliki i wymiary, a przegenerowanie trwa; o tym decyduje sklep w Narzędziach.
     */
    public function applyOnInstall(): void
    {
        $zdjec = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'image`');

        if ($zdjec === 0) {
            $this->apply();
        }
    }

    /** Ustawia zestaw: rozmiary typów, formaty i format pliku podstawowego. */
    public function apply(): void
    {
        $db = Db::getInstance();
        $obecne = $this->currentTypes();

        foreach (self::SIZES as $nazwa => $opis) {
            if (isset($obecne[$nazwa])) {
                $db->update(
                    'image_type',
                    ['width' => $opis['size'], 'height' => $opis['size']],
                    '`name` = \'' . pSQL($nazwa) . '\''
                );

                continue;
            }

            $wiersz = [
                'name' => pSQL($nazwa),
                'width' => $opis['size'],
                'height' => $opis['size'],
                'products' => (int) in_array('products', $opis['scopes'], true),
                'categories' => (int) in_array('categories', $opis['scopes'], true),
                'manufacturers' => (int) in_array('manufacturers', $opis['scopes'], true),
                'suppliers' => (int) in_array('suppliers', $opis['scopes'], true),
                'stores' => 0,
            ];

            $db->insert('image_type', $wiersz);
        }

        $this->setEverywhere('PS_IMAGE_FORMAT', $this->recommendedFormats((string) Configuration::get('PS_IMAGE_FORMAT')));
        $this->setEverywhere('PS_IMAGE_QUALITY', self::BASE_FORMAT);
    }

    /**
     * Formaty do wygenerowania: JPEG i WebP (gdy serwer umie WebP). AVIF
     * zostaje, jeśli sklep go już włączył.
     */
    private function recommendedFormats(string $obecne): string
    {
        $lista = ['jpg'];

        if ($this->webpSupported()) {
            $lista[] = 'webp';
        }

        if (in_array('avif', explode(',', $obecne), true)) {
            $lista[] = 'avif';
        }

        return implode(',', $lista);
    }

    private function sameList(string $a, string $b): bool
    {
        $x = array_filter(explode(',', $a));
        $y = array_filter(explode(',', $b));
        sort($x);
        sort($y);

        return $x === $y;
    }

    private function webpSupported(): bool
    {
        return function_exists('imagewebp');
    }

    /** @return array<string,array{width:int,height:int}> */
    private function currentTypes(): array
    {
        $wynik = [];
        $wiersze = Db::getInstance()->executeS(
            'SELECT `name`, `width`, `height` FROM `' . _DB_PREFIX_ . 'image_type`'
        ) ?: [];

        foreach ($wiersze as $w) {
            $wynik[(string) $w['name']] = ['width' => (int) $w['width'], 'height' => (int) $w['height']];
        }

        return $wynik;
    }

    /**
     * Ustawienia zdjęć są jedne dla całej instalacji (typy obrazu nie mają
     * podziału na sklepy), więc zapisujemy wiersz globalny i każdy wiersz sklepu,
     * który już istnieje. Inaczej sklep z własnym wierszem zostałby przy starym.
     */
    private function setEverywhere(string $klucz, string $wartosc): void
    {
        Configuration::updateGlobalValue($klucz, $wartosc);

        foreach (Shop::getShops(false, null, true) as $idShop) {
            if (Configuration::hasKey($klucz, null, null, (int) $idShop)) {
                Configuration::updateValue($klucz, $wartosc, false, null, (int) $idShop);
            }
        }
    }
}
