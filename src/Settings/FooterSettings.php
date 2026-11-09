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
use Quince\Engine\Appearance\PaletteGenerator;
use Quince\Engine\Ui\IconLibrary;

/**
 * Ustawienia stopki: kolumny, dane firmy, pas dolny.
 *
 * Stopka nie zna treści, które w niej stoją. Kolumna ma nazwę i listę hooków —
 * to, co się w nich wyświetli, przynoszą moduły. Dzięki temu przestawienie
 * kolumn nie wymaga ruszania modułów, a dołożenie modułu nie wymaga zmiany
 * w motywie.
 *
 * Wyjątkiem jest pierwsza kolumna: dane firmy i kontakt to treść samego sklepu,
 * nie modułu, więc siedzą tutaj. Adres, telefon, e-mail i godziny ustawia się
 * na miejscu, każdy z własną ikoną — stopka mówi co innego niż pas informacyjny
 * i nie zawsze ma pokazywać ten sam numer.
 */
final class FooterSettings
{
    private const PREFIX = 'QUINCE_FOOTER_';

    /** Klucze wielojęzyczne — wartość trzymana jako JSON `{"1":"…","2":"…"}`. */
    private const TRANSLATABLE = [
        'COMPANY_TITLE', 'COMPANY_TEXT', 'ADDRESS', 'PHONE', 'EMAIL', 'HOURS', 'COPYRIGHT',
    ];

    /** @var array<string,string> klucz => wartość domyślna */
    private const SCHEMA = [
        'COMPANY_TITLE' => '',
        'COMPANY_TEXT' => '',
        'ADDRESS' => '',
        'PHONE' => '',
        'EMAIL' => '',
        'HOURS' => '',
        'COPYRIGHT' => '',

        // Ikona przy każdym wierszu kontaktu. Domyślne są te same, co w pasie
        // informacyjnym — sklep, który ich nie ruszy, ma stopkę spójną z pasem.
        'ADDRESS_ICON' => 'geo-alt-fill',
        'PHONE_ICON' => 'telephone',
        'EMAIL_ICON' => 'envelope',
        'HOURS_ICON' => 'clock',

        // Kolumny i ikony społecznościowe trzymamy jako JSON: liczba jednych
        // i drugich zależy od sklepu, więc kolumna w tabeli musiałaby powstawać
        // przy każdej zmianie zdania.
        'COLUMNS' => '',
        'SOCIALS' => '',
        'PAYMENTS' => '',
        'BAR_LINKS' => '',

        // Tło pasa: stopień skali albo kod barwy. Pas dolny ma własne, bo bywa,
        // że ma się odciąć od kolumn — a bywa, że ma być ich przedłużeniem.
        'BG' => 'neutral-12',
        'BAR_BG' => 'neutral-12',

        // Pasek nad stopką: miejsce dla modułu, który ma coś powiedzieć tuż
        // przed nią — baner, zapis do newslettera, pas zaufania.
        'STRIP_HOOKS' => '',
        'STRIP_BG' => 'neutral-1',
        'STRIP_WIDTH' => 'container',

        // Przycisk powrotu na górę: pokazuje się po przewinięciu strony.
        'TOTOP' => '1',
    ];

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];

        // Wartość domyślna wchodzi wyłącznie wtedy, gdy klucza nie ma w konfiguracji.
        // Pusty napis jest wyborem administratora — tak zapisuje się „bez ikony" —
        // i podmiana go na wartość domyślną przywracałaby skasowaną ikonę przy odczycie.
        foreach (self::SCHEMA as $key => $default) {
            $value = Configuration::get(self::PREFIX . $key);
            $out[$key] = ($value === false || $value === null) ? $default : (string) $value;
        }

        return $out;
    }

    /**
     * Kolumny stopki: nazwa w każdym języku i hooki, które w niej stoją.
     *
     * Sklep bez własnego ustawienia dostaje trzy kolumny z hookami, których
     * PrestaShop używa od zawsze — stopka działa od razu, zamiast być pusta
     * do pierwszej wizyty w panelu.
     *
     * @return array<int,array{labels:array<int,string>,hooks:string[]}>
     */
    public function columns(): array
    {
        $zapisane = json_decode($this->all()['COLUMNS'], true);

        if (!is_array($zapisane) || $zapisane === []) {
            return [
                ['labels' => [], 'hooks' => ['displayFooterCategories']],
                ['labels' => [], 'hooks' => ['displayFooterCustomer']],
                ['labels' => [], 'hooks' => ['displayFooter']],
            ];
        }

        $out = [];

        foreach ($zapisane as $kolumna) {
            if (!is_array($kolumna)) {
                continue;
            }

            $hooki = [];

            foreach (is_array($kolumna['hooks'] ?? null) ? $kolumna['hooks'] : [] as $hook) {
                $hook = trim((string) $hook);

                if ($hook !== '') {
                    $hooki[] = $hook;
                }
            }

            $out[] = [
                'labels' => is_array($kolumna['labels'] ?? null) ? $kolumna['labels'] : [],
                'hooks' => $hooki,
            ];
        }

        return $out;
    }

    /**
     * Ikony społecznościowe: ikona z biblioteki i adres.
     *
     * Bez pola na serwis: lista rośnie co roku o kolejny, a ikona plus adres
     * opisują każdy z nich tak samo dobrze.
     *
     * @return array<int,array{icon:string,url:string}>
     */
    public function socials(): array
    {
        return array_values(array_filter(
            $this->savedSocials(),
            static fn (array $wpis): bool => $wpis['icon'] !== '' && $wpis['url'] !== ''
        ));
    }

    /**
     * Wpisy tak, jak stoją w konfiguracji — z niedokończonymi włącznie.
     *
     * Panel pokazuje to, co zapisane, a nie to, co zobaczy sklep. Wiersz bez ikony
     * albo bez adresu nigdzie się nie wyświetli, ale nadal jest wierszem, który ktoś
     * zaczął wypełniać; wycięcie go z listy panelu kasowało go przy pierwszym zapisie
     * razem z wpisanym już adresem.
     *
     * @return array<int,array{icon:string,url:string}>
     */
    public function savedSocials(): array
    {
        $zapisane = json_decode($this->all()['SOCIALS'], true);
        $out = [];

        foreach (is_array($zapisane) ? $zapisane : [] as $wpis) {
            if (!is_array($wpis)) {
                continue;
            }

            $out[] = [
                'icon' => trim((string) ($wpis['icon'] ?? '')),
                'url' => trim((string) ($wpis['url'] ?? '')),
            ];
        }

        return $out;
    }

    /** Szerokość paska nad stopką: w kontenerze albo przez całą szerokość. */
    private const STRIP_WIDTHS = ['container', 'full'];

    /**
     * Hooki, których treść staje w pasku nad stopką.
     *
     * @return string[]
     */
    public function stripHooks(): array
    {
        $zapisane = json_decode($this->all()['STRIP_HOOKS'], true);
        $out = [];

        foreach (is_array($zapisane) ? $zapisane : [] as $hook) {
            $hook = trim((string) $hook);

            if ($hook !== '') {
                $out[] = $hook;
            }
        }

        return $out;
    }

    /**
     * Odnośniki w pasie dolnym — regulamin, prywatność, zwroty.
     *
     * Nazwa i adres w każdym języku: sklep dwujęzyczny ma dwie strony regulaminu
     * i dwa adresy do nich.
     *
     * @return array<int,array{labels:array<int,string>,urls:array<int,string>}>
     */
    public function barLinks(): array
    {
        $zapisane = json_decode($this->all()['BAR_LINKS'], true);
        $out = [];

        foreach (is_array($zapisane) ? $zapisane : [] as $wpis) {
            if (!is_array($wpis)) {
                continue;
            }

            $out[] = [
                'labels' => is_array($wpis['labels'] ?? null) ? array_map('strval', $wpis['labels']) : [],
                'urls' => is_array($wpis['urls'] ?? null) ? array_map('strval', $wpis['urls']) : [],
            ];
        }

        return $out;
    }

    /**
     * Nazwy sposobów płatności w pasie dolnym.
     *
     * @return string[]
     */
    public function payments(): array
    {
        $zapisane = json_decode($this->all()['PAYMENTS'], true);

        if (!is_array($zapisane)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($x): string => trim((string) $x),
            $zapisane
        )));
    }

    /**
     * Wszystko, czego potrzebuje szablon stopki, rozwiązane dla języka.
     *
     * @return array<string,mixed>
     */
    public function forTemplate(int $idLang): array
    {
        $s = $this->all();

        return [
            'company' => [
                'title' => $this->translate($s['COMPANY_TITLE'], $idLang),
                'text' => $this->translate($s['COMPANY_TEXT'], $idLang),
            ],

            // Każdy wiersz kontaktu niesie swój tekst i rysunek ikony. Wiersz bez
            // tekstu szablon pomija — sama ikona niczego nie mówi.
            'contact' => [
                'address' => $this->contactRow($s, 'ADDRESS', $idLang),
                'phone' => $this->contactRow($s, 'PHONE', $idLang),
                'email' => $this->contactRow($s, 'EMAIL', $idLang),
                'hours' => $this->contactRow($s, 'HOURS', $idLang),
            ],

            'columns' => array_map(
                fn (array $k): array => [
                    'label' => $this->translateArray($k['labels'], $idLang),
                    'hooks' => $k['hooks'],
                ],
                $this->columns()
            ),

            // Nazwa ikony jedzie obok jej rysunku: to ona opisuje odnośnik czytnikowi
            // ekranu. Bez niej opisem zostawał adres i czytnik dyktował cały URL.
            'socials' => array_map(
                static fn (array $w): array => [
                    'icon' => IconLibrary::svg($w['icon']),
                    'name' => $w['icon'],
                    'url' => $w['url'],
                ],
                $this->socials()
            ),

            'strip' => [
                'hooks' => $this->stripHooks(),
                'full' => $s['STRIP_WIDTH'] === 'full',
            ],

            'totop' => $s['TOTOP'] === '1',

            // Zbiorczy hook stopki wypisuje się raz: w kolumnie, jeśli sklep go
            // tam wpisał, a poza stopką, jeśli nie.
            'catchAllInColumn' => $this->catchAllInColumn(),

            'payments' => $this->payments(),
            'copyright' => $this->translate($s['COPYRIGHT'], $idLang),

            // Odnośnik bez nazwy albo bez adresu wypada: pusty myślnik w pasie
            // wygląda na usterkę sklepu.
            'barLinks' => array_values(array_filter(array_map(
                fn (array $l): array => [
                    'label' => $this->translateArray($l['labels'], $idLang),
                    'url' => $this->translateArray($l['urls'], $idLang),
                ],
                $this->barLinks()
            ), static fn (array $l): bool => $l['label'] !== '' && $l['url'] !== '')),
        ];
    }

    /** Czy któraś kolumna wypisuje zbiorczy hook `displayFooter`. */
    private function catchAllInColumn(): bool
    {
        foreach ($this->columns() as $kolumna) {
            if (in_array('displayFooter', $kolumna['hooks'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jeden wiersz kontaktu: tekst w wybranym języku i rysunek ikony.
     *
     * @param array<string,string> $s
     *
     * @return array{value:string,icon:string}
     */
    private function contactRow(array $s, string $key, int $idLang): array
    {
        return [
            'value' => $this->translate($s[$key], $idLang),
            'icon' => IconLibrary::svg($s[$key . '_ICON']),
        ];
    }

    /**
     * Role stopki i pasa dolnego wyprowadzone z wybranych teł.
     *
     * @param array<string,string> $palette
     *
     * @return array<string,string>
     */
    public function bandTokens(array $palette): array
    {
        $ustawienia = $this->all();
        $generator = new PaletteGenerator();
        $out = [];

        // Każdy pas liczy swoje role z własnego tła: tekst, odnośniki i kreska
        // dobierają się do tego, na czym stoją, a nie do stopki jako całości.
        foreach (['footer' => 'BG', 'footer-bar' => 'BAR_BG', 'footer-strip' => 'STRIP_BG'] as $pas => $klucz) {
            $wybor = $ustawienia[$klucz];

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

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        foreach (['BG', 'BAR_BG', 'STRIP_BG', 'ADDRESS_ICON', 'PHONE_ICON', 'EMAIL_ICON', 'HOURS_ICON'] as $key) {
            if (array_key_exists($key, $values)) {
                ConfigStore::set(self::PREFIX . $key, (string) $values[$key]);
            }
        }

        foreach (self::TRANSLATABLE as $key) {
            $zebrane = [];

            foreach ($values as $nazwa => $wartosc) {
                if (preg_match('/^' . $key . '_(\d+)$/', (string) $nazwa, $m)) {
                    $zebrane[(int) $m[1]] = (string) $wartosc;
                }
            }

            if ($zebrane) {
                ConfigStore::set(self::PREFIX . $key, (string) json_encode($zebrane), true);
            }
        }

        ConfigStore::set(
            self::PREFIX . 'COLUMNS',
            (string) json_encode($this->readColumns($values)),
            true
        );

        ConfigStore::set(
            self::PREFIX . 'SOCIALS',
            (string) json_encode($this->readSocials($values)),
            true
        );

        ConfigStore::set(
            self::PREFIX . 'PAYMENTS',
            (string) json_encode($this->readPayments($values)),
            true
        );

        ConfigStore::set(
            self::PREFIX . 'BAR_LINKS',
            (string) json_encode($this->readBarLinks($values)),
            true
        );

        ConfigStore::set(
            self::PREFIX . 'STRIP_HOOKS',
            (string) json_encode($this->readStripHooks($values)),
            true
        );

        // Przełącznik wysyła wartość tylko wtedy, gdy jest włączony.
        ConfigStore::set(self::PREFIX . 'TOTOP', empty($values['TOTOP']) ? '0' : '1');

        ConfigStore::set(
            self::PREFIX . 'STRIP_WIDTH',
            in_array($values['STRIP_WIDTH'] ?? '', self::STRIP_WIDTHS, true)
                ? (string) $values['STRIP_WIDTH']
                : self::SCHEMA['STRIP_WIDTH']
        );
    }

    /**
     * Hooki paska nad stopką z formularza.
     *
     * @param array<string,mixed> $values
     *
     * @return string[]
     */
    private function readStripHooks(array $values): array
    {
        $out = [];

        foreach (is_array($values['strip_hook'] ?? null) ? $values['strip_hook'] : [] as $hook) {
            $hook = trim((string) $hook);

            if ($hook !== '' && !in_array($hook, $out, true)) {
                $out[] = $hook;
            }
        }

        return $out;
    }

    /**
     * Odnośniki pasa dolnego z formularza.
     *
     * @param array<string,mixed> $values
     *
     * @return array<int,array{labels:array<int,string>,urls:array<int,string>}>
     */
    private function readBarLinks(array $values): array
    {
        $out = [];

        foreach (is_array($values['barlink'] ?? null) ? $values['barlink'] : [] as $wpis) {
            if (!is_array($wpis)) {
                continue;
            }

            $etykiety = [];
            $adresy = [];

            foreach (is_array($wpis['labels'] ?? null) ? $wpis['labels'] : [] as $idLang => $tekst) {
                $etykiety[(int) $idLang] = (string) $tekst;
            }

            foreach (is_array($wpis['urls'] ?? null) ? $wpis['urls'] : [] as $idLang => $adres) {
                $adresy[(int) $idLang] = (string) $adres;
            }

            $out[] = ['labels' => $etykiety, 'urls' => $adresy];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return array<int,array{labels:array<int,string>,hooks:string[]}>
     */
    private function readColumns(array $values): array
    {
        $out = [];

        foreach (is_array($values['column'] ?? null) ? $values['column'] : [] as $kolumna) {
            if (!is_array($kolumna)) {
                continue;
            }

            $etykiety = [];

            foreach (is_array($kolumna['labels'] ?? null) ? $kolumna['labels'] : [] as $idLang => $tekst) {
                $etykiety[(int) $idLang] = (string) $tekst;
            }

            $hooki = [];

            foreach (is_array($kolumna['hooks'] ?? null) ? $kolumna['hooks'] : [] as $hook) {
                $hook = trim((string) $hook);

                if ($hook !== '') {
                    $hooki[] = $hook;
                }
            }

            $out[] = ['labels' => $etykiety, 'hooks' => $hooki];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return array<int,array{icon:string,url:string}>
     */
    private function readSocials(array $values): array
    {
        $out = [];

        foreach (is_array($values['social'] ?? null) ? $values['social'] : [] as $wpis) {
            if (!is_array($wpis)) {
                continue;
            }

            $out[] = [
                'icon' => trim((string) ($wpis['icon'] ?? '')),
                'url' => trim((string) ($wpis['url'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return string[]
     */
    private function readPayments(array $values): array
    {
        $surowe = (string) ($values['PAYMENTS'] ?? '');

        return array_values(array_filter(array_map('trim', explode(',', $surowe))));
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

    /** @return array<int,string> */
    private function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);
        $out = [];

        foreach (is_array($decoded) ? $decoded : [] as $idLang => $tekst) {
            $out[(int) $idLang] = (string) $tekst;
        }

        return $out;
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

        foreach ($values as $wartosc) {
            if (trim((string) $wartosc) !== '') {
                return (string) $wartosc;
            }
        }

        return '';
    }
    /**
     * PeÅne nazwy kluczy konfiguracji tej klasy.
     *
     * Potrzebne przy odinstalowaniu: moduÅ kasuje dokÅadnie to, co zaÅoÅ¼yÅ.
     * Nazwy wyliczane ze schematu, a nie wypisane drugi raz â inaczej doÅoÅ¼enie
     * pola zostawiaÅoby po sobie klucz na zawsze.
     *
     * @return string[]
     */
    public static function configurationKeys(): array
    {
        return array_map(
            static fn (string $klucz): string => self::PREFIX . $klucz,
            array_keys(self::SCHEMA)
        );
    }

}
