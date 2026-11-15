<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Ui;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Adres wpisany w panelu, zanim trafi do `href` na froncie.
 *
 * Przepuszcza `http:`, `https:`, `mailto:`, `tel:` i adresy bez schematu
 * (`/`, `#`, `?`, ścieżka). Każdy inny schemat (`javascript:`, `data:`, `vbscript:`)
 * zamienia na pusty napis: kliknięcie takiego odnośnika uruchomiłoby kod
 * u klienta sklepu.
 */
final class SafeUrl
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function clean(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        // Przeglądarka dekoduje encje w atrybucie i pomija znaki sterujące
        // oraz odstępy w schemacie, więc `&#106;ava&Tab;script:` też jest
        // `javascript:`. Sprawdzamy postać po tych samych krokach.
        $check = $url;

        for ($i = 0; $i < 3; ++$i) {
            $decoded = html_entity_decode($check, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $check) {
                break;
            }

            $check = $decoded;
        }

        $check = preg_replace('/[\x00-\x20\x7F\p{Z}]+/u', '', $check);

        // Nieprawidłowy UTF-8: nie da się ocenić, więc nie zapisujemy.
        if ($check === null) {
            return '';
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $check, $m)) {
            return in_array(strtolower($m[1]), self::ALLOWED_SCHEMES, true) ? $url : '';
        }

        return $url;
    }
}
