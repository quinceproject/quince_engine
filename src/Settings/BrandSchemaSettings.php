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
use Context;

/**
 * Ustawienia „Brand & Schema": dane organizacji dla Schema.org, domyślne
 * meta na wypadek, gdy żadna strona ich nie ustawi, i treść llms.txt.
 *
 * PS9 już wypisuje podstawowy Organization JSON-LD na każdej stronie
 * (`_partials/microdata/head-jsonld.tpl`: nazwa, adres, logo). Ta klasa go
 * NIE dubluje, tylko dostarcza wzbogaconą wersję tego samego obiektu (sameAs,
 * contactPoint, nadpisanie nazwy/logo), którą motyw wstawia w to samo
 * miejsce. Puste pole znaczy „jak w rdzeniu PS9", nie „brak wartości".
 *
 * Nazwa firmy, logo, telefon i e-mail NIE są wielojęzyczne, tak samo jak
 * `PS_SHOP_NAME` w rdzeniu PS9: to dane firmy, nie treść strony.
 * Tytuł, opis i sekcje llms.txt SĄ wielojęzyczne: to wolny tekst czytany
 * przez silniki AI, w języku, w którym ktoś odwiedził sklep.
 * Zapis idzie przez `ConfigStore`, więc w wielosklepowości każdy sklep
 * z grupy ma swój własny zestaw wartości.
 */
final class BrandSchemaSettings
{
    private const PREFIX = 'QUINCE_BRAND_';

    /** Proste pola wielojęzyczne: JSON `{"1":"…","2":"…"}` per `id_lang`. */
    private const TRANSLATABLE = ['LLMS_TITLE', 'LLMS_SUMMARY'];

    /** @return array<string,string> */
    public function all(): array
    {
        $defaults = [
            'ORG_NAME' => '',
            'ORG_LOGO' => '',
            'ORG_PHONE' => '',
            'ORG_EMAIL' => '',
            'ORG_CONTACT_TYPE' => 'customer service',
            'ORG_SAMEAS' => '[]',
            'DEFAULT_ROBOTS' => '',
            'DEFAULT_OG_IMAGE' => '',
            'LLMS_TITLE' => '{}',
            'LLMS_SUMMARY' => '{}',
            'LLMS_SECTIONS' => '[]',
        ];

        $out = [];

        foreach ($defaults as $key => $fallback) {
            $value = Configuration::get(self::PREFIX . $key);
            $out[$key] = $value !== false && $value !== null && (string) $value !== '' ? (string) $value : $fallback;
        }

        return $out;
    }

    /** @return string[] */
    public function sameAs(): array
    {
        $zapisane = json_decode($this->all()['ORG_SAMEAS'], true);

        return array_values(array_filter(
            array_map(static fn ($u): string => trim((string) $u), is_array($zapisane) ? $zapisane : []),
            static fn (string $u): bool => $u !== ''
        ));
    }

    /**
     * Zapisane wartości pól wielojęzycznych, do pól panelu:
     * `fields.multilang()`/`fields.multilangArea()` oczekuje `{id_lang: string}`.
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

    /**
     * Sekcje llms.txt tak, jak zapisane: nagłówek i każde pole pozycji
     * (tytuł, adres, notatka) jako mapa język→tekst. Surowy kształt służy
     * do uwodnienia stanu Alpine w panelu, gdzie przełącznik języka działa
     * w przeglądarce, bez przeładowania strony.
     *
     * @return array<int,array{heading:array<int,string>,items:array<int,array{title:array<int,string>,url:array<int,string>,note:array<int,string>}>}>
     */
    public function llmsSections(): array
    {
        $zapisane = json_decode($this->all()['LLMS_SECTIONS'], true);
        $out = [];

        foreach (is_array($zapisane) ? $zapisane : [] as $sekcja) {
            if (!is_array($sekcja)) {
                continue;
            }

            $pozycje = [];
            foreach (is_array($sekcja['items'] ?? null) ? $sekcja['items'] : [] as $pozycja) {
                if (!is_array($pozycja)) {
                    continue;
                }

                $pozycje[] = [
                    'title' => $this->decodeArray($pozycja['title'] ?? []),
                    'url' => $this->decodeArray($pozycja['url'] ?? []),
                    'note' => $this->decodeArray($pozycja['note'] ?? []),
                ];
            }

            $out[] = [
                'heading' => $this->decodeArray($sekcja['heading'] ?? []),
                'items' => $pozycje,
            ];
        }

        return $out;
    }

    /**
     * Sekcje llms.txt rozwiązane do jednego języka: bieżące tłumaczenie, a bez
     * niego pierwsze dostępne (jak w `TopbarSettings`). Pozycja bez
     * tytułu albo adresu W TYM JĘZYKU znika z wyniku dla niego, choćby miała
     * komplet w innym.
     *
     * @return array<int,array{heading:string,items:array<int,array{title:string,url:string,note:string}>}>
     */
    public function llmsSectionsFor(int $idLang): array
    {
        $out = [];

        foreach ($this->llmsSections() as $sekcja) {
            $naglowek = $this->translateArray($sekcja['heading'], $idLang);
            if ($naglowek === '') {
                continue;
            }

            $pozycje = [];
            foreach ($sekcja['items'] as $pozycja) {
                $tytul = $this->translateArray($pozycja['title'], $idLang);
                $url = $this->translateArray($pozycja['url'], $idLang);
                if ($tytul === '' || $url === '') {
                    continue;
                }

                $pozycje[] = [
                    'title' => $tytul,
                    'url' => $url,
                    'note' => $this->translateArray($pozycja['note'], $idLang),
                ];
            }

            $out[] = ['heading' => $naglowek, 'items' => $pozycje];
        }

        return $out;
    }

    public function llmsTitleFor(int $idLang): string
    {
        $tytul = $this->translateArray($this->decode($this->all()['LLMS_TITLE']), $idLang);

        return $tytul !== '' ? $tytul : (string) Configuration::get('PS_SHOP_NAME');
    }

    public function llmsSummaryFor(int $idLang): string
    {
        return $this->translateArray($this->decode($this->all()['LLMS_SUMMARY']), $idLang);
    }

    /**
     * @param array<string,mixed> $values dane z formularza ($request->request->all())
     */
    public function save(array $values): void
    {
        $proste = [
            'ORG_NAME' => 'org_name',
            'ORG_LOGO' => 'org_logo',
            'ORG_PHONE' => 'org_phone',
            'ORG_EMAIL' => 'org_email',
            'ORG_CONTACT_TYPE' => 'org_contact_type',
            'DEFAULT_OG_IMAGE' => 'default_og_image',
        ];

        foreach ($proste as $key => $pole) {
            ConfigStore::set(self::PREFIX . $key, trim((string) ($values[$pole] ?? '')));
        }

        // Format `index|noindex[,follow|nofollow]` albo pusto. Inna wartość
        // zapisana tu trafiłaby do <meta robots> na każdej stronie, która sama
        // tego taga nie ustawia, i cichcem wyłączyła sklep z indeksu.
        $robots = trim((string) ($values['default_robots'] ?? ''));
        if ($robots !== '' && !preg_match('/^(index|noindex)(,(follow|nofollow))?$/i', $robots)) {
            $robots = '';
        }
        ConfigStore::set(self::PREFIX . 'DEFAULT_ROBOTS', $robots);

        foreach (self::TRANSLATABLE as $key) {
            $zebrane = [];

            foreach ($values as $nazwa => $wartosc) {
                if (preg_match('/^' . $key . '_(\d+)$/', (string) $nazwa, $m)) {
                    $zebrane[(int) $m[1]] = trim((string) $wartosc);
                }
            }

            ConfigStore::set(self::PREFIX . $key, (string) json_encode($zebrane), true);
        }

        ConfigStore::set(self::PREFIX . 'ORG_SAMEAS', (string) json_encode($this->readSameAs($values)), true);
        ConfigStore::set(self::PREFIX . 'LLMS_SECTIONS', (string) json_encode($this->readLlmsSections($values)), true);
    }

    /** @return string[] */
    private function readSameAs(array $values): array
    {
        $wyslane = is_array($values['org_sameas'] ?? null) ? $values['org_sameas'] : [];

        return array_values(array_filter(
            array_map(static fn ($u): string => trim((string) $u), $wyslane),
            static fn (string $u): bool => $u !== ''
        ));
    }

    /**
     * Zapisuje sekcję, jeśli ma choć jedno tłumaczenie nagłówka; zapisuje
     * pozycję, jeśli ma choć jedno tłumaczenie z kompletem tytułu i adresu.
     * Czy pozycja ma KOMPLET w KONKRETNYM języku, rozstrzyga dopiero
     * `llmsSectionsFor()` przy generowaniu pliku. Tu odsiewane jest tylko to, co
     * nie ma żadnej treści w żadnym języku.
     *
     * @return array<int,array{heading:array<int,string>,items:array<int,array{title:array<int,string>,url:array<int,string>,note:array<int,string>}>}>
     */
    private function readLlmsSections(array $values): array
    {
        $wyslane = is_array($values['llms_section'] ?? null) ? $values['llms_section'] : [];
        $out = [];

        foreach ($wyslane as $sekcja) {
            if (!is_array($sekcja)) {
                continue;
            }

            $naglowek = $this->trimmedMap($sekcja['heading'] ?? []);
            if ($this->allEmpty($naglowek)) {
                continue;
            }

            $pozycje = [];
            foreach (is_array($sekcja['items'] ?? null) ? $sekcja['items'] : [] as $pozycja) {
                if (!is_array($pozycja)) {
                    continue;
                }

                $tytul = $this->trimmedMap($pozycja['title'] ?? []);
                $url = $this->trimmedMap($pozycja['url'] ?? []);
                if ($this->allEmpty($tytul) && $this->allEmpty($url)) {
                    continue;
                }

                $pozycje[] = ['title' => $tytul, 'url' => $url, 'note' => $this->trimmedMap($pozycja['note'] ?? [])];
            }

            $out[] = ['heading' => $naglowek, 'items' => $pozycje];
        }

        return $out;
    }

    /** @return array<int,string> */
    private function trimmedMap(mixed $wartosci): array
    {
        $out = [];

        foreach (is_array($wartosci) ? $wartosci : [] as $idLang => $tekst) {
            $out[(int) $idLang] = trim((string) $tekst);
        }

        return $out;
    }

    /** @param array<int,string> $values */
    private function allEmpty(array $values): bool
    {
        foreach ($values as $wartosc) {
            if ($wartosc !== '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<int,string> */
    private function decode(string $raw): array
    {
        return $this->decodeArray(json_decode($raw, true));
    }

    /** @return array<int,string> */
    private function decodeArray(mixed $wartosci): array
    {
        $out = [];

        foreach (is_array($wartosci) ? $wartosci : [] as $idLang => $tekst) {
            $out[(int) $idLang] = (string) $tekst;
        }

        return $out;
    }

    /** @param array<int,string> $values */
    private function translateArray(array $values, int $idLang): string
    {
        if (isset($values[$idLang]) && trim($values[$idLang]) !== '') {
            return trim($values[$idLang]);
        }

        foreach ($values as $wartosc) {
            if (trim((string) $wartosc) !== '') {
                return trim((string) $wartosc);
            }
        }

        return '';
    }

    /**
     * Pola, którymi ten ekran DOKŁADA się do Organization. Bez tego, co rdzeń PS9
     * już umie policzyć sam (nazwa, adres, telefon, e-mail z `PS_SHOP_*`,
     * logo motywu). Nadpisanie nazwy/logo wchodzi tylko, gdy pole nie jest
     * puste; puste wraca do zachowania rdzenia.
     *
     * Jedno źródło dla dwóch odbiorców: hook `actionFrontControllerSetVariables`
     * dokleja ten zestaw do `structured_data.organization`, który od PS 9.2
     * liczy sam rdzeń (`FrontController::getStructuredData()`); `organizationJsonLd()`
     * niżej składa z tego samego zestawu pełny podgląd dla panelu i dla
     * starszych PS9 bez tamtego mechanizmu.
     *
     * @return array<string,mixed>
     */
    public function organizationOverrides(?Context $context = null): array
    {
        $ustawienia = $this->all();
        $out = [];

        $name = trim($ustawienia['ORG_NAME']);
        if ($name !== '') {
            $out['name'] = $name;
        }

        $logo = $this->absoluteLogo($ustawienia['ORG_LOGO'], $context);
        if ($logo !== '') {
            $out['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }

        $sameAs = $this->sameAs();
        if ($sameAs !== []) {
            $out['sameAs'] = $sameAs;
        }

        $phone = trim($ustawienia['ORG_PHONE']);
        $email = trim($ustawienia['ORG_EMAIL']);
        if ($phone !== '' || $email !== '') {
            $contactPoint = [
                '@type' => 'ContactPoint',
                'contactType' => trim($ustawienia['ORG_CONTACT_TYPE']) ?: 'customer service',
            ];
            if ($phone !== '') {
                $contactPoint['telephone'] = $phone;
            }
            if ($email !== '') {
                $contactPoint['email'] = $email;
            }
            $out['contactPoint'] = $contactPoint;
        }

        return $out;
    }

    /**
     * Pełny Organization, dla ekranów bez rdzeniowego `structured_data`
     * (PS 9.0/9.1, `head-jsonld.tpl`) i dla podglądu w panelu. Baza
     * (nazwa, url, logo motywu) liczona tak samo, jak rdzeń PS 9.2 liczy ją
     * w `getStructuredData()`, żeby obie ścieżki dawały ten sam wynik.
     */
    public function organizationJsonLd(?Context $context = null): string
    {
        $ctx = $context ?? Context::getContext();
        $shopUrl = $ctx && $ctx->shop ? rtrim($ctx->shop->getBaseURL(true), '/') : '';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) Configuration::get('PS_SHOP_NAME'),
        ];

        if ($shopUrl !== '') {
            $data['url'] = $shopUrl;
        }

        $themeLogo = $this->absoluteLogo('', $ctx);
        if ($themeLogo !== '') {
            $data['logo'] = ['@type' => 'ImageObject', 'url' => $themeLogo];
        }

        $data = array_merge($data, $this->organizationOverrides($ctx));

        // BEZ JSON_UNESCAPED_SLASHES: wyjście trafia do <script> przez `nofilter`
        // (fallback dla PS 9.0/9.1 w head-jsonld.tpl, gdy rdzeń nie ma jeszcze
        // $structured_data). Pole „Name"/sameAs z literalnym `</script>` inaczej
        // zamknęłoby tag przedwcześnie i wstrzyknęło dowolny HTML. Escapowane `/`
        // nie zmienia danych (`<\/script>` parsuje się w JSON tak samo), ale rozbija
        // ciąg znaków, którego przeglądarka szuka.
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Logo jako bezwzględny URL: podane w ustawieniach (ścieżka względna
     * dopełniona adresem sklepu) albo, gdy puste, logo motywu z `PS_LOGO`.
     */
    private function absoluteLogo(string $override, ?Context $context): string
    {
        $ctx = $context ?? Context::getContext();
        $shopUrl = $ctx && $ctx->shop ? rtrim($ctx->shop->getBaseURL(true), '/') : '';
        $logo = trim($override);

        if ($logo === '' && $shopUrl !== '') {
            $psLogo = Configuration::get('PS_LOGO');

            return $psLogo ? $shopUrl . '/img/' . ltrim((string) $psLogo, '/') : '';
        }

        if ($logo !== '' && !preg_match('#^https?://#i', $logo) && $shopUrl !== '') {
            return $shopUrl . '/' . ltrim($logo, '/');
        }

        return $logo;
    }

    /**
     * Gotowe znaczniki `<meta>` dla domyślnych wartości robots/og:image,
     * tylko gdy sklep je ustawił. Strona, która ustawia własny tag, i tak
     * wypisze swój. Przeglądarki i roboty czytają pierwsze wystąpienie,
     * a ten tag stoi w `<head>` wcześnie, więc per-stronowy zwykle wygrywa.
     *
     * @return array<int,array{attr:string,content:string}>
     */
    public function defaultMetaTags(?Context $context = null): array
    {
        $ustawienia = $this->all();
        $tags = [];

        $robots = trim($ustawienia['DEFAULT_ROBOTS']);
        if ($robots !== '') {
            $tags[] = ['attr' => 'name="robots"', 'content' => $robots];
        }

        $og = trim($ustawienia['DEFAULT_OG_IMAGE']);
        if ($og !== '') {
            $ctx = $context ?? Context::getContext();
            if ($ctx && $ctx->shop && !preg_match('#^https?://#i', $og)) {
                $og = rtrim($ctx->shop->getBaseURL(true), '/') . '/' . ltrim($og, '/');
            }
            $tags[] = ['attr' => 'property="og:image"', 'content' => $og];
        }

        return $tags;
    }
}
