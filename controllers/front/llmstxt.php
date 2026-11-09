<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use Quince\Engine\Seo\LlmsTxtManager;
use Quince\Engine\Settings\BrandSchemaSettings;

/**
 * Serwuje `/llms.txt` na żywo — liczony przy każdym żądaniu z ustawień
 * bieżącego sklepu i języka odwiedzającego, nigdy nie zapisywany na dysk.
 *
 * Powód: ta instalacja ma kilka sklepów, a część z nich dzieli jeden
 * fizyczny katalog (`virtual_uri`) — zapisany plik byłby więc wspólny dla
 * wszystkich naraz. Trasa `/llms.txt` rejestruje się osobno dla każdego
 * sklepu i każdego jego języka przez hook `moduleRoutes`
 * (`quince_engine.php::hookModuleRoutes()`), więc PrestaShop sam kieruje
 * żądanie do właściwego kontekstu — ten kontroler nie musi niczego zgadywać.
 */
class Quince_EngineLlmstxtModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        $settings = new BrandSchemaSettings();
        $llms = new LlmsTxtManager($settings);

        header('Content-Type: text/plain; charset=utf-8');
        echo $llms->generate((int) $this->context->language->id);
        exit;
    }
}
