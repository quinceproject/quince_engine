<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Appearance;

/**
 * Wylicza paletę sklepu z szesnastu decyzji administratora.
 *
 * Odwzorowuje mechanizm zaprojektowany w prototypie
 * (docs/delivery3/Quince Panel - Ustawienia ogolne.dc.html).
 *
 * Wynik ma dwie warstwy:
 *
 *   1. SKALE — dwanaście stopni dla koloru głównego, dodatkowego, promocji i neutralnych.
 *      Stopień oznacza ZADANIE, nie jasność: 1 to tło strony, 9 wypełnienie przycisku,
 *      12 tekst wysokokontrastowy. Kontrakt pochodzi z Radix Colors.
 *   2. ROLE SEMANTYCZNE — nazwy opisujące użycie, wskazujące na stopnie. Tego używa motyw;
 *      warstwę trzecią, tokeny komponentu, trzyma już CSS motywu.
 *
 * Pełną dwunastkę dostają: marka, trzeci kolor, promocja i neutralne. Statusy mają
 * pięć ról, bo ich znaczenie jest umowne i nie schodzą na tła całych sekcji.
 * Pasy strony sześć, wyliczanych z jednej decyzji `light` / `dark` / `brand`.
 */
final class PaletteGenerator
{
    /** Odcień, nasycenie i jasność bazowa każdego statusu (HSL). */
    private const STATUS = [
        'success' => [142, 0.62, 0.34],
        'danger'  => [4,   0.68, 0.45],
        'warning' => [38,  0.72, 0.42],
        'info'    => [212, 0.62, 0.44],
    ];

    /** Odcień i nasycenie neutralnych dla każdego wariantu barwy. */
    /**
     * Jasności dwunastu stopni skali neutralnej, w procentach.
     *
     * Ta skala CELOWO nie jest monotoniczna: powierzchnia karty (3) jest jaśniejsza
     * od tła strony (1). Biała karta ma odskakiwać od lekko przygaszonego tła — bez tego
     * listing wygląda jak jednolita płachta. Nie „poprawiać" tego na ciąg malejący.
     */
    /*
     * Drabinka jasności szarości, w procentach HSL.
     *
     * Stopnie 5, 6 i 7 podniesione (92/85/75 na 94/88/77): przy poprzednich
     * kreska bloków, czyli piąty stopień, wychodziła #ebebeb i była wyraźnie
     * ciemniejsza od tej z makiety (#f0efee). Kreska ma dzielić, a nie rysować
     * ramkę wokół każdego bloku.
     */
    private const NEUTRAL_L = [
        1 => 96.0, 2 => 98.0, 3 => 100.0, 4 => 96.5, 5 => 94.0, 6 => 88.0,
        7 => 77.0, 8 => 64.0, 9 => 50.0, 10 => 42.0, 11 => 38.0, 12 => 16.0,
    ];

    /**
     * Jasność percepcyjna, od której startuje skala barwna, i ta, do której schodzi.
     *
     * Liczby są w skali OKLCH (0–1), nie w procentach HSL. To nie jest kosmetyczna
     * zmiana jednostki: w HSL „50%" znaczy co innego dla żółtego niż dla granatu,
     * przez co jeden wzór dawał raz brąz, raz zmianę niewidoczną.
     */
    private const TONE_TOP = 0.98;
    private const TONE_BOTTOM = 0.32;

    /** Ile nasycenia zostaje przy górnym końcu skali — reszta ginie w bieli. */
    private const CHROMA_TOP = 0.07;

    /**
     * Charakter szarości: odcień i nasycenie podkładu (prototyp: PODKŁAD).
     *
     * Czyste to zero barwy. Ciepłe ciągną w stronę beżu, chłodne w stronę
     * błękitu — nasycenie jest śladowe, bo szarość ma zostać szarością.
     */
    private const TINT = [
        'pure' => [0, 0.0],
        'warm' => [45, 0.04],
        'cool' => [235, 0.04],
    ];

    /**
     * Zmiana jasności na najechanie i wciśnięcie.
     *
     * Kolory ciemne dostają większy krok, bo przy dolnym końcu skali oko rozróżnia
     * mniej: te same 6% widać na żółtym, a na granacie już nie.
     */
    private const STATE_STEPS = [
        'jasny' => ['hover' => -0.06, 'active' => -0.11],
        'ciemny' => ['hover' => 0.08, 'active' => 0.14],
    ];

    /** Powyżej tej jasności kolor uznajemy za jasny, czyli przyciemniamy go na najechanie. */
    private const TONE_MID = 0.45;

    /**
     * Najmniejszy kontrast między kolejnymi stanami przycisku.
     *
     * 1.15 to próg, przy którym zmiana tła jest zauważalna, a jeszcze nie wygląda
     * jak inny przycisk. Mierzymy kontrastem, a nie różnicą jasności, bo przy samym
     * dole skali te same liczby jasności dają na ekranie różnice nie do zobaczenia.
     */
    private const STATE_MIN_CONTRAST = 1.15;

    /** Pasy strony w kolejności występowania na stronie. */
    public const BANDS = ['topbar', 'header', 'nav', 'footer'];

    /** Poniżej tylu stopni odcienia dwa kolory przestają być rozróżnialne. */
    private const HUE_COLLISION = 20.0;

    /**
     * @param array<string,string> $settings wartości z AppearanceSettings
     *
     * @return array<string,string> nazwa roli => kolor hex
     */
    public function generate(array $settings): array
    {
        $input = $this->input($settings);

        $neutral   = $this->neutralScale($input['dark'], $input['tint']);

        // Progi czytelności skal barwnych liczymy wobec tła, które sklep NAPRAWDĘ
        // ma pod tekstem. Stały stopień 1 dawał teksty dociągnięte do jasności,
        // której na stronie nie było — na ciemniejszym tle wychodziły za jasne.
        $page      = $neutral['steps'][$input['page']];
        $primary   = $this->colourScale($input['primary'], $page);
        $secondary = $this->colourScale($input['secondary'], $page);
        $promo     = $this->colourScale($input['promo'], $page);

        $scales = array_merge(
            $this->flatten('neutral', $neutral),
            $this->flatten('primary', $primary),
            $this->flatten('secondary', $secondary),
            $this->flatten('promo', $promo),
            $this->statusRoles($input['promo'])
        );

        return array_merge(
            $scales,
            $this->bandRoles($settings, $scales, $input['dark']),
            $this->semanticRoles($scales, $input['page'], $input['card'], $input),
            $this->badgeRoles($scales)
        );
    }

    /**
     * Nakłada nadpisania administratora i przelicza role od nich zależne.
     *
     * Samo `array_merge` podmienia jedną rolę i zostawia rodzinę starą: przypięty
     * kolor sukcesu zmieniał plamkę, a tło komunikatu, obrys i tekst zostawały
     * z barwy wyliczonej. Prototyp mówi inaczej — zmiana koloru statusu daje
     * nowe tło, obrys, napis na kolorze i tekst, a zmiana plakietki nowy napis.
     *
     * Rola, którą sklep przypiął osobno, zostaje jego — przeliczenie nie nadpisuje
     * świadomych wyborów.
     *
     * @param array<string,string> $computed  pełna wyliczona paleta
     * @param array<string,string> $overrides nadpisania rola => kolor
     *
     * @return array<string,string>
     */
    public function applyOverrides(array $computed, array $overrides): array
    {
        $out = array_merge($computed, $overrides);

        foreach (array_keys(self::STATUS) as $status) {
            $pin = $overrides['fill-' . $status] ?? null;

            if ($pin === null) {
                continue;
            }

            [$hue, $sat, $lightness] = Color::toHsl($pin);
            $bg = Color::fromHsl($hue, $sat * 0.34, 0.955);

            $rodzina = [
                'surface-' . $status => $bg,
                'border-' . $status  => Color::fromHsl($hue, $sat * 0.52, 0.845),
                'on-fill-' . $status => Color::readableOn($pin),
                'text-' . $status    => Color::adjustToContrast($hue, $sat, $lightness, $bg, 4.5),
            ] + $this->interactiveStates('fill-' . $status, $pin);

            foreach ($rodzina as $rola => $barwa) {
                if (!array_key_exists($rola, $overrides)) {
                    $out[$rola] = $barwa;
                }
            }
        }

        foreach (['new', 'bestseller', 'promo', 'pack', 'sale'] as $nazwa) {
            $pin = $overrides['badge-' . $nazwa] ?? null;

            if ($pin !== null && !array_key_exists('on-badge-' . $nazwa, $overrides)) {
                $out['on-badge-' . $nazwa] = Color::readableOn($pin);
            }
        }

        return $out;
    }

    /**
     * Informacje dla panelu: co silnik skorygował i co budzi wątpliwość.
     *
     * Rozdział jest celowy. Kontrast wewnątrz pary kolorów to problem techniczny bez
     * pola do dyskusji — silnik go rozwiązuje i mówi, że to zrobił. Podobieństwo dwóch
     * kolorów to ocena, w której nie ma jednej poprawnej odpowiedzi — więc tylko
     * ostrzegamy, nigdy nie blokujemy zapisu i nie zmieniamy koloru wybranego świadomie.
     *
     * @param array<string,string> $settings
     *
     * @return array{adjustments:array<string,array{from:string,to:string,contrast:float}>,warnings:string[]}
     */
    public function diagnostics(array $settings): array
    {
        $input = $this->input($settings);
        $adjustments = [];

        foreach (['primary', 'secondary', 'promo'] as $key) {
            $pair = Color::resolveFillPair($input[$key]);

            if ($pair['adjusted']) {
                $adjustments[$key] = [
                    'from'     => $input[$key],
                    'to'       => $pair['fill'],
                    'contrast' => $pair['contrast'],
                ];
            }
        }

        $warnings = [];

        if (Color::hueDistance($input['primary'], $input['promo']) < self::HUE_COLLISION) {
            $warnings[] = 'primary_promo_similar';
        }

        $danger = Color::fromHsl(...self::STATUS['danger']);

        if (Color::hueDistance($input['promo'], $danger) < self::HUE_COLLISION) {
            $warnings[] = 'promo_danger_similar';
        }

        // Kolor dodatkowy pojawia się jako tekst i obrys przycisku konturowego, nie jako
        // wypełnienie — zbyt jasny przestaje być czytelny na białym tle karty.
        if (Color::contrast($input['secondary'], '#ffffff') < 3.0) {
            $warnings[] = 'secondary_too_light';
        }

        return ['adjustments' => $adjustments, 'warnings' => $warnings];
    }

    /**
     * @param array<string,string> $settings
     *
     * @return array{primary:string,secondary:string,dark:string,promo:string}
     */
    private function input(array $settings): array
    {
        return [
            'primary'   => Color::normalize($settings['PRIMARY'] ?? '#c99a3f', '#c99a3f'),
            'secondary' => Color::normalize($settings['SECONDARY'] ?? '#5678bd', '#5678bd'),
            'dark'      => Color::normalize($settings['DARK'] ?? '#262a33', '#262a33'),
            'promo'     => Color::normalize($settings['PROMO'] ?? '#1f8a78', '#1f8a78'),

            // Powierzchnie to NUMER STOPNIA skali neutralnej, nie kod barwy.
            // Zapisany kod zamroziłby tło na odcieniu sprzed zmiany atramentu;
            // stopień idzie za skalą, więc sklep przemalowany na cieplejszy
            // atrament dostaje cieplejsze tło bez wracania do tego ekranu.
            'page' => $this->step($settings['SURFACE_PAGE'] ?? '1', 1),
            'card' => $this->step($settings['SURFACE_CARD'] ?? '3', 3),

            // Kreski: ten sam sposób co powierzchnie. Sklep wskazuje stopień,
            // a nie kod barwy, więc kreska zmienia się razem z paletą.
            'borderCard'  => $this->step($settings['BORDER_CARD'] ?? '5', 5),
            'borderField' => $this->step($settings['BORDER_FIELD'] ?? '6', 6),
            'borderTable' => $this->step($settings['BORDER_TABLE'] ?? '5', 5),

            // Czym pole odpowiada na kursor i na ognisko: atramentem albo barwą
            // marki. Dwie wartości, bo to jest wybór między „nie zwraca uwagi na
            // siebie" a „zwraca", a nie miejsce na trzeci odcień szarości.
            'fieldAccent' => in_array($settings['FIELD_ACCENT'] ?? 'ink', ['ink', 'brand'], true)
                ? $settings['FIELD_ACCENT']
                : 'ink',

            'tint' => array_key_exists($settings['NEUTRAL_TINT'] ?? 'pure', self::TINT)
                ? $settings['NEUTRAL_TINT']
                : 'pure',
        ];
    }

    /**
     * Numer stopnia skali z ustawień, przycięty do dwunastki.
     *
     * @param mixed $value
     */
    private function step($value, int $fallback): int
    {
        $step = (int) $value;

        return $step >= 1 && $step <= 12 ? $step : $fallback;
    }

    // ------------------------------------------------------------------ skale

    /**
     * Skala barwna zakotwiczona na kolorze administratora.
     *
     * Dziewiątka to jego kolor — stopnie 1–8 interpolują jasność od niemal bieli do niego,
     * 10–12 schodzą od niego w dół. Dzięki kotwicy wygenerowana skala zawsze zawiera
     * dokładnie tę barwę, którą wybrał, a nie jej przybliżenie.
     *
     * @return array{steps:array<int,string>,on9:string,adjusted:bool,contrast:float}
     */
    private function colourScale(string $hex, string $page): array
    {
        $pair = Color::resolveFillPair($hex);
        $fill = $pair['fill'];
        $tone = Color::tone($fill);
        $steps = [];

        // Im bliżej bieli, tym mniej nasycenia — inaczej stopień pierwszy, który ma
        // być tłem strony, wychodzi jaskrawym pastelem. Tak zachowuje się rozbielanie
        // farby i tak wyglądają skale, do których oko jest przyzwyczajone.
        for ($i = 1; $i <= 9; $i++) {
            $t = ($i - 1) / 8;
            $steps[$i] = Color::atTone(
                $fill,
                self::TONE_TOP + ($tone - self::TONE_TOP) * $t,
                self::CHROMA_TOP + (1.0 - self::CHROMA_TOP) * ($t ** 0.75)
            );
        }

        // Dolna granica musi leżeć poniżej wypełnienia. Przy bardzo ciemnej marce
        // stała granica jest OD NIEJ jaśniejsza i stopnie 10–12 wyszłyby jaśniejsze
        // od dziewiątki — skala rozjechałaby się w drugą stronę.
        $bottom = min(self::TONE_BOTTOM, $tone - 0.06);

        for ($i = 10; $i <= 12; $i++) {
            $t = ($i - 9) / 3;
            $steps[$i] = Color::atTone($fill, $tone + ($bottom - $tone) * $t, 1.0 - 0.15 * $t);
        }

        $steps[9] = $fill;

        // Stopnie tekstowe to jedyne miejsce, gdzie interpolacja nie wystarcza: przy jasnej
        // marce (żółć, cytryn) wychodzą nieczytelne, więc schodzą do progu WCAG.
        $steps[11] = Color::shiftToContrast($steps[11], $page, 4.5, -0.02);
        $steps[12] = Color::shiftToContrast($steps[12], $page, 7.0, -0.02);

        return [
            'steps'    => $steps,
            'on9'      => $pair['on'],
            'adjusted' => $pair['adjusted'],
            'contrast' => $pair['contrast'],
        ];
    }

    /**
     * Skala neutralna w całości z atramentu: ciemny koniec to on sam, jasny to
     * jego barwa w śladowej ilości.
     *
     * Barwę całej skali bierzemy z atramentu w śladowej ilości, a jasność ze stałej
     * drabinki. Wyjątkiem jest stopień dwunasty: to sam atrament, bo
     * gdyby wychodziły z drabinki jasności, sklep pisałby szarością, której nikt
     * nie wybrał, a sam atrament nie dałby się nigdzie wskazać.
     *
     * @return array{steps:array<int,string>,on9:string,adjusted:bool,contrast:float}
     */
    private function neutralScale(string $ink, string $tint = 'pure'): array
    {
        // Charakter szarości to osobne pytanie w panelu (prototyp: „Szarości" —
        // czyste, ciepłe, chłodne), a nie pochodna atramentu. Sklep z czarnym
        // tekstem może chcieć kremowych teł i odwrotnie.
        [$hue, $sat] = self::TINT[$tint] ?? self::TINT['pure'];
        $steps = [];

        foreach (self::NEUTRAL_L as $step => $lightness) {
            $steps[$step] = Color::fromHsl($hue, $sat, $lightness / 100);
        }

        // Atrament zbyt jasny na tekst schodzi do progu 7:1 wobec tła strony —
        // ten sam sprawdzian, który skala barwna stosuje do swoich stopni tekstowych.
        $atrament = Color::shiftToContrast($ink, $steps[1], 7.0, -0.02);

        // Tylko dwunasty. Dziesiąty i jedenasty to tekst drugoplanowy i kreski —
            // liczone od atramentu robiły się niemal tak ciemne jak on i hierarchia
            // tekstu znikała.
        $steps[12] = $atrament;

        return [
            'steps'    => $steps,
            'on9'      => Color::readableOn($steps[9]),
            'adjusted' => false,
            'contrast' => Color::contrast(Color::readableOn($steps[9]), $steps[9]),
        ];
    }

    /**
     * @param array{steps:array<int,string>,on9:string} $scale
     *
     * @return array<string,string>
     */
    private function flatten(string $name, array $scale): array
    {
        $out = [];

        foreach ($scale['steps'] as $step => $colour) {
            $out[$name . '-' . $step] = $colour;
        }

        $out[$name . '-on-9'] = $scale['on9'];

        return $out;
    }

    /**
     * Statusy zachowują kanoniczny odcień — zieleń zawsze znaczy sukces, więc nie
     * przesuwamy ich w stronę marki. Każdy dostaje pięć ról: tło, obramowanie,
     * wypełnienie, wypełnienie pod kursorem, tekst i parę do wypełnienia.
     *
     * @return array<string,string>
     */
    private function statusRoles(string $promo): array
    {
        $out = [];

        foreach (self::STATUS as $name => [$hue, $sat, $lightness]) {
            $solid = Color::resolveFillPair(Color::fromHsl($hue, $sat, $lightness));
            $bg    = Color::fromHsl($hue, $sat * 0.34, 0.955);

            $out[$name . '-2']    = $bg;
            $out[$name . '-6']    = Color::fromHsl($hue, $sat * 0.52, 0.845);
            $out[$name . '-9']    = $solid['fill'];
            $out[$name . '-10']   = Color::atLightness($solid['fill'], max(0.04, $lightness - 0.07));
            $out[$name . '-11']   = Color::adjustToContrast($hue, $sat, $lightness, $bg, 4.5);
            $out[$name . '-on-9'] = $solid['on'];
        }

        return $out;
    }

    // -------------------------------------------------------------------- pasy

    /**
     * Plakietki handlowe: nowość, bestseller, promocja, pakiet, wyprzedaż.
     *
     * Osobna rodzina od statusów, bo odpowiada na inne pytanie. Status mówi
     * o STANIE SYSTEMU — udało się, nie udało, uwaga. Plakietka wyróżnia TOWAR
     * i nie niesie żadnego uniwersalnego znaczenia. Mieszanie ich kończy się
     * tym, że „Wycofany" dostaje czerwień błędu, choć nic złego się nie stało.
     *
     * Wartości domyślne pochodzą z palety sklepu, nie z listy stałych barw:
     * nowość i pakiet z neutralnych, bestseller z marki, promocja i wyprzedaż
     * z koloru promocji. Napis dobiera `resolveFillPair` — czerń albo biel,
     * zależnie od tego, którą widać lepiej.
     *
     * @param array<string,string> $s
     *
     * @return array<string,string>
     */
    private function badgeRoles(array $s): array
    {
        $zrodla = [
            'new'        => $s['neutral-12'],
            'bestseller' => $s['primary-9'],
            'promo'      => $s['promo-9'],
            'pack'       => $s['neutral-11'],
            'sale'       => $s['promo-9'],
        ];

        $out = [];

        foreach ($zrodla as $nazwa => $fill) {
            $para = $this->paraPlakietki($fill);

            $out['badge-' . $nazwa]    = $para['fill'];
            $out['on-badge-' . $nazwa] = $para['on'];
        }

        return $out;
    }

    /**
     * Pas strony z jednej decyzji: sześć wartości z gwarantowanym kontrastem.
     *
     * @param array<string,string> $settings
     * @param array<string,string> $scales
     *
     * @return array<string,string>
     */
    /**
     * Tekst przygaszony na pasie: ten sam kolor, tylko bliżej tła jasnością.
     *
     * Mieszanie z tłem w RGB ciągnie barwę w stronę tła — na żółtym pasie czerń
     * wychodziła zgniłym złotem. Przesunięcie tonu zostawia barwę tekstu w spokoju.
     */
    private function mutedOn(string $text, string $bg): string
    {
        $kierunek = Color::tone($bg) > Color::tone($text) ? 1.0 : -1.0;

        return Color::shiftTone($text, $kierunek * 0.22);
    }

    /**
     * Odnośnik na pasie: barwa marki tylko wtedy, gdy naprawdę działa.
     *
     * Marka schodząca do progu kontrastu przestaje być marką — żółć przyciemniona
     * do 4,5:1 to brudne złoto. Kiedy nie przechodzi bez poprawek, odnośnik bierze
     * kolor tekstu; rozpoznaje się wtedy po pozycji i po stanie pod kursorem.
     */
    private function linkOn(string $bg, string $text, array $scales): string
    {
        return Color::contrast($scales['primary-9'], $bg) >= 4.5
            ? $scales['primary-9']
            : $text;
    }

    /**
     * Role pasa wyprowadzone z dowolnego tła.
     *
     * `bandRoles()` zna trzy gotowe odpowiedzi — jasny, ciemny, w barwie marki.
     * Pasy topbara wybiera się dziś inaczej: właściciel sklepu wskazuje stopień
     * skali, więc tłem bywa każdy z czterdziestu ośmiu odcieni palety i schematu
     * nie ma jak zgadnąć. Reszta ról musi więc wynikać z policzonego kontrastu,
     * a nie z założenia „to jest ciemne".
     *
     * @param array<string,string> $scales
     *
     * @return array<string,string>
     */
    public function bandFromBackground(string $bg, array $scales): array
    {
        $text = Color::readableOn($bg);
        $link = $this->linkOn($bg, $text, $scales);

        // Czy pas stoi w barwie marki. Nie pytamy o równość kodów, tylko o to, czy
        // barwa marki daje się na nim jeszcze odróżnić — pas ustawiony na odcień
        // obok marki ma się zachować tak samo jak pas ustawiony na nią dokładnie.
        $naMarce = Color::contrast($bg, $scales['primary-9']) < self::STATE_MIN_CONTRAST;

        // Kreska pasa zostaje w barwie pasa: przesuwamy ton tła, zamiast mieszać
        // je z kolorem tekstu. Mieszanie dawało na jasnym pasie szarą obwódkę,
        // widoczną bardziej niż to, co miała oddzielać.
        //
        // Jedno przesunięcie dla każdego pasa znaczy jedną grubość kreski w całym
        // sklepie — na jasnym, ciemnym i na barwie marki tak samo. Stopień skali
        // marki wypadał tu dwa razy mocniej niż kreski pozostałych pasów.
        $kreska = Color::shiftTone($bg, Color::tone($bg) > 0.5 ? -0.05 : 0.07);

        // Barwa marki przestaje być wyróżnieniem, kiedy pas sam ją nosi.
        $wyroznienie = $naMarce ? $scales['neutral-12'] : $scales['primary-9'];

        return [
            'bg'         => $bg,
            'fg'         => $text,
            'fg-muted'   => $this->mutedOn($text, $bg),
            'link'       => $link,
            'link-hover' => Color::shiftTone($link, Color::tone($bg) > 0.5 ? -0.12 : 0.12),
            'border'     => $kreska,
            // Najechanie na akcje pasa.
            //
            // ODSTĘPSTWO OD PROTOTYPU, świadome i na polecenie właściciela sklepu.
            // Prototyp nagłówka (widoki/header/naglowek.html:216) ma tu welon:
            // `color-mix(in srgb, var(--q-band-header-fg) 8%, transparent)`.
            // Na jasnym pasie daje on jasną szarość, a ta ma być ciemna.
            //
            // Stąd pełne odwrócenie: na jasnym pasie tło najechania bierze barwę
            // pisma, a napis barwę tła. Na ciemnym pasie odwrócenie dałoby jasny
            // prostokąt nie do odróżnienia od tła sąsiadów, więc wchodzi barwa
            // marki — tak samo jak zachowuje się przycisk koszyka.
            'hover'      => Color::tone($bg) > 0.5
                ? $text
                : $scales['primary-9'],

            'hover-fg'   => Color::tone($bg) > 0.5
                ? $bg
                : $scales['primary-on-9'],
            // Wypełnienie elementu, który ma się z pasa wyróżniać: przycisk
            // katalogu i koszyka. Gdy pas STOI JUŻ w barwie marki, ta sama barwa
            // na przycisku w nim ginie — wtedy przycisk bierze kolor ciemny
            // sklepu, dokładnie tak jak na pasie ustawionym na „kolor marki".
            'solid'      => $wyroznienie,
            'on-solid'   => Color::resolveFillPair($wyroznienie)['on'],
        ] + $this->fieldOnBand($bg, $scales, $kreska)
          + $this->interactiveStates('solid', $wyroznienie);
    }

    /**
     * Powierzchnia pola formularza leżącego na pasie — wyszukiwarka w nagłówku.
     *
     * Pole na pasie nie może brać tła karty: ten sam pas bywa jasny, ciemny albo
     * w barwie marki, a po przewinięciu zmienia się w locie. Tło karty działałoby
     * w jednym z trzech przypadków, a przy okazji ustawienie „Tło kart"
     * przemalowywałoby wyszukiwarkę, z którą nie ma nic wspólnego.
     *
     * Reguła (makieta, zmierzone na `makiety/strona-produktu.html`): pole jest
     * ZAWSZE kartką — stopniem papieru skali. Na pasie ciemnym i na barwie marki
     * odcina je samo tło, na pasie jasnym odcina je kreska. Odcień ciemniejszy
     * od jasnego nagłówka daje szarą bryłę, której w makiecie nie ma.
     *
     * @param array<string,string> $scales
     *
     * @return array<string,string>
     */
    private function fieldOnBand(string $bg, array $scales, string $border): array
    {
        $tlo = $scales['neutral-3'];
        $napis = Color::readableOn($tlo);

        return [
            'field'             => $tlo,
            'field-fg'          => $napis,
            'field-placeholder' => $this->mutedOn($napis, $tlo),
            // Zakres wyszukiwania stoi wewnątrz pola i ma w makiecie własne,
            // ledwie odróżnialne tło — jeden krok od kartki, nie osobna bryła.
            'field-sunken'      => Color::shiftTone($tlo, -0.02),
            // Kreska pola to kreska pasa: na jasnym nagłówku jest jedynym, co
            // oddziela białe pole od białego tła, więc musi się liczyć od tego
            // tła, a nie od pola.
            'field-border'      => $border,
        ];
    }

    /**
     * Role toasta: tło, napis i akcent każdego rodzaju komunikatu.
     *
     * Akcenty liczone OD TŁA TOASTA, a nie brane wprost ze skal stanów. Skale są
     * dobrane pod jasne tło strony i na ciemnym pudełku część z nich znika —
     * zmierzone na palecie sklepu: „informacja" ma na ciemnym tle kontrast 1,15,
     * a „ostrzeżenie" na jasnym 2,61. Dociągamy je w stronę czytelności, tak samo
     * jak robią to stany przycisków.
     *
     * @param array<string,string> $scales
     *
     * @return array<string,string>
     */
    public function toastTokens(string $wybor, array $scales): array
    {
        $tlo = match ($wybor) {
            'light' => $scales['neutral-1'] ?? '#ffffff',
            'brand' => $scales['primary-9'] ?? '#000000',
            default => $scales['neutral-12'] ?? '#000000',
        };

        $jasneTlo = Color::tone($tlo) > 0.5;

        $out = [
            'toast-bg' => $tlo,
            'toast-text' => Color::readableOn($tlo),
        ];

        foreach (array_keys(self::STATUS) as $rodzaj) {
            $out['toast-accent-' . $rodzaj] = $this->czytelnyNa(
                $tlo,
                $scales[$rodzaj . '-9'] ?? $tlo,
                $jasneTlo ? -0.02 : 0.02
            );
        }

        return $out;
    }

    /**
     * Dociąga kolor, aż odetnie się od podanego tła.
     *
     * Próg 3.0 to wymóg dla elementów nietekstowych: znacznik przy krawędzi ma
     * 3 px, ikona 18 px i oba muszą być widoczne, a nie tylko obecne.
     */
    private function czytelnyNa(string $tlo, string $kolor, float $krok): string
    {
        for ($i = 0; $i < 40 && Color::contrast($tlo, $kolor) < 3.0; $i++) {
            $tone = Color::tone($kolor) + $krok;

            if ($tone <= 0.0 || $tone >= 1.0) {
                break;
            }

            $kolor = Color::atTone($kolor, $tone);
        }

        return $kolor;
    }

    private function bandRoles(array $settings, array $scales, string $dark): array
    {
        $out = [];

        foreach (self::BANDS as $band) {
            $scheme = $settings['BAND_' . strtoupper($band)] ?? ($band === 'header' ? 'light' : 'dark');

            $band_ = match ($scheme) {
                'brand' => $this->brandBand($dark, $scales),
                'light' => $this->lightBand($scales),
                default => $this->darkBand($dark, $scales),
            };

            foreach ($band_ as $role => $colour) {
                $out['band-' . $band . '-' . $role] = $colour;
            }
        }

        return $out;
    }

    /**
     * @param array<string,string> $s
     *
     * @return array<string,string>
     */
    private function lightBand(array $s): array
    {
        return [
            'bg'         => $s['neutral-1'],
            'fg'         => $s['neutral-12'],
            'fg-muted'   => $s['neutral-9'],
            // Odnośnik bierze barwę marki tylko wtedy, gdy ta trzyma próg bez
            // przyciemniania. Przyciemniona żółć to brudne złoto — odrzuciliśmy je
            // przy ustalaniu palety i nie wraca tylnymi drzwiami.
            'link'       => $this->linkOn($s['neutral-1'], $s['neutral-12'], $s),
            'link-hover' => Color::shiftTone($this->linkOn($s['neutral-1'], $s['neutral-12'], $s), -0.12),
            // Pas jasny leży na jasnej stronie, więc granicę niesie wyłącznie ta linia.
            'border'     => $s['neutral-6'],
            // Tło elementu pod kursorem. Powierzchnia strony się tu nie nadaje:
            // ten sam pas bywa ciemny albo w barwie marki, a jasne tło działałoby
            // tylko w jednym z trzech przypadków. Stąd mieszanka z barw pasa.
            'hover'      => Color::mix($s['neutral-1'], $s['neutral-12'], 0.08),
            // Wypełnienie elementu, który ma się z pasa wyróżniać: przycisk katalogu
            // w nawigacji. Na pasie jasnym i ciemnym robi to barwa marki.
            'solid'      => $s['primary-9'],
            'on-solid'   => $s['primary-on-9'],
        ] + $this->fieldOnBand($s['neutral-1'], $s, $s['neutral-6'])
          + $this->interactiveStates('solid', $s['primary-9']);
    }

    /**
     * Pas ciemny: tło wprost z koloru administratora, reszta wyliczona wobec niego.
     * Odnośnik to kolor główny rozjaśniony dokładnie tyle, ile trzeba do czytelności —
     * marka zostaje rozpoznawalna, zamiast zamieniać się w białą etykietę.
     *
     * @param array<string,string> $s
     *
     * @return array<string,string>
     */
    private function darkBand(string $dark, array $s): array
    {
        $pair = Color::resolveFillPair($dark);
        $text = $pair['on'];
        $link = $this->linkOn($dark, $text, $s);

        return [
            'bg'         => $dark,
            'fg'         => $text,
            'fg-muted'   => $this->mutedOn($text, $dark),
            'link'       => $link,
            'link-hover' => Color::shiftTone($link, 0.12),
            'border'     => Color::mix($dark, $text, 0.22),
            'hover'      => Color::mix($dark, $text, 0.10),
            'solid'      => $s['primary-9'],
            'on-solid'   => $s['primary-on-9'],
        ] + $this->fieldOnBand($dark, $s, Color::mix($dark, $text, 0.22))
          + $this->interactiveStates('solid', $s['primary-9']);
    }

    /**
     * @param array<string,string> $s
     *
     * @return array<string,string>
     */
    private function brandBand(string $dark, array $s): array
    {
        $bg   = $s['primary-9'];
        $text = $s['primary-on-9'];

        return [
            'bg'         => $bg,
            'fg'         => $text,
            'fg-muted'   => $this->mutedOn($text, $bg),
            'link'       => $text,
            'link-hover' => Color::shiftTone($text, Color::tone($bg) > 0.5 ? 0.12 : -0.12),
            'border'     => $s['primary-10'],
            'hover'      => Color::mix($bg, $text, 0.12),
            // Pas JEST już w barwie marki, więc wypełnienie w tej samej barwie
            // zniknęłoby w tle, a jej ciemniejszy stopień wygląda jak barwa marki
            // przybrudzona. Przycisk bierze wtedy po prostu kolor ciemny sklepu —
            // ten sam, na którym stoi pas informacyjny i stopka.
            'solid'      => $dark,
            'on-solid'   => Color::resolveFillPair($dark)['on'],
        ] + $this->fieldOnBand($bg, $s, $s['primary-10'])
          + $this->interactiveStates('solid', $dark);
    }

    // ------------------------------------------------------------- semantyka

    /**
     * Warstwa druga: nazwy opisujące rolę, wskazujące na stopnie skali.
     *
     * Rozdział `surface` od `fill` pochodzi z Polaris: surface to powierzchnia, na której
     * coś leży (tekst na niej zwykły), fill to wypełnienie elementu (tekst na nim to
     * osobna rola `on-fill-*`).
     *
     * @param array<string,string> $s
     * @param int $page stopień skali neutralnej wybrany na tło strony
     * @param int $card stopień skali neutralnej wybrany na powierzchnię karty
     *
     * @return array<string,string>
     */
    /**
     * @param array<string,mixed> $input wejście panelu; stąd stopnie kresek
     */
    private function semanticRoles(array $s, int $page = 1, int $card = 3, array $input = []): array
    {
        $tloStrony = $s['neutral-' . $page];
        $tloKarty = $s['neutral-' . $card];

        // Wgłębienie, najechanie i wciśnięcie liczą się OD KARTY, bo to na karcie
        // leżą: szare pudełko pod zdjęciem, pas tabeli, wiersz pod kursorem.
        // Na stałych stopniach 2/4/5 przestawały być czymkolwiek względem karty,
        // gdy sklep przesunął ją ze stopnia trzeciego — „wgłębienie" wychodziło
        // jaśniejsze od powierzchni, w którą miało być wpuszczone.
        //
        // Kierunek bierze się z karty: od jasnej odchodzimy w dół, od ciemnej
        // w górę. Odstępy dobrane tak, żeby od białej karty dały dokładnie te
        // barwy, które wychodziły ze stopni 2, 4 i 5 — sklep przy ustawieniach
        // domyślnych nie zobaczy żadnej zmiany.
        $wDol = Color::tone($tloKarty) > 0.5 ? -1 : 1;

        $roles = [
            'surface-page'    => $tloStrony,
            'surface-sunken'  => Color::shiftTone($tloKarty, $wDol * 0.014),
            'surface-raised'  => $tloKarty,
            'surface-hover'   => Color::shiftTone($tloKarty, $wDol * 0.026),
            'surface-active'  => Color::shiftTone($tloKarty, $wDol * 0.059),

            'text-strong'     => $s['neutral-12'],
            'text-body'       => $s['neutral-12'],
            'text-muted'      => $s['neutral-11'],
            'text-disabled'   => $s['neutral-9'],

            // Kreska dzieli, a nie rysuje — stąd stopnie lżejsze, niż podpowiadałby
            // kontrakt skali. Twardsza linia jest w `border-hover` i w `border-field`,
            // gdzie ma coś zasygnalizować, a nie tylko oddzielić.
            'border-subtle'   => $s['neutral-5'],
            'border'          => $s['neutral-6'],
            'border-hover'    => $s['neutral-7'],

            // Kreski ustawiane przez sklep. Trzy, bo dotyczą trzech różnych
            // rzeczy: bloku, kontrolki i siatki tabeli. Bez tego sklep, który
            // chciał mocniejszej siatki w tabelach, musiał pociemnić wszystko.
            'border-card'     => $s['neutral-' . ($input['borderCard'] ?? 5)],
            'border-input'    => $s['neutral-' . ($input['borderField'] ?? 6)],
            'border-table'    => $s['neutral-' . ($input['borderTable'] ?? 5)],
            // Obramowanie pola bywa jedynym sygnałem, że pole tam jest, więc WCAG 1.4.11
            // wymaga dla niego 3:1 wobec tła. Stopień 7 kontraktu tyle nie daje.
            //
            // Krok schodzenia jest drobny (0,01 zamiast 0,02): pętla zatrzymuje się
            // przy pierwszym przekroczeniu progu, więc grubszy krok przeskakiwał go
            // z zapasem i kreska wychodziła ciemniejsza, niż wymaga tego reguła.
            'border-field'    => Color::shiftToContrast($s['neutral-7'], $tloStrony, 3.0, -0.01),
            'focus-ring'      => $s['primary-7'],

            // Pole pod kursorem i pole z ogniskiem. Reszta kresek dzieli treść,
            // te dwie odpowiadają na działanie klienta, więc idą w drugą stronę
            // skali: z jasnych stopni w mocne.
            //
            // Kursor dostaje stopień słabszy od ogniska, a nie tę samą barwę.
            // Grubość też się różni (kreska kontra pierścień), ale sama grubość
            // nie wystarcza: na liście kilku pól obok siebie różnicę jednego
            // piksela widać dopiero po porównaniu dwóch pól, a różnicę barwy
            // od razu.
            //
            // Barwa marki znaczy tu barwę marki: ten sam stopień, który wypełnia
            // przyciski. Pole podświetlone na kolor przycisku i przycisk obok
            // niego mają wyglądać na tę samą barwę.
            'field-hover'     => $input['fieldAccent'] === 'brand'
                ? $s['primary-9']
                : $s['neutral-10'],
            'field-focus'     => $input['fieldAccent'] === 'brand'
                ? $s['primary-9']
                : $s['neutral-12'],

            // Gwiazdka oceny to BARWA MARKI, nie kolor ostrzeżenia. Bursztyn
            // ostrzeżenia był pożyczką pod konwencję handlową, ale jest w panelu
            // edytowalny — sklep, który przemalował ostrzeżenie na czerwień,
            // dostawał czerwone gwiazdki, choć ocena nie jest ostrzeżeniem.
            //
            // Barwa marki, ale dociągnięta do 3,0 wobec tła karty. Gwiazdka jest
            // mała i ma cienkie ramiona — jasna marka rozlewa się na niej w plamę,
            // z której nie widać, ile ramion jest wypełnionych. Marka trzymająca
            // próg zostaje nietknięta; dociąganie dotyka tylko jasnych.
            'star'            => $this->zeSkaliMarki($s, $tloKarty, 3.0),

            'fill-primary'         => $s['primary-9'],
            'on-fill-primary'      => $s['primary-on-9'],
            'surface-primary'      => $s['primary-3'],
            'surface-primary-active' => $s['primary-5'],
            // Barwa marki jako tekst tylko wtedy, gdy trzyma próg bez przesuwania.
            // Zjeżdżanie w dół skali robi z żółci brudne złoto — wyróżnienie niesie
            // wtedy grubsze pismo, nie barwa.
            'text-primary'         => $this->linkOn($s['neutral-1'], $s['neutral-12'], $s),

            'fill-secondary'       => $s['secondary-9'],
            'on-fill-secondary'    => $s['secondary-on-9'],
            'border-secondary'     => $s['secondary-7'],
            'text-secondary'       => $s['secondary-11'],

            'fill-promo'      => $s['promo-9'],
            'on-fill-promo'   => $s['promo-on-9'],

            // Odnośnik nie ma własnej barwy: bierze kolor tekstu, a rozpoznaje się po
            // pogrubieniu i kresce w barwie marki. Barwa w gęstym akapicie robi cętki,
            // a nasza żółć na kreskę i tak by się nie nadała sama — patrz docs/18.
            // Pod kursorem odnośnik w treści dostaje tło marki; napis na nim bierze
            // wtedy `on-fill-primary`, więc osobnej roli na to nie ma.
            'link'            => $s['neutral-12'],
            'link-hover'      => $s['neutral-11'],
        ];

        // Stany wypełnień liczone osobno dla każdego koloru — patrz `interactiveStates()`.
        foreach (['primary', 'secondary', 'promo'] as $rola) {
            $roles += $this->interactiveStates('fill-' . $rola, $roles['fill-' . $rola]);
        }

        foreach (array_keys(self::STATUS) as $status) {
            $roles['surface-' . $status] = $s[$status . '-2'];
            $roles['border-' . $status]  = $s[$status . '-6'];
            $roles['fill-' . $status]        = $s[$status . '-9'];
            $roles['on-fill-' . $status]     = $s[$status . '-on-9'];
            $roles['text-' . $status]    = $s[$status . '-11'];

            $roles += $this->interactiveStates('fill-' . $status, $s[$status . '-9']);
        }

        return $roles;
    }

    /**
     * Pierwszy stopień skali marki dający wymagany kontrast wobec podłoża.
     *
     * Idziemy OD barwy marki w stronę przeciwną do podłoża i zatrzymujemy się
     * DOKŁADNIE na progu, zamiast skakać do najbliższego stopnia skali. Stopnie
     * są zgrubne: dla żółtej marki pierwszy, który trzyma 3,0, dawał 6,02 —
     * z bursztynu robił się brąz. Marka trzymająca próg zostaje nietknięta.
     *
     * @param array<string,string> $s
     */
    private function zeSkaliMarki(array $s, string $tlo, float $prog): string
    {
        $marka = $s['primary-9'];

        if (Color::contrast($tlo, $marka) >= $prog) {
            return $marka;
        }

        return Color::shiftToContrast($marka, $tlo, $prog, Color::tone($tlo) > 0.5 ? -0.02 : 0.02);
    }

    /**
     * Wypełnienie plakietki i napis na niej.
     *
     * Różni się od `resolveFillPair()` jednym: napis wybiera JASNOŚĆ tła, a nie
     * większy kontrast. Na średniej czerwieni rabatu biel daje 3,60, a czerń 4,74,
     * więc reguła „większy kontrast wygrywa" stawiała czarny napis na czerwonym
     * prostokącie — formalnie poprawny, w czytaniu brudny i wbrew temu, jak
     * plakietka rabatu wygląda w każdym sklepie.
     *
     * Przy ciemnym tle bierzemy biel i DOCIĄGAMY TŁO, żeby próg padł; przy jasnym
     * zostaje czerń. Granica jest na jasności, bo to ona decyduje, czy prostokąt
     * czyta się jako kolorowy (biały napis), czy jako jasny (czarny napis) —
     * żółty bestseller ma zostać żółty, a nie zbrązowieć pod biel.
     *
     * @return array{fill:string,on:string}
     */
    private function paraPlakietki(string $hex): array
    {
        $ink = '#1c1c1c';

        if (Color::tone($hex) >= 0.75) {
            return ['fill' => $hex, 'on' => $ink];
        }

        $fill = $hex;

        for ($i = 0; $i < 40 && Color::contrast($fill, '#ffffff') < 4.5; $i++) {
            $tone = Color::tone($fill) - 0.03;

            if ($tone <= 0.0) {
                break;
            }

            $fill = Color::atTone($fill, $tone);
        }

        return ['fill' => $fill, 'on' => '#ffffff'];
    }

    /**
     * Najechanie i wciśnięcie dla jednego wypełnienia, wraz z kolorem napisu.
     *
     * Trzy decyzje, każda była wcześniej podjęta źle:
     *
     * 1. Stan liczony jest OD KOLORU WYPEŁNIENIA, a nie brany jako kolejny stopień
     *    skali. Stopień skali ma inne zadanie — tło, obramowanie, tekst — i wypadał
     *    o całą jedną trzecią drogi na dno, czyli o wiele za daleko.
     * 2. Kierunek zależy od jasności koloru: jasne przyciemniamy, ciemne rozjaśniamy.
     *    Granatowy przycisk przyciemniony wchodzi w czerń i nie widać, że zareagował.
     * 3. Kolor napisu liczony jest dla KAŻDEGO stanu osobno. Napis wzięty raz, od
     *    koloru spoczynku, potrafił spaść poniżej progu czytelności na stanie
     *    wciśniętym — i spadał, mierzalnie.
     *
     * @return array<string,string>
     */
    private function interactiveStates(string $rola, string $fill): array
    {
        $kroki = Color::tone($fill) > self::TONE_MID
            ? self::STATE_STEPS['jasny']
            : self::STATE_STEPS['ciemny'];

        // Wciśnięcie liczone OD NAJECHANIA, nie od spoczynku. Gdy najechanie zostanie
        // dociągnięte przez `legible()`, liczone osobno od spoczynku wciśnięcie wypadłoby
        // tuż obok niego i dwa stany zlałyby się w jeden.
        $hover = $this->widoczny($fill, $this->legible(Color::shiftTone($fill, $kroki['hover']), $kroki['hover']), $kroki['hover']);
        $active = $this->widoczny(
            $hover,
            $this->legible(Color::shiftTone($hover, $kroki['active'] - $kroki['hover']), $kroki['active']),
            $kroki['active']
        );

        return [
            $rola . '-hover' => $hover,
            'on-' . $rola . '-hover' => Color::labelFor($hover),
            $rola . '-active' => $active,
            'on-' . $rola . '-active' => Color::labelFor($active),
        ];
    }

    /**
     * Dociąga kolor stanu, dopóki różnicy wobec poprzedniego naprawdę nie widać.
     *
     * Sama jasność percepcyjna tu nie wystarcza. Przy samym dole skali jest mocno
     * ściśnięta wobec tego, co potrafi wyświetlić monitor: krok o 0.08 jasności od
     * czerni daje `#020202`, czyli liczbowo dużo, a na ekranie nic. Dlatego pytamy
     * o kontrast dwóch stanów, bo on mierzy to, co widać.
     */
    private function widoczny(string $od, string $kolor, float $kierunek): string
    {
        $krok = $kierunek < 0 ? -0.02 : 0.02;

        for ($i = 0; $i < 30 && Color::contrast($od, $kolor) < self::STATE_MIN_CONTRAST; $i++) {
            $tone = Color::tone($kolor) + $krok;

            if ($tone <= 0.0 || $tone >= 1.0) {
                break;
            }

            $kolor = Color::atTone($kolor, $tone);
        }

        return $kolor;
    }

    /**
     * Dociąga kolor stanu, jeśli utknął w pułapce środka skali.
     *
     * Barwa o jasności około 0.6 jest jednocześnie za ciemna na czarny napis
     * i za jasna na biały — żaden nie osiąga progu. Spoczynek jest przed tym
     * chroniony, bo `resolveFillPair()` poprawia kolor administratora. Stany
     * liczone od niego mogą jednak w tę pułapkę wejść z powrotem.
     *
     * Idziemy dalej W TĘ SAMĄ STRONĘ, w którą szedł stan: najechanie, które
     * miało przyciemnić, ma przyciemnić bardziej, a nie zawrócić. Inaczej
     * hover „ciemniejszy" wyszedłby jaśniejszy od spoczynku.
     */
    private function legible(string $kolor, float $kierunek): string
    {
        $krok = $kierunek < 0 ? -0.02 : 0.02;

        for ($i = 0; $i < 20; $i++) {
            if (Color::contrast($kolor, Color::labelFor($kolor)) >= 4.5) {
                break;
            }

            $tone = Color::tone($kolor) + $krok;

            if ($tone <= 0.0 || $tone >= 1.0) {
                break;
            }

            $kolor = Color::atTone($kolor, $tone);
        }

        return $kolor;
    }
}
