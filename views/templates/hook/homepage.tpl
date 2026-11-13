{**
 * Quince: strona główna złożona z pasów.
 *
 * Znaczniki przeniesione z `makiety/strona-glowna.html`. Pas, układ kolumn,
 * kafel działu, duża zajawka i bohater pasa to `q-ps__*` prosto z makiety.
 * Zajawka produktu, taśma, cena i przycisk przychodzą z motywu.
 *
 * Szerokość kolumny liczy się w polach siatki sklepu, nie w ułamkach pasa:
 * pole wyznaczamy raz, od szerokości całego rzędu, więc zajawka w kolumnie ma
 * dokładnie tę samą szerokość co zajawka na listingu.
 *
 * Ustawienia: `Wygląd → Quince → Strona główna`.
 *}

{foreach from=$quince_sections item=section}

    {* ── tło pasa ──────────────────────────────────────────────────────
       Nazwa stopnia palety („primary-9") zamienia się na odwołanie do
       zmiennej, kod szesnastkowy wchodzi wprost. *}
    {capture name=tlo}
        {if $section.skin == 'gradient'}
            {if $section.skin_from}--q-ps-from: {if $section.skin_from|substr:0:1 == '#'}{$section.skin_from}{else}var(--q-{$section.skin_from}){/if};{/if}
            {if $section.skin_to}--q-ps-to: {if $section.skin_to|substr:0:1 == '#'}{$section.skin_to}{else}var(--q-{$section.skin_to}){/if};{/if}
            --q-ps-angle: {$section.skin_angle|intval}deg;
        {elseif $section.skin == 'photo' && $section.skin_image}
            --q-ps-photo: url('{$section.skin_image|escape:'html':'UTF-8'}');
        {/if}
    {/capture}

    {* ── co pas pokazuje ───────────────────────────────────────────────
       Dwa układy oddają nagłówek pasa swojej treści, bo ta niesie go lepiej:
       taśma stawia tytuł w jednym rzędzie ze strzałkami, a bohater pasa robi
       z niego nadpis nad nazwą produktu. Drugi nagłówek nad nimi zostawiałby
       pas pustego miejsca. *}
    {assign var=qTasmaSama value=false}
    {if $section.columns|@count == 1 && $section.columns[0].type == 'products' && $section.columns[0].display == 'carousel'}
        {assign var=qTasmaSama value=true}
    {/if}

    {assign var=qBohater value=false}
    {if $section.columns|@count == 1 && $section.columns[0].type == 'product'}
        {assign var=qBohater value=true}
    {/if}

    {* Smarty nie liczy wyrażeń warunkowych w parametrach `{include}`, więc to,
       co dostaje taśma, składamy tutaj. *}
    {assign var=qcNaglowek value=false}
    {assign var=qcTytul value=''}
    {assign var=qcIkona value=''}
    {assign var=qcWiecej value=''}

    {if $qTasmaSama && $section.header}
        {assign var=qcNaglowek value=true}
        {assign var=qcTytul value=$section.title}
        {assign var=qcIkona value=$section.icon_svg}
        {assign var=qcWiecej value=$section.link}
    {/if}

    <section class="q-ps q-ps--{$section.skin|escape:'html':'UTF-8'}{if $section.width == 'boxed'} q-ps--boxed{/if}{if $section.bleed} q-ps--bleed{/if}"
             {if $smarty.capture.tlo|trim}style="{$smarty.capture.tlo|strip|escape:'html':'UTF-8'}"{/if}>
        <div class="{if !$section.bleed}q-container {/if}q-ps__in">

            {if $section.header && $section.title && !$qTasmaSama && !$qBohater}
                <div class="q-ps__head">
                    <h2 class="q-section-title">
                        {if $section.icon_svg}
                            <svg class="q-section-title__ico" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">{$section.icon_svg nofilter}</svg>
                        {/if}
                        {* Napis prowadzi tam, co odnośnik obok. Na telefonie
                           odnośnik znika i nagłówek zostaje jedynym wyjściem
                           do pełnej listy. *}
                        {if $section.link}
                            <a class="q-ps__title-link" href="{$section.link|escape:'html':'UTF-8'}">{$section.title|escape:'html':'UTF-8'}</a>
                        {else}
                            {$section.title|escape:'html':'UTF-8'}
                        {/if}
                    </h2>

                    {if $section.link}
                        <a class="q-ps__more" href="{$section.link|escape:'html':'UTF-8'}">
                            {if $section.link_label}{$section.link_label|escape:'html':'UTF-8'}{else}{l s='See all' d='Modules.Quinceengine.Shop'}{/if}
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708"/></svg>
                        </a>
                    {/if}
                </div>
            {/if}

            {* Jedna kolumna zajmuje cały pas, więc nie potrzebuje rzędu.
               Przy kilku kolumnach rząd wyznacza pole siatki i każda kolumna
               bierze tyle pól, ile wskazał sprzedawca. *}
            {if $section.columns|@count == 1}
                {assign var=column value=$section.columns[0]}
                {include file='module:quince_engine/views/templates/hook/_home-column.tpl'
                  column=$column section=$section qBohater=$qBohater qIndeks=1
                  qcNaglowek=$qcNaglowek qcTytul=$qcTytul qcIkona=$qcIkona qcWiecej=$qcWiecej}
            {else}
                {* Ile przerw jest w rzędzie. Przy podziale procentowym udziały
                   sumują się do stu procent, a przerwy dochodzą ponad to, więc
                   każda kolumna musi oddać swoją część ich szerokości. *}
                {assign var=qLuki value=($section.columns|@count - 1)}

                <div class="q-ps__mix">
                    {foreach from=$section.columns item=column name=kol}
                        {assign var=qRail value=($column.type == 'products' && $column.display == 'carousel')}

                        <div class="{if $qRail}q-ps__mix-rail{else}q-ps__mix-fixed{/if}"
                             style="--q-span: {$column.parts|default:1|intval}{if $section.mode == 'percent'};--q-udzial: calc({$column.percent|intval}% - var(--q-space-md) * {$qLuki} * {$column.percent|intval} / 100){/if}">
                            {include file='module:quince_engine/views/templates/hook/_home-column.tpl'
                              column=$column section=$section qBohater=false
                              qIndeks=$smarty.foreach.kol.iteration
                              qcNaglowek=false qcTytul='' qcIkona='' qcWiecej=''}
                        </div>
                    {/foreach}
                </div>
            {/if}

        </div>
    </section>
{/foreach}
