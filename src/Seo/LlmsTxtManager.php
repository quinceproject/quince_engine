<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Seo;

use Quince\Engine\Settings\BrandSchemaSettings;

/**
 * Treść `llms.txt`, liczona na żądanie.
 *
 * Instalacja może mieć kilka sklepów i kilka języków, a część sklepów dzieli
 * ten sam katalog na dysku (`virtual_uri`). Zapisany plik byłby jeden dla
 * wszystkich, dlatego `llms.txt` NIE jest zapisywany na dysk. Serwuje go
 * `QuinceEngineLlmstxtModuleFrontController` pod adresem `/llms.txt`,
 * zarejestrowanym hookiem `moduleRoutes` (PS liczy tę trasę osobno dla
 * każdego sklepu i każdego jego języka, patrz `Dispatcher::loadRoutes()`).
 */
final class LlmsTxtManager
{
    public function __construct(private readonly BrandSchemaSettings $settings)
    {
    }

    public function generate(int $idLang): string
    {
        $title = $this->settings->llmsTitleFor($idLang);
        $summary = $this->settings->llmsSummaryFor($idLang);

        $lines = ['# ' . $title, ''];

        if ($summary !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $summary) ?: [] as $linia) {
                $lines[] = '> ' . $linia;
            }
            $lines[] = '';
        }

        foreach ($this->settings->llmsSectionsFor($idLang) as $sekcja) {
            $lines[] = '## ' . $sekcja['heading'];

            foreach ($sekcja['items'] as $pozycja) {
                $wpis = '- [' . $pozycja['title'] . '](' . $pozycja['url'] . ')';
                if ($pozycja['note'] !== '') {
                    $wpis .= ': ' . $pozycja['note'];
                }
                $lines[] = $wpis;
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }
}
