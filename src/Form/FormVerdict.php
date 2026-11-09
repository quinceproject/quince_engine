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

/**
 * Wynik sprawdzenia zgłoszenia formularza.
 *
 * Powód odrzucenia zostaje po naszej stronie — do dziennika i do decyzji, co
 * pokazać. Klientowi nie mówimy, która zapora zadziałała: bot dostałby wtedy
 * instrukcję, co poprawić, a człowiek i tak nie wie, co zrobić z informacją
 * „wypełniłeś formularz za szybko".
 */
final class FormVerdict
{
    public const PASSED = 'ok';
    public const NO_TOKEN = 'no_token';
    public const BAD_TOKEN = 'bad_token';
    public const EXPIRED_TOKEN = 'expired_token';
    public const HONEYPOT = 'honeypot';
    public const TOO_FAST = 'too_fast';
    public const RATE_LIMIT = 'rate_limit';
    public const PROVIDER = 'provider';

    private function __construct(
        public readonly bool $passed,
        public readonly string $reason,
        public readonly ?string $provider = null
    ) {
    }

    public static function ok(): self
    {
        return new self(true, self::PASSED);
    }

    public static function rejected(string $reason, ?string $provider = null): self
    {
        return new self(false, $reason, $provider);
    }

    /** Krótki opis do dziennika sklepu. */
    public function logLine(string $formId): string
    {
        return sprintf(
            'Quince form guard: %s rejected on "%s"%s',
            $this->reason,
            $formId,
            $this->provider !== null ? ' (' . $this->provider . ')' : ''
        );
    }
}
