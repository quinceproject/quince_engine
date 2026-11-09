/**
 * Ekran „Układ listingu" — to, czego nie obsługuje wspólny `arrangement.js`.
 *
 * Dwie rzeczy: wybór miejsca filtrów razem ze skutkiem podanym liczbami oraz
 * tabela miejsc promocyjnych w siatce, spisywana do pola formularza przy
 * wysyłce, tak samo jak rozstawienie elementów.
 */
(function () {
    'use strict';

    var formularz = document.getElementById('quince-listing');

    if (!formularz) {
        return;
    }

    var lista = formularz.querySelector('[data-miejsca-lista]');

    /**
     * Pusty wiersz tabeli miejsc.
     *
     * Powstaje z kopii wiersza, który już stoi na ekranie, a nie z napisanego
     * tutaj HTML-a: pola wyboru niosą wartości z silnika i przepisane tutaj
     * rozjechałyby się przy pierwszej zmianie katalogu.
     */
    function nowyWiersz() {
        var wzor = lista.querySelector('[data-miejsce]');

        if (!wzor) {
            return null;
        }

        var wiersz = wzor.cloneNode(true);

        wiersz.querySelector('[data-pole="position"]').value = '0';
        wiersz.querySelector('[data-pole="hook"]').value = '';
        wiersz.querySelector('[data-pole="type"]').selectedIndex = 0;
        wiersz.querySelector('[data-pole="scope"]').selectedIndex = 0;
        wiersz.querySelector('[data-pole="active"] input[type="checkbox"]').checked = true;

        return wiersz;
    }

    formularz.addEventListener('click', function (zdarzenie) {
        var cel = zdarzenie.target.closest('button');

        if (!cel) {
            return;
        }

        /* Miejsce filtrów. Skutek pokazujemy od razu, bo liczba zajawek w rzędzie
           zmienia się razem z tym wyborem, a bez tego widać ją dopiero na sklepie. */
        if (cel.dataset.filtry) {
            formularz.querySelectorAll('[data-filtry]').forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', przycisk === cel);
            });
            formularz.querySelector('[name="FILTERS"]').value = cel.dataset.filtry;

            var kolumnowe = cel.dataset.filtry === 'column';
            var skutekKolumny = formularz.querySelector('[data-skutek-kolumny]');
            var skutekPelny = formularz.querySelector('[data-skutek-pelny]');

            if (skutekKolumny) { skutekKolumny.hidden = !kolumnowe; }
            if (skutekPelny) { skutekPelny.hidden = kolumnowe; }

            /* Styl bloku dla panelu filtrów ma sens tylko w kolumnie: tylko tam
               panel jest blokiem z własnym tłem. */
            var wygladFiltry = formularz.querySelector('[data-wyglad-strefy="filters"]');

            if (wygladFiltry) { wygladFiltry.hidden = !kolumnowe; }

            return;
        }

        /* Rodzaj stronicowania. Przy dokładaniu opis stoi na całej liście,
           więc jego włącznik „tylko na pierwszej stronie" przestaje cokolwiek
           znaczyć i mówimy o tym od razu. */
        if (cel.dataset.stronicowanie) {
            formularz.querySelectorAll('[data-stronicowanie]').forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', przycisk === cel);
            });
            formularz.querySelector('[name="PAGINATION"]').value = cel.dataset.stronicowanie;

            var skutek = formularz.querySelector('[data-skutek-doladowanie]');

            if (skutek) { skutek.hidden = cel.dataset.stronicowanie !== 'more'; }

            return;
        }

        /* Które widoki listy sklep daje. Przy jednym nie ma czego wybierać
           na wejściu, więc pole widoku domyślnego chowa się razem z nim. */
        if (cel.dataset.widokTryb) {
            formularz.querySelectorAll('[data-widok-tryb]').forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', przycisk === cel);
            });
            formularz.querySelector('[name="VIEW_MODE"]').value = cel.dataset.widokTryb;

            var domyslny = formularz.querySelector('[data-widok-domyslny]');

            if (domyslny) {
                domyslny.hidden = cel.dataset.widokTryb !== 'both';
            }

            return;
        }

        /* Widok, który klient zobaczy na wejściu. */
        if (cel.dataset.widok) {
            formularz.querySelectorAll('[data-widok]').forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', przycisk === cel);
            });
            formularz.querySelector('[name="VIEW"]').value = cel.dataset.widok;

            return;
        }

        if (cel.hasAttribute('data-dodaj-miejsce-promo')) {
            var wiersz = nowyWiersz();

            if (wiersz) {
                lista.appendChild(wiersz);
                wiersz.querySelector('[data-pole="hook"]').focus();
            }

            return;
        }

        if (cel.hasAttribute('data-usun-miejsce')) {
            var doUsuniecia = cel.closest('[data-miejsce]');

            /* Ostatni wiersz zostaje jako pusty wzór: bez niego nie byłoby
               z czego zrobić następnego, a przycisk „Dodaj" przestałby działać. */
            if (doUsuniecia && lista.querySelectorAll('[data-miejsce]').length > 1) {
                doUsuniecia.remove();
            } else if (doUsuniecia) {
                doUsuniecia.querySelector('[data-pole="hook"]').value = '';
                doUsuniecia.querySelector('[data-pole="active"] input[type="checkbox"]').checked = false;
            }
        }
    });

    /* Miejsca spisujemy przy wysyłce, tak jak rozstawienie: stan czytamy wprost
       z drzewa, więc dokładanie i kasowanie wierszy nie musi niczego pilnować.
       Wiersz bez nazwy hooka wypada — nie ma czego wyświetlić. */
    formularz.addEventListener('submit', function () {
        var miejsca = [];

        formularz.querySelectorAll('[data-miejsce]').forEach(function (wiersz, i) {
            var hook = wiersz.querySelector('[data-pole="hook"]').value.trim();

            if (hook === '') {
                return;
            }

            miejsca.push({
                id: 'slot_' + i,
                position: parseInt(wiersz.querySelector('[data-pole="position"]').value, 10) || 0,
                hook: hook,
                type: wiersz.querySelector('[data-pole="type"]').value,
                scope: wiersz.querySelector('[data-pole="scope"]').value,
                views: wiersz.querySelector('[data-pole="views"]').value,
                active: wiersz.querySelector('[data-pole="active"] input[type="checkbox"]').checked
            });
        });

        formularz.querySelector('[name="PLACEMENTS"]').value = JSON.stringify(miejsca);
    });
}());
