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
use Quince\Engine\Catalog\CategoryTree;
use Quince\Engine\Catalog\CategoryTreeCache;
use Quince\Engine\Feature\FeatureRegistry;
use Quince\Engine\Ui\IconLibrary;

/**
 * Ustawienia paska nawigacji: kategorie i odnośniki własne.
 *
 * Pasek i odnośniki własne należą do motywu — działają bez żadnego modułu.
 * Moduł menu może przez rejestr podmienić samo źródło kategorii, nie cały pasek.
 *
 * Kategorie stoją zawsze pod jednym przyciskiem — sklep wybiera wyłącznie kształt
 * ich rozwinięcia: kolumny, kafelki albo kaskadę. Sklep z płaskim katalogiem weźmie
 * kafelki, z głębokim — kaskadę.
 */
final class NavigationSettings
{
    private const PREFIX = 'QUINCE_NAV_';

    private const TRANSLATABLE = ['TRIGGER_TEXT', 'SCROLL_TEXT'];

    /** Rozwinięcie: kolumna z podkategoriami, kafelki ze zdjęciem, kaskada w prawo. */
    public const PANELS = ['columns', 'tiles', 'cascade'];

    /**
     * Przycisk katalogu w przyklejonym nagłówku: cały, sama ikona albo ikona
     * z krótszą nazwą.
     *
     * Wybór jest realny, bo miejsce w nagłówku jest policzone: nazwa katalogu
     * zajmuje tam tyle samo, co pole wyszukiwania na wąskim ekranie. Sklep
     * z krótką nazwą weźmie całą, sklep z długą — skróconą albo samą ikonę.
     */
    public const SCROLL_BUTTONS = ['full', 'icon', 'short'];

    /** Układ kafelków na telefonie: wiersz z obrazkiem po lewej albo dwie kolumny. */
    public const TILE_MOBILE = ['list', 'grid'];

    /** Styl odnośnika własnego: zwykły albo wyróżniony barwą.
     *
     * Kropka wypadła: robiła to samo, co ikona, tylko gorzej — nie mówiła nic
     * o treści odnośnika. Kto chce wyróżnić pozycję, stawia przy niej ikonę.
     */
    public const LINK_STYLES = ['normal', 'accent'];

    /** @var array<string,string> */
    private const SCHEMA = [
        'ENABLED' => '1',
        // Tło pasa i tło przycisku kategorii: stopień skali albo kod barwy.
        'BG' => 'neutral-12',
        'TRIGGER_BG' => 'primary-9',

        'MENU_PROVIDER' => FeatureRegistry::BUILT_IN,
        'TRIGGER_TEXT' => '',
        'TRIGGER_ICON' => 'list',

        'SCROLL_BUTTON' => 'full',
        'SCROLL_TEXT' => '',

        'PANEL' => 'columns',
        'DEPTH' => '2',
        'COLUMNS' => '3',
        'TILE_COLUMNS' => '5',
        'TILE_ICON_MODE' => '0',
        // Układ kafelków na telefonie: list (wiersz z obrazkiem po lewej)
        // albo grid (dwie kolumny, obrazek nad nazwą).
        'TILE_MOBILE' => 'list',

        'CATEGORIES' => '',
        'LINKS' => '',
    ];

    private FeatureRegistry $registry;

    public function __construct(?FeatureRegistry $registry = null)
    {
        $this->registry = $registry ?? new FeatureRegistry();
    }

    /**
     * Role pasa menu i przycisku kategorii, gotowe do wypisania jako tokeny.
     *
     * Przycisk dostaje własne wypełnienie, bo to on jest wejściem do katalogu —
     * pas może być ciemny, a przycisk w barwie marki albo odwrotnie. Napis
     * i stan pod kursorem liczą się z wybranego tła, nie z barwy marki.
     *
     * @param array<string,string> $palette
     *
     * @return array<string,string>
     */
    public function bandTokens(array $palette): array
    {
        $generator = new \Quince\Engine\Appearance\PaletteGenerator();
        $settings = $this->all();

        $barwa = static function (string $wybor, string $domyslny) use ($palette): ?string {
            if (str_starts_with($wybor, '#')) {
                return $wybor;
            }

            return $palette[$wybor] ?? $palette[$domyslny] ?? null;
        };

        $tlo = $barwa($settings['BG'], self::SCHEMA['BG']);
        $out = [];

        if ($tlo !== null) {
            foreach ($generator->bandFromBackground($tlo, $palette) as $rola => $wartosc) {
                $out['band-nav-' . $rola] = $wartosc;
            }
        }

        $przycisk = $barwa($settings['TRIGGER_BG'], self::SCHEMA['TRIGGER_BG']);

        if ($przycisk !== null) {
            $out['band-nav-solid'] = $przycisk;
            $out['band-nav-on-solid'] = \Quince\Engine\Appearance\Color::readableOn($przycisk);
            $out['band-nav-solid-hover'] = \Quince\Engine\Appearance\Color::shiftTone(
                $przycisk,
                \Quince\Engine\Appearance\Color::tone($przycisk) > 0.5 ? -0.06 : 0.06
            );
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
     * Dane gotowe dla szablonu.
     *
     * @return array<string,mixed>
     */
    public function forTemplate(int $idLang): array
    {
        $s = $this->all();

        $panel = in_array($s['PANEL'], self::PANELS, true) ? $s['PANEL'] : 'columns';

        return [
            'enabled' => $s['ENABLED'] === '1',
            'provider' => $this->registry->resolve(FeatureRegistry::TYPE_MENU, $s['MENU_PROVIDER']),
            'panel' => $panel,

            'trigger' => [
                'text' => $this->translate($s['TRIGGER_TEXT'], $idLang),
                'icon' => IconLibrary::svg($s['TRIGGER_ICON']),
            ],

            // Ten sam przycisk, drugie miejsce: nagłówek, który został na ekranie
            // po odjechaniu pasa menu. Krótsza nazwa jest osobnym tekstem, a nie
            // przycięciem tamtego — „Wszystkie kategorie" ucięte w połowie nie
            // znaczy nic, a „Katalog" znaczy to samo w jednym słowie.
            'scroll' => [
                'button' => in_array($s['SCROLL_BUTTON'], self::SCROLL_BUTTONS, true) ? $s['SCROLL_BUTTON'] : 'full',
                'text' => $this->translate($s['SCROLL_TEXT'], $idLang),
            ],

            // Kafelki są jednopoziomowe z założenia: kafelek ze zdjęciem jest
            // zajawką kategorii, a nie węzłem drzewa.
            // Kafelek jest zajawką kategorii, nie węzłem drzewa — stąd jeden poziom.
            'depth' => $panel === 'tiles' ? 1 : max(1, min(3, (int) $s['DEPTH'])),
            'columns' => max(2, min(6, (int) $s['COLUMNS'])),
            'tile_columns' => max(2, min(8, (int) $s['TILE_COLUMNS'])),
            'tile_icon_mode' => $s['TILE_ICON_MODE'] === '1',
            'tile_mobile' => $s['TILE_MOBILE'] === 'grid' ? 'grid' : 'list',

            // Gotowe gałęzie, nie same numery: szablon ma je wypisać, a nie
            // dopytywać bazę w pętli po każdej pozycji menu.
            'branches' => $this->branchesWithIcons(
                $panel === 'tiles' ? 1 : max(1, min(3, (int) $s['DEPTH'])),
                $idLang
            ),
            'links' => $this->linksFor($idLang),
        ];
    }

    /**
     * Kategorie główne pokazywane w menu, w kolejności ustawionej przez sprzedawcę.
     *
     * Pusto znaczy wszystkie — sklep, który nic nie wybrał, ma działające menu,
     * a nie puste. Kolejność bierze się z tablicy, nie z katalogu: w menu układa
     * się je według tego, co sprzedaje, a nie według tego, co ustawiono w drzewie
     * kategorii dla całego sklepu.
     *
     * Zapis sprzed rozszerzenia to była lista numerów. Czytamy oba kształty, bo
     * sklep zaktualizowany ze starszej wersji ma w bazie tamten i nie ma powodu
     * kazać mu ustawiać menu od nowa.
     *
     * @return array<int,array{id:int,icon:string}>
     */
    public function categories(): array
    {
        $stored = json_decode($this->all()['CATEGORIES'], true);
        $out = [];

        foreach (is_array($stored) ? $stored : [] as $entry) {
            if (is_array($entry)) {
                $id = (int) ($entry['id'] ?? 0);
                $icon = (string) ($entry['icon'] ?? '');
            } else {
                $id = (int) $entry;
                $icon = '';
            }

            if ($id > 0) {
                $out[] = ['id' => $id, 'icon' => $icon];
            }
        }

        return $out;
    }

    /**
     * Same numery wybranych kategorii — do zaznaczenia pozycji w panelu.
     *
     * @return array<int,int>
     */
    public function categoryIds(): array
    {
        return array_column($this->categories(), 'id');
    }

    /**
     * Gałęzie z ikonami zamienionymi na kod SVG. Szablon dostaje gotowy rysunek,
     * a nie nazwę pliku, której i tak nie umiałby otworzyć.
     *
     * @return array<int,array<string,mixed>>
     */
    private function branchesWithIcons(int $depth, int $idLang): array
    {
        $chosen = $this->categories();

        // Klucz obejmuje wszystko, co zmienia wynik: język, głębokość i wybór
        // kategorii razem z ikonami. Zmiana któregokolwiek daje inny zapis,
        // więc stary nie ma jak przeżyć zmiany ustawień.
        $key = $idLang . '|' . $depth . '|' . json_encode($chosen);

        $branches = (new CategoryTreeCache())->remember(
            $key,
            fn (): array => (new CategoryTree())->branches($chosen, $depth, $idLang)
        );

        foreach ($branches as $i => $branch) {
            $branches[$i]['icon'] = $branch['icon'] !== '' ? IconLibrary::svg($branch['icon']) : '';
        }

        return $branches;
    }

    /**
     * Odnośniki własne w kolejności ustawionej przez sprzedawcę.
     *
     * Adres jest wielojęzyczny tak samo jak nazwa: sklep prowadzony w dwóch
     * językach ma dwie strony promocji, dwa regulaminy i dwa blogi, więc jeden
     * adres dla wszystkich odsyłałby część klientów na obcą wersję.
     *
     * Zapis sprzed tej zmiany miał jeden adres — czytamy go i traktujemy jako
     * wspólny dla wszystkich języków, żeby nikt nie musiał wpisywać menu od nowa.
     *
     * @return array<int,array{labels:array<int,string>,urls:array<int,string>,icon:string,style:string}>
     */
    public function links(): array
    {
        $stored = json_decode($this->all()['LINKS'], true);
        $out = [];

        foreach (is_array($stored) ? $stored : [] as $link) {
            if (!is_array($link)) {
                continue;
            }

            $style = (string) ($link['style'] ?? 'normal');

            if (isset($link['urls']) && is_array($link['urls'])) {
                $urls = array_map('strval', $link['urls']);
            } else {
                $wspolny = (string) ($link['url'] ?? '');
                $urls = $wspolny !== '' ? [(int) Configuration::get('PS_LANG_DEFAULT') => $wspolny] : [];
            }

            $out[] = [
                'labels' => isset($link['labels']) && is_array($link['labels']) ? $link['labels'] : [],
                'urls' => $urls,
                'icon' => (string) ($link['icon'] ?? ''),
                'style' => in_array($style, self::LINK_STYLES, true) ? $style : 'normal',
            ];
        }

        return $out;
    }

    /**
     * Odnośniki gotowe dla sklepu: nazwa rozwiązana dla języka, ikona jako SVG.
     * Odnośnik bez nazwy albo bez adresu wypada — pusta pozycja w pasku to wada,
     * nie decyzja.
     *
     * @return array<int,array{label:string,url:string,icon:string,style:string}>
     */
    public function linksFor(int $idLang): array
    {
        $out = [];

        foreach ($this->links() as $link) {
            $label = $this->translateArray($link['labels'], $idLang);
            $url = $this->translateArray($link['urls'], $idLang);

            if ($label === '' || $url === '') {
                continue;
            }

            $out[] = [
                'label' => $label,
                'url' => $url,
                'icon' => IconLibrary::svg($link['icon']),
                'style' => $link['style'],
            ];
        }

        return $out;
    }

    /** @return array<string,array{id:string,label:string,route:?string,module:string}> */
    public function menuProviders(): array
    {
        return $this->registry->providersFor(FeatureRegistry::TYPE_MENU);
    }

    public function menuProviderMissing(): bool
    {
        return $this->registry->isMissing(FeatureRegistry::TYPE_MENU, $this->all()['MENU_PROVIDER']);
    }

    /** @return array<string,array<int,string>> */
    public function translations(): array
    {
        $all = $this->all();
        $out = [];

        foreach (self::TRANSLATABLE as $key) {
            $out[$key] = $this->decode($all[$key]);
        }

        return $out;
    }

    /** Kod SVG wybranych ikon — dla selektorów w panelu. */
    public function triggerIconSvg(): string
    {
        return IconLibrary::svg($this->all()['TRIGGER_ICON']);
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        // Tła pasa i przycisku: stopień skali albo kod barwy — sprawdza je silnik
        // przy wypisywaniu tokenów, więc tutaj przepisujemy je bez zgadywania.
        foreach (['ENABLED', 'MENU_PROVIDER', 'TRIGGER_ICON', 'TILE_ICON_MODE',
                  'BG', 'TRIGGER_BG'] as $key) {
            if (array_key_exists($key, $values)) {
                ConfigStore::set(self::PREFIX . $key, (string) $values[$key]);
            }
        }

        $this->saveChoice($values, 'PANEL', self::PANELS, 'columns');
        $this->saveChoice($values, 'SCROLL_BUTTON', self::SCROLL_BUTTONS, 'full');
        $this->saveChoice($values, 'TILE_MOBILE', self::TILE_MOBILE, 'list');

        ConfigStore::set(self::PREFIX . 'DEPTH', (string) max(1, min(3, (int) ($values['DEPTH'] ?? 2))));
        ConfigStore::set(self::PREFIX . 'COLUMNS', (string) max(2, min(6, (int) ($values['COLUMNS'] ?? 3))));
        ConfigStore::set(self::PREFIX . 'TILE_COLUMNS', (string) max(2, min(8, (int) ($values['TILE_COLUMNS'] ?? 5))));

        ConfigStore::set(self::PREFIX . 'CATEGORIES', (string) json_encode($this->readCategories($values)), true);

        ConfigStore::set(self::PREFIX . 'LINKS', (string) json_encode($this->readLinks($values)), true);

        foreach (self::TRANSLATABLE as $key) {
            $collected = [];

            foreach ($values as $name => $value) {
                if (preg_match('/^' . $key . '_(\d+)$/', (string) $name, $m)) {
                    $collected[(int) $m[1]] = (string) $value;
                }
            }

            if ($collected) {
                ConfigStore::set(self::PREFIX . $key, (string) json_encode($collected), true);
            }
        }
    }

    /** @return string[] */
    public static function configurationKeys(): array
    {
        return array_map(static fn (string $k): string => self::PREFIX . $k, array_keys(self::SCHEMA));
    }

    // ------------------------------------------------------------------ pomocnicze

    /**
     * @param array<string,mixed> $values
     * @param string[]            $allowed
     */
    private function saveChoice(array $values, string $key, array $allowed, string $default): void
    {
        $value = (string) ($values[$key] ?? $default);
        ConfigStore::set(self::PREFIX . $key, in_array($value, $allowed, true) ? $value : $default);
    }

    /**
     * Kategorie z formularza: wyłącznie zaznaczone, w kolejności, w jakiej przyszły,
     * bo tę kolejność ustawił sprzedawca przeciąganiem. Każdy numer raz — dwa wpisy
     * tej samej kategorii dałyby ją w menu dwa razy.
     *
     * @param array<string,mixed> $values
     *
     * @return array<int,array{id:int,icon:string}>
     */
    private function readCategories(array $values): array
    {
        $rows = $values['category'] ?? [];
        $out = [];
        $seen = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || empty($row['visible'])) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $out[] = ['id' => $id, 'icon' => (string) ($row['icon'] ?? '')];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return array<int,array<string,mixed>>
     */
    private function readLinks(array $values): array
    {
        $rows = $values['link'] ?? [];
        $out = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $labels = [];

            foreach ((array) ($row['labels'] ?? []) as $idLang => $label) {
                $labels[(int) $idLang] = (string) $label;
            }

            $urls = [];

            foreach ((array) ($row['urls'] ?? []) as $idLang => $url) {
                $urls[(int) $idLang] = trim((string) $url);
            }

            $style = (string) ($row['style'] ?? 'normal');

            $out[] = [
                'labels' => $labels,
                'urls' => $urls,
                'icon' => (string) ($row['icon'] ?? ''),
                'style' => in_array($style, self::LINK_STYLES, true) ? $style : 'normal',
            ];
        }

        return $out;
    }

    /** @return array<int,string> */
    private function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (trim($raw) !== '') {
            return [(int) Configuration::get('PS_LANG_DEFAULT') => $raw];
        }

        return [];
    }

    private function translate(string $raw, int $idLang): string
    {
        return $this->translateArray($this->decode($raw), $idLang);
    }

    /** @param array<int,string> $values */
    private function translateArray(array $values, int $idLang): string
    {
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
