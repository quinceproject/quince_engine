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
use Quince\Engine\Ui\IconLibrary;

/**
 * Ustawienia topbara: treści, widoczność i pasek komunikatu.
 *
 * Kolor każdego z dwóch pasów jest tutaj, nie w AppearanceSettings. Trzymamy
 * STOPIEŃ SKALI (`neutral-12`, `primary-9`), nie kod barwy, żeby pas szedł za
 * paletą po zmianie koloru marki. Pozostałe role (tekst, odnośnik, kreskę, tło
 * pod kursorem) wyprowadza `bandFromBackground()`.
 *
 * Widoczność ma dwa przełączniki: komputer i telefon. Tablet dziedziczy po dużym
 * ekranie, a to, co się na nim nie mieści, chowa reguła w motywie.
 */
final class TopbarSettings
{
    private const PREFIX = 'QUINCE_TOPBAR_';

    /** Klucze wielojęzyczne: wartość trzymana jako JSON `{"1":"…","2":"…"}`. */
    private const TRANSLATABLE = ['PHONE', 'EMAIL', 'HOURS', 'PROMO_TEXT', 'PROMO_LINK'];

    /** @var array<string,string> klucz => wartość domyślna */
    private const SCHEMA = [
        // pas informacyjny
        'ENABLED'           => '1',
        // Tło pasa: stopień skali palety, nie kod barwy.
        'BG'                => 'neutral-12',
        'PHONE'             => '',
        'PHONE_ICON'        => 'telephone',
        'PHONE_DESKTOP'     => '1',
        'PHONE_MOBILE'      => '1',
        'EMAIL'             => '',
        'EMAIL_ICON'        => 'envelope',
        'EMAIL_DESKTOP'     => '1',
        'EMAIL_MOBILE'      => '0',
        'HOURS'             => '',
        'HOURS_ICON'        => 'clock',
        'HOURS_DESKTOP'     => '1',
        'HOURS_MOBILE'      => '0',
        'LANG_DESKTOP'      => '1',
        'LANG_MOBILE'       => '1',
        'CURRENCY_DESKTOP'  => '1',
        // Przy 390 px kontakt zajmuje 133 px, oba przełączniki 109 px,
        // więc waluta mieści się w pasie także na telefonie.
        'CURRENCY_MOBILE'   => '1',

        // pasek komunikatu: osobny wiersz nad pasem informacyjnym
        'PROMO_ENABLED'     => '0',
        'PROMO_BG'          => 'primary-9',
        'PROMO_TEXT'        => '',
        'PROMO_LINK'        => '',
        'PROMO_DISMISSIBLE' => '0',
    ];

    /**
     * Role obu pasów, gotowe do wypisania jako tokeny żądania.
     *
     * Stopień, którego nie ma w palecie, cofa się do wartości domyślnej zamiast
     * zostawiać pas przezroczysty.
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

        foreach (['topbar' => 'BG', 'promobar' => 'PROMO_BG'] as $pas => $klucz) {
            $wybor = $settings[$klucz];

            // Wybór ma trzy postacie: nazwa roli, stopień skali albo gotowy kod
            // barwy. Pierwsze dwie szukamy w palecie, trzecia jest sobą.
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
     * Dane gotowe do wstawienia w szablon: treści rozwiązane dla bieżącego języka,
     * widoczność zamieniona na klasy CSS.
     *
     * @return array<string,mixed>
     */
    public function forTemplate(int $idLang): array
    {
        $s = $this->all();

        $phone = $this->translate($s['PHONE'], $idLang);
        $email = $this->translate($s['EMAIL'], $idLang);
        $promoText = $this->translate($s['PROMO_TEXT'], $idLang);

        return [
            'enabled' => $s['ENABLED'] === '1',

            'items' => [
                'phone' => [
                    'value'   => $phone,
                    'icon'    => IconLibrary::svg($s['PHONE_ICON']),
                    'visible' => $phone !== '' && $this->anyScreen($s, 'PHONE'),
                    'classes' => $this->visibilityClasses($s, 'PHONE'),
                ],
                'email' => [
                    'value'   => $email,
                    'icon'    => IconLibrary::svg($s['EMAIL_ICON']),
                    'visible' => $email !== '' && $this->anyScreen($s, 'EMAIL'),
                    'classes' => $this->visibilityClasses($s, 'EMAIL'),
                ],
                'hours' => [
                    'value'   => $this->translate($s['HOURS'], $idLang),
                    'icon'    => IconLibrary::svg($s['HOURS_ICON']),
                    'visible' => $this->translate($s['HOURS'], $idLang) !== '' && $this->anyScreen($s, 'HOURS'),
                    'classes' => $this->visibilityClasses($s, 'HOURS'),
                ],
            ],

            'pickers' => [
                'lang'     => ['visible' => $this->anyScreen($s, 'LANG'),     'classes' => $this->visibilityClasses($s, 'LANG')],
                'currency' => ['visible' => $this->anyScreen($s, 'CURRENCY'), 'classes' => $this->visibilityClasses($s, 'CURRENCY')],
            ],

            'promo' => [
                // Pasek pokazujemy tylko wtedy, gdy jest włączony ORAZ ma treść
                // w tym języku.
                'visible'     => $s['PROMO_ENABLED'] === '1' && $promoText !== '',
                'text'        => $promoText,
                'link'        => $this->translate($s['PROMO_LINK'], $idLang),
                'dismissible' => $s['PROMO_DISMISSIBLE'] === '1',
            ],
        ];
    }

    /**
     * Pola wielojęzyczne rozpakowane do tablic, dla formularza w panelu.
     * Dekodowanie robimy tutaj, bo Twig nie ma filtra na JSON.
     *
     * @return array<string,array<int,string>>
     */
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
            if (in_array($key, self::TRANSLATABLE, true)) {
                continue;
            }

            if (array_key_exists($key, $values)) {
                ConfigStore::set(self::PREFIX . $key, (string) $values[$key]);
            }
        }

        // Pola wielojęzyczne przychodzą jako HOURS_1, HOURS_2 … i lądują w jednym
        // wpisie JSON. isHtml = true, bo bez tego PrestaShop wycina cudzysłowy
        // i zapisany JSON przestaje być JSON-em.
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

        // Starszy zapis: zwykły tekst zamiast JSON-a. Przypisujemy go językowi
        // domyślnemu.
        if (trim($raw) !== '') {
            return [(int) Configuration::get('PS_LANG_DEFAULT') => $raw];
        }

        return [];
    }

    private function translate(string $raw, int $idLang): string
    {
        $values = $this->decode($raw);

        // Bez tłumaczenia dla języka bierzemy pierwszą niepustą wartość,
        // żeby w pasie nie zostało puste miejsce.
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

    /** @param array<string,string> $s */
    private function anyScreen(array $s, string $key): bool
    {
        return $s[$key . '_DESKTOP'] === '1' || $s[$key . '_MOBILE'] === '1';
    }

    /**
     * Klasy ukrywające. Element niewidoczny na danym ekranie dostaje klasę, a nie
     * znika z dokumentu, więc obrót telefonu nie wymaga przeładowania strony.
     *
     * @param array<string,string> $s
     */
    private function visibilityClasses(array $s, string $key): string
    {
        $classes = [];

        if ($s[$key . '_DESKTOP'] !== '1') {
            $classes[] = 'q-hide-desktop';
        }

        if ($s[$key . '_MOBILE'] !== '1') {
            $classes[] = 'q-hide-phone';
        }

        return implode(' ', $classes);
    }
}
