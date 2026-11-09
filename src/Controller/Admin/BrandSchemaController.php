<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Engine\Controller\Admin;

use Configuration;
use Context;
use Quince\Engine\Seo\LlmsTxtManager;
use Quince\Engine\Settings\BrandSchemaSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brand & Schema: dane organizacji dla Schema.org, domyślne meta i llms.txt.
 *
 * `llms.txt` nie jest tu zapisywany na dysk — serwuje go
 * `Quince_EngineLlmstxtModuleFrontController` na żywo, pod `/llms.txt`,
 * osobno dla każdego sklepu i każdego jego języka. Ten ekran tylko
 * ustawia treść i pokazuje jej podgląd.
 */
class BrandSchemaController extends QuinceController
{
    public function __construct(
        private readonly BrandSchemaSettings $settings,
        private readonly LlmsTxtManager $llms
    ) {
    }

    public function indexAction(): Response
    {
        $ustawienia = $this->settings->all();
        $languages = $this->languages();
        $idLangDomyslny = (int) Configuration::get('PS_LANG_DEFAULT');

        $ctx = Context::getContext();
        $llmsUrl = $ctx && $ctx->shop ? rtrim($ctx->shop->getBaseURL(true), '/') . '/llms.txt' : '/llms.txt';

        return $this->renderPanel('@Modules/quince_engine/views/templates/admin/brand-schema.html.twig', [
            'settings' => $ustawienia,
            'sameAs' => $this->settings->sameAs(),
            'translations' => $this->settings->translations(),
            'llmsSections' => $this->settings->llmsSections(),
            'languages' => $languages,
            'jsonldPreview' => $this->settings->organizationJsonLd(),
            'llmsPreview' => $this->llms->generate($idLangDomyslny),
            'llmsUrl' => $llmsUrl,
            'shopName' => (string) Configuration::get('PS_SHOP_NAME'),
            'layoutTitle' => 'Brand & Schema',
            'quinceRoute' => 'quince_brand_schema',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_brand_schema_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quinceengine.Admin'));

            return $this->redirectToRoute('quince_brand_schema');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceengine.Admin'));

        return $this->redirectToRoute('quince_brand_schema');
    }
}
