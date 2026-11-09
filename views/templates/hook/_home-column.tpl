{**
 * Quince — treść jednej kolumny pasa strony głównej.
 *
 * Wydzielone z `homepage.tpl`, bo kolumna wygląda tak samo, czy stoi sama na
 * pasie, czy w rzędzie z innymi.
 *
 * Znaczniki `q-ps__*` pochodzą z `makiety/strona-glowna.html`. Zajawka
 * produktu, taśma i siatka przychodzą z motywu: ten sam produkt ma wyglądać
 * wszędzie tak samo.
 *
 * Zmienne: $column (kolumna z ustawień), $section (pas), $qBohater (produkt
 * sam na pasie), $qcNaglowek, $qcTytul, $qcIkona, $qcWiecej (nagłówek oddany
 * taśmie), $qIndeks (numer kolumny w pasie, na identyfikator taśmy).
 *}

{if $column.type == 'hook' && $column.hook}

    {* Kolumna gaśnie sama, gdy moduł nic nie wyświetli: pusty hook nie
       zostawia dziury w rzędzie. *}
    {hook h=$column.hook}

{elseif $column.type == 'products' && isset($column.items) && $column.items|@count}

    {if $column.display == 'carousel'}
        {include file='catalog/_partials/product-carousel.tpl'
          qProdukty=$column.items
          qId="home-{$section.id|escape:'html':'UTF-8'}-{$qIndeks|default:1|intval}"
          qNaglowek=$qcNaglowek
          qTytul=$qcTytul
          qIkonaSvg=$qcIkona
          qWiecej=$qcWiecej
          qWiecejNapis=$section.link_label
          qIle=$column.visible
          qStrzalki=true
          qKontener=false}
    {else}
        <div class="q-grid" style="--q-grid-columns: {$column.visible|intval}">
            {foreach from=$column.items item=product}
                {include file='catalog/_partials/miniatures/product.tpl' product=$product}
            {/foreach}
        </div>
    {/if}

{elseif $column.type == 'product' && $column.item}

    {assign var=qProdukt value=$column.item}

    {if $qBohater}

        {* Bohater pasa: jeden produkt na całej szerokości. Nadpis bierze napis
           nagłówka sekcji — krótka etykieta nad nazwą, a nie drugi tytuł. *}
        <div class="q-ps__hero">
            <div class="q-ps__hero-media">
                <a href="{$qProdukt.url}"><img src="{$qProdukt.cover.bySize.large_default.url}" alt="{$qProdukt.name|escape:'html':'UTF-8'}" loading="lazy"></a>
            </div>

            <div>
                {if $section.header && $section.title}
                    <span class="q-ps__over">{$section.title|escape:'html':'UTF-8'}</span>
                {elseif $qProdukt.manufacturer_name}
                    <span class="q-ps__over">{$qProdukt.manufacturer_name|escape:'html':'UTF-8'}</span>
                {/if}

                <h2 class="q-ps__hero-title"><a href="{$qProdukt.url}">{$qProdukt.name|escape:'html':'UTF-8'}</a></h2>

                {if $qProdukt.description_short}
                    <p class="q-ps__hero-lead">{$qProdukt.description_short|strip_tags|truncate:200}</p>
                {/if}

                {if $column.extras|@count && $column.extra_style != 'blocks'}
                    <ul class="q-ps__specs">
                        {foreach from=$column.extras item=extra}
                            <li class="q-ps__spec">{$extra.text|escape:'html':'UTF-8'}</li>
                        {/foreach}
                    </ul>
                {/if}

                <div class="q-ps__buy">
                    <div class="q-ps__price">
                        <span class="q-ps__price-now">{$qProdukt.price}</span>
                        {if $qProdukt.has_discount}<span class="q-ps__price-was">{$qProdukt.regular_price}</span>{/if}
                    </div>

                    <div class="q-ps__actions">
                        {include file='module:quince_engine/views/templates/hook/_home-buy.tpl' qProdukt=$qProdukt qDuzy=true}
                        <a class="q-btn q-btn--secondary q-btn--lg" href="{$qProdukt.url}">{l s='See details' d='Shop.Theme.Actions'}</a>
                    </div>
                </div>
            </div>
        </div>

        {* Argumenty idą pod bohaterem, na całą szerokość pasa: każdy niesie
           wyróżnioną wartość i zdanie pod nią, więc w kolumnie tekstu łamałyby
           się po dwa słowa. *}
        {if $column.extras|@count && $column.extra_style == 'blocks'}
            <div class="q-ps__reasons">
                {foreach from=$column.extras item=extra}
                    <div class="q-ps__reason">{if $extra.lead}<b>{$extra.lead|escape:'html':'UTF-8'}</b>{/if}{$extra.text|escape:'html':'UTF-8'}</div>
                {/foreach}
            </div>
        {/if}

    {else}

        {* Duża zajawka: zdjęcie nad nazwą albo obok niej. *}
        <article class="q-ps__big q-ps__big--{if $column.image == 'beside'}side{else}stack{/if}">
            <div class="q-ps__big-media">
                <a href="{$qProdukt.url}"><img src="{$qProdukt.cover.bySize.large_default.url}" alt="{$qProdukt.name|escape:'html':'UTF-8'}" loading="lazy"></a>
            </div>

            <div class="q-ps__big-body">
                {if $qProdukt.manufacturer_name}
                    <span class="q-ps__big-brand">{$qProdukt.manufacturer_name|escape:'html':'UTF-8'}</span>
                {/if}

                <h3 class="q-ps__big-title"><a href="{$qProdukt.url}">{$qProdukt.name|escape:'html':'UTF-8'}</a></h3>

                {if $qProdukt.description_short}
                    <p class="q-ps__big-lead">{$qProdukt.description_short|strip_tags|truncate:120}</p>
                {/if}

                <div class="q-ps__buy">
                    <div class="q-ps__price">
                        <span class="q-ps__price-now">{$qProdukt.price}</span>
                        {if $qProdukt.has_discount}<span class="q-ps__price-was">{$qProdukt.regular_price}</span>{/if}
                    </div>

                    <div class="q-ps__actions">
                        {include file='module:quince_engine/views/templates/hook/_home-buy.tpl' qProdukt=$qProdukt qDuzy=false}
                    </div>
                </div>
            </div>
        </article>

    {/if}

{elseif $column.type == 'categories' && isset($column.tiles) && $column.tiles|@count}

    {* Kafel działu: zdjęcie w tle, a na nim nazwa, zdanie i przycisk. *}
    {assign var=qNapis value=$column.button}
    {if !$qNapis}{assign var=qNapis value={l s='See section' d='Modules.Quinceengine.Shop'}}{/if}

    <div class="q-ps__cats" style="--q-cats: {$column.per_row|default:3|intval}">
        {foreach from=$column.tiles item=tile}
            <a class="q-ps__cat q-ps__cat--{if $column.direction == 'vertical'}portrait{else}landscape{/if}"
               href="{$tile.url}"{if $tile.image} style="--q-cat-photo: url('{$tile.image|escape:'html':'UTF-8'}')"{/if}>
                <h3 class="q-ps__cat-title">{$tile.name|escape:'html':'UTF-8'}</h3>
                {if $tile.lead}<p class="q-ps__cat-lead">{$tile.lead|escape:'html':'UTF-8'}</p>{/if}
                <span class="q-btn q-btn--primary">{$qNapis|escape:'html':'UTF-8'}</span>
            </a>
        {/foreach}
    </div>

{/if}
