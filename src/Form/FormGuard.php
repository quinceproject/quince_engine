<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Form;

use Configuration;
use Quince\Engine\Settings\ConfigStore;
use Db;
use Hook;

/**
 * Strażnik formularzy sklepu.
 *
 * Każdy formularz w sklepie — kontakt, zapis do newslettera, pytanie o produkt —
 * dostaje ten sam zestaw zabezpieczeń, zamiast wymyślać go po swojemu:
 *
 *   1. znacznik strony  — dowód, że formularz wyszedł z tego sklepu, a nie ze
 *                         skryptu wysyłającego dane prosto pod adres kontrolera;
 *   2. pułapka          — pole niewidoczne dla człowieka; bot wypełnia wszystko,
 *                         co znajdzie, więc wypełniona pułapka to bot;
 *   3. czas wypełnienia — człowiek potrzebuje kilku sekund, skrypt wysyła od razu;
 *   4. limit powtórzeń  — ten sam adres nie wyśle stu zgłoszeń w minutę.
 *
 * Te cztery zapory działają zawsze i nic nie kosztują odwiedzającego: nie ma
 * zagadki do rozwiązania, nie ma cudzego skryptu, nie ma zgody na ciasteczka.
 *
 * Piąta zapora jest wymienna: moduł captchy zgłasza się na hook HOOK_VERIFY
 * i dostaje ostatnie słowo. Bez takiego modułu formularze działają i są
 * zabezpieczone czwórką powyżej — captcha dokłada warstwę, nie jest warunkiem.
 *
 * Znacznik celowo nie siedzi w sesji: strona sklepu bywa serwowana z pamięci
 * podręcznej i ten sam kawałek HTML-a trafia do wielu osób. Podpisujemy więc
 * czas wydania kluczem sklepu — to działa niezależnie od tego, kto dostał stronę.
 */
final class FormGuard
{
    /** Hook, na którym moduły captchy dostają zgłoszenie do oceny. */
    public const HOOK_VERIFY = 'actionQuinceFormVerify';

    /**
     * Miejsce na zapis dostawcy oceny przy chronionym formularzu, np. „chronione
     * przez reCAPTCHA". Motyw wstawia je razem z polami strażnika; wypełnia je
     * moduł dostawcy, który sam zakłada ten hook.
     */
    public const HOOK_NOTICE = 'displayQuinceFormNotice';

    /** Klucz sklepu, którym podpisujemy znaczniki. Powstaje przy instalacji. */
    private const SECRET = 'QUINCE_FORM_SECRET';

    /** Nazwy pól, które strażnik czyta ze zgłoszenia. */
    public const FIELD_TOKEN = 'q_guard';
    public const FIELD_FORM = 'q_form';
    public const FIELD_TRAP = 'q_website';

    /** Ile znacznik jest ważny. Dwie godziny to długo otwarta karta, nie atak. */
    private const TOKEN_LIFETIME = 7200;

    /** Poniżej tylu sekund od wczytania strony zgłoszenie uznajemy za maszynowe. */
    private const MIN_SECONDS = 2;

    /** Ile zgłoszeń z jednego adresu na jeden formularz w oknie czasu. */
    private const RATE_LIMIT = 8;
    private const RATE_WINDOW = 600;

    /**
     * Pola, które szablon ma wypisać wewnątrz formularza.
     *
     * @return array{token:string, fieldToken:string, fieldForm:string, fieldTrap:string}
     */
    public function fields(): array
    {
        return [
            'token' => $this->issueToken(),
            'fieldToken' => self::FIELD_TOKEN,
            'fieldForm' => self::FIELD_FORM,
            'fieldTrap' => self::FIELD_TRAP,
        ];
    }

    /**
     * Ocena zgłoszenia.
     *
     * @param string               $formId nazwa formularza — do limitu i dziennika
     * @param array<string,mixed>  $values dane z żądania
     */
    public function check(string $formId, array $values, ?string $ip = null): FormVerdict
    {
        $trap = trim((string) ($values[self::FIELD_TRAP] ?? ''));

        if ($trap !== '') {
            return FormVerdict::rejected(FormVerdict::HONEYPOT);
        }

        $token = (string) ($values[self::FIELD_TOKEN] ?? '');

        if ($token === '') {
            return FormVerdict::rejected(FormVerdict::NO_TOKEN);
        }

        $issuedAt = $this->readToken($token);

        if ($issuedAt === null) {
            return FormVerdict::rejected(FormVerdict::BAD_TOKEN);
        }

        $wiek = time() - $issuedAt;

        if ($wiek > self::TOKEN_LIFETIME) {
            return FormVerdict::rejected(FormVerdict::EXPIRED_TOKEN);
        }

        // Ujemny wiek to zegar sklepu przestawiony po wydaniu znacznika — nie wina
        // odwiedzającego, więc przepuszczamy, ale progu czasu już nie liczymy.
        if ($wiek >= 0 && $wiek < self::MIN_SECONDS) {
            return FormVerdict::rejected(FormVerdict::TOO_FAST);
        }

        if ($this->overLimit($formId, $ip)) {
            return FormVerdict::rejected(FormVerdict::RATE_LIMIT);
        }

        return $this->askProviders($formId, $values);
    }

    /**
     * Zapisanie przyjętego zgłoszenia do licznika.
     *
     * Osobno od `check()`, bo liczymy zgłoszenia przyjęte, nie próby: odrzucone
     * przez pułapkę i tak nic nie kosztowały, a wliczone do limitu pozwoliłyby
     * botowi zablokować formularz prawdziwym klientom.
     */
    public function accept(string $formId, ?string $ip = null): void
    {
        $adres = $this->hashIp($ip);

        if ($adres === null) {
            return;
        }

        Db::getInstance()->insert('quince_form_hit', [
            'ip_hash' => pSQL($adres),
            'form_id' => pSQL(substr($formId, 0, 64)),
            'submitted_at' => (int) time(),
        ], false, true, Db::INSERT_IGNORE);

        // Sprzątanie przy okazji zapisu: osobne zadanie w harmonogramie byłoby
        // kolejną rzeczą do skonfigurowania w sklepie.
        Db::getInstance()->delete(
            'quince_form_hit',
            'submitted_at < ' . (int) (time() - self::RATE_WINDOW)
        );
    }

    /** Znacznik: czas wydania i podpis kluczem sklepu. */
    private function issueToken(): string
    {
        $czas = time();

        return $czas . '.' . $this->sign((string) $czas);
    }

    /** Czas wydania znacznika albo null, gdy podpis się nie zgadza. */
    private function readToken(string $token): ?int
    {
        $czesci = explode('.', $token, 2);

        if (count($czesci) !== 2 || !ctype_digit($czesci[0])) {
            return null;
        }

        // hash_equals, a nie ===: porównanie znak po znaku zdradza czasem, ile
        // pierwszych znaków podpisu zgadło się poprawnie.
        if (!hash_equals($this->sign($czesci[0]), $czesci[1])) {
            return null;
        }

        return (int) $czesci[0];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret());
    }

    /**
     * Klucz sklepu. Powstaje przy pierwszym użyciu, żeby moduł zainstalowany
     * wcześniej nie został bez niego.
     */
    private function secret(): string
    {
        $klucz = (string) Configuration::get(self::SECRET);

        if ($klucz === '') {
            $klucz = bin2hex(random_bytes(32));
            ConfigStore::set(self::SECRET, $klucz);
        }

        return $klucz;
    }

    private function overLimit(string $formId, ?string $ip): bool
    {
        return $this->tooOften($formId, self::RATE_LIMIT, self::RATE_WINDOW, $ip);
    }

    /**
     * Czy z tego adresu przyszło już za dużo takich żądań.
     *
     * Ten sam licznik obsługuje formularze i punkty wejścia, które kosztują sklep
     * więcej niż pytającego — na przykład podpowiedzi wyszukiwarki, gdzie każde
     * naciśnięcie klawisza to zapytanie do bazy. Limit i okno podaje wołający,
     * bo „za dużo" znaczy co innego przy wysyłce formularza, a co innego przy
     * pisaniu w polu.
     *
     * Adres nieznany nie blokuje: przy braku adresu nie ma czego liczyć,
     * a odmowa dotknęłaby wszystkich naraz.
     */
    public function tooOften(string $key, int $limit, int $window, ?string $ip = null): bool
    {
        $adres = $this->hashIp($ip);

        if ($adres === null) {
            return false;
        }

        $ile = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'quince_form_hit`
             WHERE ip_hash = "' . pSQL($adres) . '"
               AND form_id = "' . pSQL(substr($key, 0, 64)) . '"
               AND submitted_at > ' . (int) (time() - max(1, $window))
        );

        return $ile >= $limit;
    }

    /**
     * Adres zapisujemy jako skrót, nie jako adres.
     *
     * Do policzenia „ile razy ten sam" skrót wystarcza, a w bazie nie zostaje
     * dana osobowa, której nikt nie potrzebuje po dziesięciu minutach.
     */
    private function hashIp(?string $ip): ?string
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');

        if ($ip === '') {
            return null;
        }

        return substr(hash_hmac('sha256', $ip, $this->secret()), 0, 40);
    }

    /**
     * Ostatnie słowo modułów captchy.
     *
     * Każdy zgłoszony moduł zwraca `true` (przepuszczam), `false` (odrzucam) albo
     * nic (nie mam zdania — np. nie jest skonfigurowany). Jedno „odrzucam"
     * wystarczy: dostawcy nie głosują, tylko strzegą.
     *
     * @param array<string,mixed> $values
     */
    private function askProviders(string $formId, array $values): FormVerdict
    {
        if (!Hook::getIdByName(self::HOOK_VERIFY)) {
            return FormVerdict::ok();
        }

        $odpowiedzi = Hook::exec(
            self::HOOK_VERIFY,
            ['formId' => $formId, 'values' => $values],
            null,
            true
        );

        foreach (is_array($odpowiedzi) ? $odpowiedzi : [] as $modul => $odpowiedz) {
            if ($odpowiedz === false) {
                return FormVerdict::rejected(FormVerdict::PROVIDER, (string) $modul);
            }
        }

        return FormVerdict::ok();
    }
    /** Licznik zgłoszeń odchodzi razem z modułem — bez niego nie ma czego liczyć. */
    public function dropTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'quince_form_hit`'
        );
    }

    /** Sekret podpisujący znacznik strony. Zostawiony w bazie po odinstalowaniu
     *  nie ma już czego chronić, a jest sekretem. */
    public function removeSettings(): void
    {
        Configuration::deleteByName(self::SECRET);
    }

}
