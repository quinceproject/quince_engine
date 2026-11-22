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
     * Wszystkie typy zdjęć motywu: rozmiar w pikselach i to, czego dotyczą
     * (zdjęcia produktów, kategorii, logo marek i dostawców, zdjęcia sklepów).
     * Ten sam zestaw co `image_types` w `config/theme.yml` motywu.
     *
     * `home_default` i `medium_default` to wartości dla strony 1200 px i 4 kolumn;
     * faktyczne liczy `sizes()` z ustawień wyglądu. Pozostałe są stałe: miniatury
     * nie zależą od szerokości strony, a powiększenie po kliknięciu od wysokości
     * ekranu klienta (ok. 840 px, w dwukrotnej gęstości 1600).
     *
     * @var array<string,array{width:int,height:int,scopes:string[]}>
     */
    private const SIZES = [
        // Miniatury galerii, koszyk, logo marek i dostawców, kategorie w menu.
        'small_default' => ['width' => 200, 'height' => 200, 'scopes' => ['products', 'categories', 'manufacturers', 'suppliers']],
        // Szuflada koszyka.
        'cart_default' => ['width' => 250, 'height' => 250, 'scopes' => ['products']],
        // Zajawki.
        'home_default' => ['width' => 300, 'height' => 300, 'scopes' => ['products']],
        // Zdjęcie na karcie produktu, zajawki w dwukrotnej gęstości, logo marek.
        'medium_default' => ['width' => 800, 'height' => 800, 'scopes' => ['products', 'manufacturers', 'suppliers']],
        // Powiększenie, logo w nagłówku strony marki.
        'large_default' => ['width' => 1600, 'height' => 1600, 'scopes' => ['products', 'manufacturers', 'suppliers']],
        // Zdjęcie kategorii w nagłówku, kafelki podkategorii.
        'category_default' => ['width' => 300, 'height' => 300, 'scopes' => ['categories']],
        // Zdjęcia sklepów stacjonarnych na stronie „Nasze sklepy”.
        'stores_default' => ['width' => 287, 'height' => 160, 'scopes' => ['stores']],
    ];

    /** Kolumny zakresów w `image_type`. */
    private const SCOPES = ['products', 'categories', 'manufacturers', 'suppliers', 'stores'];

    /** Odstęp między zajawkami w siatce motywu, w pikselach. */
    private const GRID_GAP = 24;

    /**
     * Udział zdjęcia głównego w szerokości strony na karcie produktu w układzie
     * motywu Quince (zmierzone: 455 px przy stronie 1520 px).
     */
    private const GALLERY_SHARE = 0.30;

    /**
     * Rozmiary dopasowane do szerokości strony i liczby kolumn listingu.
     *
     * Zajawka (`home_default`) w zwykłej gęstości: szerokość jednej kolumny siatki.
     * Zdjęcie na karcie produktu i zajawka w dwukrotnej gęstości (`medium_default`):
     * większa z tych dwóch potrzeb, razy dwa. Zaokrąglamy w górę, żeby obrazek nie
     * był rozciągany o kilka pikseli. `medium` nigdy nie przerasta `large`.
     *
     * @return array<string,array{width:int,height:int,scopes:string[]}>
     */
    public function sizes(): array
    {
        ['width' => $strona, 'columns' => $kolumny] = $this->basis();

        $zajawka = ($strona - ($kolumny - 1) * self::GRID_GAP) / $kolumny;
        $galeria = $strona * self::GALLERY_SHARE;

        $home = (int) (ceil($zajawka / 50) * 50);
        $medium = min(
            self::SIZES['large_default']['width'],
            (int) (ceil(max(2 * $zajawka, 2 * $galeria) / 100) * 100)
        );

        $rozmiary = self::SIZES;
        $rozmiary['home_default']['width'] = $rozmiary['home_default']['height'] = $home;
        $rozmiary['medium_default']['width'] = $rozmiary['medium_default']['height'] = $medium;

        return $rozmiary;
    }

    /**
     * Z czego liczymy rozmiary: szerokość strony i kolumny listingu z ustawień Wyglądu.
     *
     * Czytamy oba klucze wprost, z tymi samymi granicami co Wygląd (960-1920 px,
     * 3-5 kolumn), jak robią to ustawienia listingu: do dwóch liczb nie trzeba
     * budować całego zestawu ustawień wyglądu z jego zależnościami.
     *
     * @return array{width:int,columns:int}
     */
    public function basis(): array
    {
        $szerokosc = (int) Configuration::get('QUINCE_CONTAINER_WIDTH');
        $kolumny = (int) Configuration::get('QUINCE_GRID_COLUMNS');

        return [
            'width' => $szerokosc > 0 ? max(960, min(1920, $szerokosc)) : 1200,
            'columns' => in_array($kolumny, [3, 4, 5], true) ? $kolumny : 4,
        ];
    }

    /** Plik podstawowy JPEG; PNG tylko tam, gdzie sklep wybrał „PNG dla wszystkich”. */
    private const BASE_FORMAT = 'jpg';

    /**
     * Stan sklepu obok zestawu, dla ekranu Narzędzia.
     *
     * @return array{types: array<int,array{name:string,current:?string,recommended:string,currentScopes:string[],scopes:string[],missingScopes:string[],ok:bool}>, formats: array{current:string,recommended:string,ok:bool}, base: array{current:string,recommended:string,ok:bool}, webp:bool, ok:bool}
     */
    public function status(): array
    {
        $obecne = $this->currentTypes();
        $typy = [];
        $wszystkoOk = true;

        foreach ($this->sizes() as $nazwa => $opis) {
            $jest = $obecne[$nazwa] ?? null;
            $teraz = $jest !== null ? $jest['width'] . ' × ' . $jest['height'] : null;
            $rozmiarOk = $jest !== null && $jest['width'] === $opis['width'] && $jest['height'] === $opis['height'];

            // Zakresy: typ musi obejmować to, do czego motyw go używa. Zakresów,
            // które sklep dołożył sam, nie liczymy jako błędu i nie zdejmujemy.
            $brakZakresow = $jest !== null
                ? array_values(array_filter($opis['scopes'], static fn (string $z): bool => empty($jest['scopes'][$z])))
                : $opis['scopes'];
            $ok = $rozmiarOk && $brakZakresow === [];
            $wszystkoOk = $wszystkoOk && $ok;

            $typy[] = [
                'name' => $nazwa,
                'current' => $teraz,
                'recommended' => $opis['width'] . ' × ' . $opis['height'],
                'currentScopes' => $jest !== null ? array_keys(array_filter($jest['scopes'])) : [],
                'scopes' => $opis['scopes'],
                'missingScopes' => $brakZakresow,
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

        foreach ($this->sizes() as $nazwa => $opis) {
            if (isset($obecne[$nazwa])) {
                // Dokładamy brakujące zakresy, a te, które sklep ma ponad zestaw,
                // zostawiamy: mogą ich używać moduły albo inny motyw.
                $zmiana = ['width' => $opis['width'], 'height' => $opis['height']];

                foreach ($opis['scopes'] as $zakres) {
                    $zmiana[$zakres] = 1;
                }

                $db->update('image_type', $zmiana, '`name` = \'' . pSQL($nazwa) . '\'');

                continue;
            }

            $wiersz = [
                'name' => pSQL($nazwa),
                'width' => $opis['width'],
                'height' => $opis['height'],
            ];

            foreach (self::SCOPES as $zakres) {
                $wiersz[$zakres] = (int) in_array($zakres, $opis['scopes'], true);
            }

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
            'SELECT * FROM `' . _DB_PREFIX_ . 'image_type`'
        ) ?: [];

        foreach ($wiersze as $w) {
            $zakresy = [];

            foreach (self::SCOPES as $zakres) {
                $zakresy[$zakres] = !empty($w[$zakres]);
            }

            $wynik[(string) $w['name']] = ['width' => (int) $w['width'], 'height' => (int) $w['height'], 'scopes' => $zakresy];
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
