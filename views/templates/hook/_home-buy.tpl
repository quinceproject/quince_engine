{**
 * Quince: przycisk zakupu przy dużej zajawce strony głównej.
 *
 * Wkłada produkt do koszyka tak jak na listingu: ten sam formularz, ta sama
 * nazwa akcji, ten sam napis.
 *
 * Produkt, którego nie da się kupić z listy (wariantowy, na zamówienie albo
 * wyprzedany), prowadzi na kartę, gdzie klient zobaczy powód.
 *
 * Zmienne: $qProdukt (zajawka z presentera), $qDuzy (przycisk w rozmiarze
 * bohatera pasa).
 *}

{if $qProdukt.add_to_cart_url}
    <form action="{$urls.pages.cart}" method="post">
        <input type="hidden" value="{$qProdukt.id_product}" name="id_product">
        {if $qProdukt.id_product_attribute}
            <input type="hidden" value="{$qProdukt.id_product_attribute}" name="id_product_attribute">
        {/if}
        <input type="hidden" name="token" value="{$static_token}">

        <button type="submit" class="q-btn q-btn--primary{if $qDuzy|default:false} q-btn--lg{/if}"
                data-button-action="add-to-cart" data-ps-ref="add-to-cart"
                aria-label="{l s='Add to cart %product_name%' sprintf=['%product_name%' => $qProdukt.name] d='Shop.Theme.Actions'}">
            {* Ten sam napis, co na karcie listingu. *}
            {l s='To cart' d='Shop.Theme.Actions'}
        </button>
    </form>
{else}
    <a class="q-btn q-btn--primary{if $qDuzy|default:false} q-btn--lg{/if}" href="{$qProdukt.url}">
        {l s='See details' d='Shop.Theme.Actions'}
    </a>
{/if}
