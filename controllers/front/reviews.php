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

use Quince\Engine\Product\Reviews;

/**
 * Kolejna porcja opinii o produkcie.
 *
 * Pierwsza porcja stoi w kodzie strony, tę dokłada przycisk „Pokaż wszystkie
 * opinie". Odpowiedź niesie gotowe znaczniki, a nie dane: pojedynczą opinię
 * rysuje ten sam szablon motywu, co przy pierwszej porcji, więc opinia
 * doładowana wygląda tak samo jak ta wypisana przez serwer. Rysowanie ich
 * drugi raz w JavaScripcie rozjechałoby się przy pierwszej zmianie wyglądu.
 */
class Quince_EngineReviewsModuleFrontController extends ModuleFrontController
{
    /** @var bool nie renderujemy strony, tylko JSON */
    public $ajax = true;

    public function postProcess(): void
    {
        $this->ajax = true;
    }

    public function displayAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode($this->porcja());
        exit;
    }

    /** @return array<string,mixed> */
    private function porcja(): array
    {
        $idProduct = (int) Tools::getValue('id_product');
        $strona = (int) Tools::getValue('page');

        $porcja = Reviews::forProduct($idProduct, $strona);

        if ($porcja === null) {
            return ['ok' => false, 'html' => '', 'hasMore' => false];
        }

        return [
            'ok' => true,
            'html' => $this->znaczniki($porcja['items']),
            'hasMore' => $porcja['hasMore'],
            'page' => $porcja['page'],
        ];
    }

    /**
     * Opinie zamienione na znaczniki szablonem motywu.
     *
     * @param array<int,array<string,mixed>> $opinie
     */
    private function znaczniki(array $opinie): string
    {
        $szablon = _PS_THEME_DIR_ . 'templates/catalog/_partials/product-review.tpl';

        if (!is_file($szablon)) {
            return '';
        }

        $wynik = '';

        // Przydatność podajemy jawnie: szablon nie ma tu danych strony, z których
        // czyta ją pierwsza porcja.
        $this->context->smarty->assign('qPrzydatnosc', Reviews::usefulnessEnabled());

        foreach ($opinie as $opinia) {
            $this->context->smarty->assign('qOpinia', $opinia);
            $wynik .= $this->context->smarty->fetch($szablon);
        }

        return $wynik;
    }
}
