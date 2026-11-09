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

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType;

/**
 * 0.4.2: pole filmów przechodzi na zakres językowy.
 *
 * `product_lang` ma w kluczu głównym i sklep, i język, więc pole językowe daje
 * osobną wartość dla każdej pary. Zakres sklepowy dawał jedną wartość na sklep,
 * wspólną dla wszystkich języków.
 *
 * Zmiana zakresu to inna tabela, więc stara definicja idzie do skasowania razem
 * z wartościami. Robimy to teraz, dopóki pole jest puste.
 *
 * @param Module $module
 */
function upgrade_module_0_4_2($module): bool
{
    $module->unregisterExtraProperty(new ExtraPropertyDefinition(
        entityName: 'product',
        propertyName: 'quince_videos',
        type: ExtraPropertyType::STRING,
        scope: ExtraPropertyScope::SHOP,
        moduleName: 'quince_engine'
    ), true);

    return $module->registerExtraProperty(new ExtraPropertyDefinition(
        entityName: 'product',
        propertyName: 'quince_videos',
        type: ExtraPropertyType::STRING,
        scope: ExtraPropertyScope::LANG,
        size: 1000,
        displayFront: true,
        associatedForms: ['product:description.images:after'],
        associatedApis: ['/products', '/products/{productId}'],
        labelWording: 'Video links',
        labelDomain: 'Modules.Quinceengine.Admin',
        descriptionWording: 'Addresses of YouTube or Vimeo films, separated by commas. They show up in the gallery next to the photos.',
        descriptionDomain: 'Modules.Quinceengine.Admin'
    )) !== false;
}
