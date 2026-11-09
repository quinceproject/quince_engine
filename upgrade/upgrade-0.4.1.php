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

/**
 * 0.4.1: pole filmów staje w formularzu produktu przy zdjęciach.
 *
 * Bez wskazania miejsca pole lądowało na końcu formularza, którego szablon
 * karty produktu nie wypisuje, więc nie było go widać.
 *
 * @param Module $module
 */
function upgrade_module_0_4_1($module): bool
{
    return $module->registerExtraProperty(new PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinition(
        entityName: 'product',
        propertyName: 'quince_videos',
        type: PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyType::STRING,
        scope: PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyScope::SHOP,
        size: 1000,
        displayFront: true,
        associatedForms: ['product:description.images:after'],
        associatedApis: ['product'],
        labelWording: 'Video links',
        labelDomain: 'Modules.Quinceengine.Admin',
        descriptionWording: 'Addresses of YouTube or Vimeo films, separated by commas. They show up in the gallery next to the photos.',
        descriptionDomain: 'Modules.Quinceengine.Admin'
    )) !== false;
}
