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
use Quince\Engine\Feature\FeatureRegistry;
use Quince\Engine\Ui\IconLibrary;

/**
 * Ustawienia nagłówka: logo, wyszukiwanie, akcje klienta.
 *
 * Kolorów tu nie ma — wylicza je silnik z decyzji „pas jasny / ciemny / w kolorze
 * marki" w ustawieniach ogólnych. Tutaj jest wyłącznie to, co sklep pokazuje
 * i czego nie pokazuje.
 *
 * Wybór wyszukiwarki przechodzi przez rejestr możliwości: lista zawiera wyłącznie
 * to, co faktycznie jest zainstalowane, a wybór wskazujący moduł, którego już nie
 * ma, wraca na wyszukiwarkę wbudowaną zamiast zostawiać sklep bez szukania.
 */
final class HeaderSettings
{
    private const PREFIX = 'QUINCE_HEADER_';

    /** Klucze wielojęzyczne — wartość trzymana jako JSON `{"1":"…","2":"…"}`. */
    private const TRANSLATABLE = ['SEARCH_PLACEHOLDER', 'ACCOUNT_LABEL', 'WISHLIST_LABEL', 'CART_LABEL'];

    /**
     * Obróbka logo w przyklejonym nagłówku.
     *
     * Odwrócenie zamienia ciemne na jasne i zostawia barwy — dobre dla logo
     * kolorowego. „Na biało" i „na czarno" spłaszczają je do jednej barwy:
     * kiedy odwrócenie daje kolory, których w marce nie ma.
     *
     * @var array<string,string>
     */
    private const LOGO_FILTERS = [
        'none' => 'none',
        'invert' => 'invert(1)',
        'white' => 'brightness(0) invert(1)',
        'black' => 'brightness(0)',
    ];

    /** @var array<string,string> klucz => wartość domyślna */
    private const SCHEMA = [
        'LOGO_HEIGHT' => '40',

        'SEARCH_ENABLED' => '1',
        'SEARCH_ICON' => 'search',
        'SEARCH_PROVIDER' => FeatureRegistry::BUILT_IN,
        'SEARCH_PLACEHOLDER' => '',
        'SEARCH_SCOPE' => '1',

        'ICON_LABELS' => '1',
        'ACCOUNT_VISIBLE' => '1',
        'ACCOUNT_DROPDOWN' => '1',
        'ACCOUNT_ICON' => 'person',
        'ACCOUNT_LABEL' => '',
        'WISHLIST_VISIBLE' => '1',
        // Akcje z modułów (dziś: powiadomienia). Bez zainstalowanego modułu
        // ustawienie nie ma czego pokazać i nie stoi w panelu.
        'MODULE_ACTIONS' => '1',
        'WISHLIST_ICON' => 'heart',
        'WISHLIST_LABEL' => '',
        'CART_TOTAL' => '1',
        'CART_ICON' => 'bag',
        'CART_LABEL' => '',

        'STICKY' => '1',
        // Tło pasa: stopień skali albo kod barwy. Nagłówek przyklejony ma własne,
        // bo leży wtedy na treści i bywa, że musi się od niej odciąć mocniej.
        'BG' => 'neutral-1',
        'BG_STUCK' => 'neutral-1',
        // Obróbka logo w przyklejonym nagłówku: none | invert | white | black.
        'LOGO_STUCK' => 'none',

        // Po przewinięciu nagłówek jest ciaśniejszy: dochodzi w nim przycisk
        // katalogu, a pola i akcje zostają te same. Podpisy akcji znikają wtedy
        // pierwsze — po to, żeby nie ubywało pola wyszukiwania.
        'SCROLL_ICONS' => '1',
    ];

    private FeatureRegistry $registry;

    public function __construct(?FeatureRegistry $registry = null)
    {
        $this->registry = $registry ?? new FeatureRegistry();
    }

    /**
     * Role obu stanów pasa nagłówka, gotowe do wypisania jako tokeny żądania.
     *
     * @param array<string,string> $palette
     *
     * @return array<string,string>
     */
    public function bandTokens(array $palette): array
    {
        $generator = new \Quince\Engine\Appearance\PaletteGenerator();
        $settings = $this->all();
        $out = [];

        // Obróbka logo idzie tą samą drogą co barwy pasa: token wypisywany przy
        // żądaniu. Wartości są zamknięte, więc do CSS nie trafia nic z zewnątrz.
        $out['header-logo-stuck-filter'] = self::LOGO_FILTERS[$settings['LOGO_STUCK']]
            ?? self::LOGO_FILTERS['none'];

        foreach (['header' => 'BG', 'header-stuck' => 'BG_STUCK'] as $pas => $klucz) {
            $wybor = $settings[$klucz];

            $tlo = str_starts_with($wybor, '#')
                ? $wybor
                : ($palette[$wybor] ?? $palette[self::SCHEMA[$klucz]] ?? null);

            if ($tlo === null) {
                continue;
            }

            foreach ($generator->bandFromBackground($tlo, $palette) as $rola => $barwa) {
                $out['band-' . $pas . '-' . $rola] = $barwa;
            }
        }

        return $out;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];

        foreach (self::SCHEMA as $key => $default) {
            $value = Configuration::get(self::PREFIX . $key);
            $out[$key] = ($value === false || $value === null) ? $default : (string) $value;
        }

        return $out;
    }

    /**
     * Dane gotowe do wstawienia w szablon: treści rozwiązane dla języka, wybory
     * sprowadzone do wartości logicznych. Szablon nie ma tu nic do policzenia.
     *
     * @return array<string,mixed>
     */
    public function forTemplate(int $idLang): array
    {
        $s = $this->all();

        return [
            'logo_height' => (int) $s['LOGO_HEIGHT'],
            'sticky' => $s['STICKY'] === '1',
            'scroll_icons' => $s['SCROLL_ICONS'] === '1',
            'icon_labels' => $s['ICON_LABELS'] === '1',

            'search' => [
                'enabled' => $s['SEARCH_ENABLED'] === '1',
                'icon' => IconLibrary::svg($s['SEARCH_ICON']),
                // Sprawdzony wybór, nie zapisany: moduł mógł zostać odinstalowany.
                'provider' => $this->registry->resolve(FeatureRegistry::TYPE_SEARCH, $s['SEARCH_PROVIDER']),
                'placeholder' => $this->translate($s['SEARCH_PLACEHOLDER'], $idLang),
                'scope' => $s['SEARCH_SCOPE'] === '1',
            ],

            // Podpisy: pusty oznacza „zostaw domyślny", a domyślny zna szablon,
            // bo to on ma tłumaczenia. Silnik nie zgaduje za sprzedawcę nazwy,
            // której ten nie podał.
            'account' => [
                'visible' => $s['ACCOUNT_VISIBLE'] === '1',
                'dropdown' => $s['ACCOUNT_DROPDOWN'] === '1',
                'icon' => IconLibrary::svg($s['ACCOUNT_ICON']),
                'label' => $this->translate($s['ACCOUNT_LABEL'], $idLang),
            ],

            // Ikona listy życzeń pojawia się dopiero razem z czymś, co tę listę
            // prowadzi. Bez tego byłby to odnośnik donikąd, a ustawienie w panelu
            // mówi wprost, czego brakuje.
            'module_actions' => $s['MODULE_ACTIONS'] === '1' && $this->moduleActionsAvailable(),

            'wishlist' => [
                'visible' => $s['WISHLIST_VISIBLE'] === '1' && $this->wishlistAvailable(),
                'icon' => IconLibrary::svg($s['WISHLIST_ICON']),
                'label' => $this->translate($s['WISHLIST_LABEL'], $idLang),
                'url' => $this->wishlistUrl(),
                'count' => $this->wishlistCount(),
            ],

            'cart' => [
                'total' => $s['CART_TOTAL'] === '1',
                'icon' => IconLibrary::svg($s['CART_ICON']),
                'label' => $this->translate($s['CART_LABEL'], $idLang),
            ],
        ];
    }

    /**
     * Dostawcy wyszukiwania do wyboru w panelu.
     *
     * @return array<string,array{id:string,label:string,route:?string,module:string}>
     */
    public function searchProviders(): array
    {
        return $this->registry->providersFor(FeatureRegistry::TYPE_SEARCH);
    }

    /** Czy zapisany wybór wskazuje moduł, którego już nie ma. Panel robi z tego ostrzeżenie. */
    public function searchProviderMissing(): bool
    {
        return $this->registry->isMissing(FeatureRegistry::TYPE_SEARCH, $this->all()['SEARCH_PROVIDER']);
    }

    /**
     * Czy cokolwiek prowadzi listę życzeń. Rejestr zawsze wystawia pozycję
     * wbudowaną, więc sama lista nigdy nie jest pusta — liczy się to, czy stoi
     * w niej cokolwiek poza nią.
     */
    /**
     * Czy jakikolwiek moduł zgłosił akcję do nagłówka.
     *
     * Ta sama zasada, co przy liście życzeń: ustawienie nie może oferować czegoś,
     * czego nie da się spełnić. Bez modułu przełącznik nie pojawia się w panelu,
     * a miejsce w nagłówku zostaje puste — czyli nagłówek wygląda jak dotąd.
     */
    public function moduleActionsAvailable(): bool
    {
        // Rejestr zawsze zwraca rozwiązanie wbudowane — dostawcą, który coś naprawdę
        // przynosi, jest dopiero drugi wpis. Tak samo liczy to lista życzeń.
        return count($this->registry->providersFor(FeatureRegistry::TYPE_NOTIFICATIONS)) > 1;
    }

    public function wishlistAvailable(): bool
    {
        return count($this->registry->providersFor(FeatureRegistry::TYPE_WISHLIST)) > 1;
    }

    /**
     * Ile produktów klient ma zapisanych.
     *
     * Liczymy w tabelach modułu prowadzącego listy, bo tylko on je zna. Zero
     * zwracamy jako zero i szablon go nie pokazuje: pusty krążek przy sercu
     * mówiłby „masz zero", zamiast nie mówić nic.
     */
    private function wishlistCount(): int
    {
        $context = \Context::getContext();

        if ($context === null || $context->customer === null || !$context->customer->isLogged()) {
            return 0;
        }

        $sql = 'SELECT COUNT(p.id_product) FROM ' . _DB_PREFIX_ . 'wishlist w'
            . ' INNER JOIN ' . _DB_PREFIX_ . 'wishlist_product p ON p.id_wishlist = w.id_wishlist'
            . ' WHERE w.id_customer = ' . (int) $context->customer->id;

        try {
            return (int) \Db::getInstance()->getValue($sql);
        } catch (\Throwable) {
            // Brak tabel znaczy brak modułu list — ikona i tak wtedy nie stoi w pasie.
            return 0;
        }
    }

    /**
     * Adres strony z listami życzeń.
     *
     * Listę prowadzi blockwishlist — jego tabele i model zostają, podmieniamy
     * dopiero warstwę widoczną, bo jego własny frontend jest aplikacją Vue,
     * której nie da się czysto ostylować. Dopóki nasze szablony nie stoją na
     * miejscu, odnośnik prowadzi na jego stronę: działającą, choć nie naszą.
     */
    private function wishlistUrl(): string
    {
        $context = \Context::getContext();

        if ($context === null || $context->link === null) {
            return '';
        }

        return (string) $context->link->getModuleLink('blockwishlist', 'lists');
    }

    /**
     * Kod SVG wybranych ikon — dla selektora w panelu, żeby rysował je od razu,
     * zanim ktokolwiek otworzy listę.
     *
     * @return array<string,string>
     */
    public function iconSvg(): array
    {
        $s = $this->all();
        $out = [];

        foreach (['SEARCH_ICON', 'ACCOUNT_ICON', 'WISHLIST_ICON', 'CART_ICON'] as $key) {
            $out[$key] = IconLibrary::svg($s[$key]);
        }

        return $out;
    }

    /** @return array<string,array<int,string>> pola wielojęzyczne rozpakowane dla formularza */
    public function translations(): array
    {
        $all = $this->all();
        $out = [];

        foreach (self::TRANSLATABLE as $key) {
            $out[$key] = $this->decode($all[$key]);
        }

        return $out;
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        foreach (self::SCHEMA as $key => $default) {
            if (in_array($key, self::TRANSLATABLE, true) || !array_key_exists($key, $values)) {
                continue;
            }

            ConfigStore::set(self::PREFIX . $key, (string) $values[$key]);
        }

        // Wysokość logo w rozsądnych granicach: poniżej 24 px znak firmowy
        // przestaje być czytelny, powyżej 72 nagłówek zaczyna zjadać ekran.
        ConfigStore::set(
            self::PREFIX . 'LOGO_HEIGHT',
            (string) max(24, min(72, (int) ($values['LOGO_HEIGHT'] ?? 40)))
        );

        foreach (self::TRANSLATABLE as $key) {
            $collected = [];

            foreach ($values as $name => $value) {
                if (preg_match('/^' . $key . '_(\d+)$/', (string) $name, $m)) {
                    $collected[(int) $m[1]] = (string) $value;
                }
            }

            if ($collected) {
                // isHtml = true, bo bez tego PrestaShop wycina cudzysłowy
                // i zapisany JSON przestaje być JSON-em.
                ConfigStore::set(self::PREFIX . $key, (string) json_encode($collected), true);
            }
        }
    }

    /** @return array<string,string> */
    public function defaults(): array
    {
        return self::SCHEMA;
    }

    /** @return string[] */
    public static function configurationKeys(): array
    {
        return array_map(static fn (string $k): string => self::PREFIX . $k, array_keys(self::SCHEMA));
    }

    // ------------------------------------------------------------------ pomocnicze

    /** @return array<int,string> */
    private function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Wartość sprzed zmiany pola na wielojęzyczne: zwykły tekst zamiast JSON-a.
        if (trim($raw) !== '') {
            return [(int) Configuration::get('PS_LANG_DEFAULT') => $raw];
        }

        return [];
    }

    private function translate(string $raw, int $idLang): string
    {
        $values = $this->decode($raw);

        if (isset($values[$idLang]) && trim($values[$idLang]) !== '') {
            return $values[$idLang];
        }

        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return '';
    }
}
