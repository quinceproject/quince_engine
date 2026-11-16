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

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use Context;
use Language;
use Quince\Engine\Ui\IconLibrary;

/**
 * Strony logowania i rejestracji: korzyści z konta obok formularza.
 *
 * Każda strona ma własną listę. Korzyść to ikona z biblioteki silnika oraz
 * tytuł i opis w każdym języku. Dopóki sklep niczego nie zapisał, strony
 * pokazują cztery korzyści domyślne w języku klienta.
 *
 * Bezpieczeństwo: tytuł i opis są zwykłym tekstem. Zapis zdejmuje znaczniki
 * i przycina długość, szablon i tak wypisuje je z escapowaniem. Ikona jest
 * nazwą sprawdzaną przez IconLibrary; SVG pochodzi z plików silnika, nigdy
 * z formularza.
 */
final class AuthSettings
{
    public const PAGES = ['login', 'register'];

    private const KEYS = [
        'login' => 'QUINCE_AUTH_LOGIN_BENEFITS',
        'register' => 'QUINCE_AUTH_REGISTER_BENEFITS',
    ];

    /** Najwięcej korzyści na stronę. Dłuższa lista spycha przycisk poza ekran. */
    private const MAX_ROWS = 12;

    private const TITLE_MAX = 80;
    private const TEXT_MAX = 300;

    /**
     * Korzyści domyślne: ikona, tytuł i opis. Teksty tłumaczy domena sklepu
     * silnika, więc każdy język dostaje swoją wersję bez wpisywania.
     */
    private const DEFAULTS = [
        ['box', 'Order tracking', 'Track the status of your orders and your purchase history in one place.'],
        ['lightning-charge-fill', 'Faster checkout', 'Saved addresses and details: buy in a few clicks without typing everything again.'],
        ['heart-fill', 'Wishlist', 'Save products to compare and buy them later, when you are ready.'],
        ['tag-fill', 'Discounts and offers', 'Get exclusive discount codes and promotion alerts.'],
    ];

    /**
     * Korzyści strony do panelu: tytuł i opis w każdym języku, nazwa ikony.
     *
     * @return array<int,array{icon:string,titles:array<int,string>,texts:array<int,string>}>
     */
    public function rows(string $page): array
    {
        $raw = Configuration::get(self::KEYS[$page] ?? '');

        if ($raw === false || $raw === null) {
            return $this->defaults();
        }

        // ConfigStore zapisuje w trybie html, PrestaShop zwraca wtedy encje
        // w miejscu cudzysłowów. Bez odkodowania JSON się nie rozpakuje.
        $stored = json_decode(html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8'), true);
        $out = [];

        foreach (is_array($stored) ? $stored : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $out[] = [
                'icon' => $this->icon((string) ($row['icon'] ?? '')),
                'titles' => $this->texts($row['titles'] ?? [], self::TITLE_MAX),
                'texts' => $this->texts($row['texts'] ?? [], self::TEXT_MAX),
            ];
        }

        return $out;
    }

    /**
     * Korzyści gotowe dla sklepu: teksty w języku klienta, ikona jako SVG.
     * Korzyść bez tytułu wypada.
     *
     * @return array<int,array{title:string,text:string,icon:string}>
     */
    public function benefitsFor(string $page, int $idLang): array
    {
        $out = [];

        foreach ($this->rows($page) as $row) {
            $title = $this->pick($row['titles'], $idLang);

            if ($title === '') {
                continue;
            }

            $out[] = [
                'title' => $title,
                'text' => $this->pick($row['texts'], $idLang),
                'icon' => $row['icon'] !== '' ? IconLibrary::svg($row['icon']) : '',
            ];
        }

        return $out;
    }

    /**
     * Dane dla `$modules.quince_engine.auth`.
     *
     * @return array{login:array<int,array<string,string>>,register:array<int,array<string,string>>}
     */
    public function forTemplate(int $idLang): array
    {
        return [
            'login' => $this->benefitsFor('login', $idLang),
            'register' => $this->benefitsFor('register', $idLang),
        ];
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        $benefits = $values['benefit'] ?? [];

        foreach (self::PAGES as $page) {
            $rows = is_array($benefits) && isset($benefits[$page]) && is_array($benefits[$page]) ? $benefits[$page] : [];
            $out = [];

            foreach ($rows as $row) {
                if (!is_array($row) || count($out) >= self::MAX_ROWS) {
                    continue;
                }

                $titles = $this->texts($row['title'] ?? [], self::TITLE_MAX);

                // Wiersz bez tytułu w żadnym języku to pusty, dodany przez pomyłkę.
                if (!array_filter($titles, static fn (string $t): bool => $t !== '')) {
                    continue;
                }

                $out[] = [
                    'icon' => $this->icon((string) ($row['icon'] ?? '')),
                    'titles' => $titles,
                    'texts' => $this->texts($row['text'] ?? [], self::TEXT_MAX),
                ];
            }

            ConfigStore::set(self::KEYS[$page], (string) json_encode($out, JSON_UNESCAPED_UNICODE), true);
        }
    }

    /** @return string[] */
    public static function configurationKeys(): array
    {
        return array_values(self::KEYS);
    }

    // ------------------------------------------------------------------ pomocnicze

    /**
     * Korzyści domyślne we wszystkich językach sklepu.
     *
     * @return array<int,array{icon:string,titles:array<int,string>,texts:array<int,string>}>
     */
    private function defaults(): array
    {
        $translator = Context::getContext()->getTranslator();
        $languages = Language::getLanguages(false);
        $out = [];

        foreach (self::DEFAULTS as [$icon, $title, $text]) {
            $titles = [];
            $texts = [];

            foreach ($languages as $language) {
                $locale = (string) ($language['locale'] ?? $language['language_code'] ?? '');
                $id = (int) $language['id_lang'];
                $titles[$id] = $translator->trans($title, [], 'Modules.Quinceengine.Shop', $locale);
                $texts[$id] = $translator->trans($text, [], 'Modules.Quinceengine.Shop', $locale);
            }

            $out[] = ['icon' => $icon, 'titles' => $titles, 'texts' => $texts];
        }

        return $out;
    }

    /**
     * Nazwa ikony z biblioteki silnika albo pusto. IconLibrary::svg() sam
     * sprawdza nazwę (bez ścieżek i znaków spoza nazwy pliku) i dla złej zwraca
     * pusty tekst.
     */
    private function icon(string $name): string
    {
        return IconLibrary::svg($name) !== '' ? $name : '';
    }

    /**
     * Teksty po identyfikatorze języka: zwykły tekst bez znaczników, w jednej
     * linii, przycięty do długości.
     *
     * @return array<int,string>
     */
    private function texts(mixed $values, int $max): array
    {
        $out = [];

        foreach (is_array($values) ? $values : [] as $idLang => $value) {
            $tekst = strip_tags(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
            $tekst = trim((string) preg_replace('/\s+/u', ' ', $tekst));
            $out[(int) $idLang] = mb_substr($tekst, 0, $max);
        }

        return $out;
    }

    /** @param array<int,string> $values */
    private function pick(array $values, int $idLang): string
    {
        if (isset($values[$idLang]) && $values[$idLang] !== '') {
            return $values[$idLang];
        }

        $domyslny = (int) Configuration::get('PS_LANG_DEFAULT');

        return $values[$domyslny] ?? '';
    }
}
