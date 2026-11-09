/**
 * Ekrany, na których elementy rozstawia się przeciąganiem: karta produktu
 * i listing.
 *
 * Obsługuje to, czego nie da się zrobić samym HTML-em: rozwijanie szczegółów
 * elementu i wyglądu karty, dokładanie wierszy i miejsc, usuwanie hooków oraz
 * spisanie rozstawienia do pola formularza przed wysłaniem.
 *
 * Formularz znajdujemy po znaczniku, a nie po identyfikatorze: mechanika jest
 * ta sama na obu ekranach, a drugi skrypt z tą samą treścią rozjechałby się
 * przy pierwszej poprawce. Gałęzie, których dany ekran nie ma — wybór układu,
 * szerokość panelu zakupu, koło zębate wyglądu — po prostu się nie odpalają,
 * bo nie ma tam takich przycisków.
 *
 * Przestawianie kolejności robi Alpine (`x-sort` z paczki alpine-sort), tak samo
 * jak w stopce i w nawigacji — nie dokładamy do tego drugiego mechanizmu.
 */
(function () {
    'use strict';

    var formularz = document.querySelector('[data-quince-uklad]');

    if (!formularz) {
        return;
    }

    /* Napisy bierzemy z ekranu, a nie z kodu: inaczej powstalby drugi zestaw
       tekstow poza tlumaczeniem panelu. */
    var napisy = formularz.dataset;

    /**
     * Pole na nazwe hooka, wstawiane w miejsce, ktore ma powstac.
     *
     * Wczesniej stala tu martwa zaslepka z napisem „wybierz element, hook albo
     * przycisk". Nie robila nic — kliknieta nie otwierala niczego, a przy zapisie
     * i tak wypadala, bo nie miala wlasnego przelacznika. Obiecywala tez trzeci
     * rodzaj miejsca, ktorego w tym modelu nie ma: przyciski dokladamy jako hooki
     * albo jako elementy z modulu, nigdy jako osobny byt.
     */
    function poleHooka() {
        return '<div class="qc-miejsce qc-miejsce--nowe">' +
            '<input type="text" class="qc-field-base qc-field-sm qc-focusable" data-nowy-hook ' +
            'placeholder="' + (napisy.napisNazwaHooka || '') + '">' +
            /* Przycisk z napisem, nie znaczek: „Add" mowi, co sie stanie,
               a ptaszek trzeba zgadnac. Ten sam napis co przy dodawaniu wiersza. */
            '<button type="button" class="qc-btn qc-btn-sm" data-zatwierdz>' +
            (napisy.napisZatwierdz || '') + '</button>' +
            '<button type="button" class="qc-ikonka" data-poniechaj title="' +
            (napisy.napisPoniechaj || '') + '">&times;</button>' +
            '</div>';
    }

    /** Pokazuje albo chowa blok; zwraca stan po zmianie. */
    function przelacz(element) {
        var kryty = element.hasAttribute('hidden');

        if (kryty) {
            element.removeAttribute('hidden');
        } else {
            element.setAttribute('hidden', '');
        }

        return kryty;
    }

    /**
     * Licznik „x / y" siedzi w nagłówku obszaru, w którym stoi przełącznik.
     * Szukamy go w górę drzewa, bo obszar nie ma własnej klasy pojemnika.
     */
    function policz(wezel) {
        var el = wezel.parentElement;

        while (el && el !== document.body) {
            if (el.firstElementChild && el.firstElementChild.classList.contains('qc-obszar-glowa')) {
                break;
            }
            el = el.parentElement;
        }

        if (!el) {
            return;
        }

        var licznik = el.querySelector('.qc-obszar-glowa .qc-licznik');

        if (!licznik) {
            return;
        }

        var pola = el.querySelectorAll('.qc-miejsca input[type="checkbox"]');
        var wlaczone = el.querySelectorAll('.qc-miejsca input[type="checkbox"]:checked');

        licznik.textContent = wlaczone.length + ' / ' + pola.length;
    }

    /** Gotowe miejsce z hookiem: chwyt, nazwa, usuniecie i przelacznik. */
    function miejsceHooka(nazwaHooka) {
        return '<div class="qc-miejsce">' +
            '<span class="qc-chwyt">&#8942;&#8942;</span>' +
            '<span class="qc-nazwa qc-nazwa--hook">' + nazwaHooka + '</span>' +
            '<button type="button" class="qc-ikonka" data-usun title="' + (napisy.napisUsun || '') + '">&times;</button>' +
            '<label class="qc-toggle">' +
            '<input type="hidden" name="on[hook:' + nazwaHooka + ']" value="0">' +
            '<input type="checkbox" name="on[hook:' + nazwaHooka + ']" value="1" checked>' +
            '<span class="qc-toggle-track"></span>' +
            '</label>' +
            '</div>';
    }

    /** Nazwa hooka: litera, potem litery i cyfry. Taka sama zasada w obu miejscach. */
    function nazwaPoprawna(nazwa) {
        return /^[a-zA-Z][a-zA-Z0-9]{2,63}$/.test(nazwa);
    }

    /**
     * Wiersz z jednym miejscem. Bez nazwy hooka wiersz powstaje od razu z polem
     * na nia — sklep nie dostaje pustego wiersza, ktorego nie ma czym wypelnic.
     */
    function nowyWiersz(nazwaHooka, jedenWWierszu) {
        var wiersz = document.createElement('div');
        var plus = jedenWWierszu
            ? ''
            : '<button type="button" class="qc-plus" data-dodaj-miejsce title="' +
              (napisy.napisDodajMiejsce || '') + '">+</button>';

        wiersz.className = 'qc-wiersz';
        wiersz.innerHTML =
            '<div class="qc-miejsca">' +
            (nazwaHooka ? miejsceHooka(nazwaHooka) : poleHooka()) +
            plus +
            '</div>';

        return wiersz;
    }

    formularz.addEventListener('click', function (zdarzenie) {
        var cel = zdarzenie.target.closest('button');

        if (!cel) {
            return;
        }

        /* Wybór układu przełącza ekran na rozstawienie zapisane dla tamtego układu.
           Rozstawienia NIE spisujemy: na ekranie stoi układ, który właśnie
           opuszczamy, więc zapisany pod nowym kluczem nadpisałby to, co tam było.
           Stąd znacznik czytany przez obsługę wysyłki. */
        if (cel.dataset.uklad) {
            formularz.querySelector('[name="LAYOUT"]').value = cel.dataset.uklad;
            formularz.dataset.tylkoUklad = '1';
            formularz.requestSubmit();
            return;
        }

        /* szerokość panelu zakupu — cztery wartości ze spisu, nie suwak */
        if (cel.dataset.split) {
            formularz.querySelectorAll('[data-split]').forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', przycisk === cel);
            });
            formularz.querySelector('[name="SPLIT"]').value = cel.dataset.split;
            return;
        }

        /* wygląd karty spod koła zębatego */
        if (cel.classList.contains('qc-zebatka')) {
            var wyglad = cel.parentElement.querySelector('.qc-wyglad');

            if (!wyglad) {
                var glowa = cel.closest('.qc-karta-glowa');
                wyglad = glowa && glowa.parentElement.querySelector('.qc-wyglad');
            }

            if (wyglad) {
                var otwarty = przelacz(wyglad);
                cel.classList.toggle('is-active', otwarty);
                cel.setAttribute('aria-expanded', String(otwarty));
            }
            return;
        }

        /* Szczegóły elementu leżą wewnątrz jego wiersza, więc szukamy ich tam,
           a nie wśród rodzeństwa: rodzeństwo należy do listy sortowalnej. */
        if (cel.hasAttribute('data-szczegoly')) {
            var wiersz = cel.closest('.qc-wiersz');
            var szczegoly = wiersz && wiersz.querySelector('.qc-rozwiniete');

            if (szczegoly) {
                przelacz(szczegoly);
            }
            return;
        }

        /* dołożenie miejsca do istniejącego wiersza: od razu pole na nazwę hooka */
        if (cel.hasAttribute('data-dodaj-miejsce')) {
            cel.insertAdjacentHTML('beforebegin', poleHooka());
            var swieze = cel.previousElementSibling.querySelector('[data-nowy-hook]');

            if (swieze) {
                swieze.focus();
            }
            return;
        }

        /* zatwierdzenie nazwy: pole zamienia się w gotowe miejsce */
        if (cel.hasAttribute('data-zatwierdz')) {
            var puste = cel.closest('.qc-miejsce');
            var wpisane = puste.querySelector('[data-nowy-hook]');
            var nowa = wpisane ? wpisane.value.trim() : '';

            if (!nazwaPoprawna(nowa)) {
                wpisane.focus();
                return;
            }

            puste.insertAdjacentHTML('beforebegin', miejsceHooka(nowa));
            var listaZ = puste.closest('[x-sort\\:group]');
            puste.remove();
            policz(listaZ || formularz);
            return;
        }

        /* poniechanie: pole znika, wiersz zostaje taki, jaki był */
        if (cel.hasAttribute('data-poniechaj')) {
            var doUsuniecia = cel.closest('.qc-miejsce');
            var wierszP = doUsuniecia.closest('.qc-wiersz');

            doUsuniecia.remove();

            /* Wiersz bez ani jednego miejsca nie ma po co zostawać. */
            if (wierszP && !wierszP.querySelector('.qc-miejsce')) {
                wierszP.remove();
            }
            return;
        }

        /* nowy wiersz; z nazwą hooka, jeśli wpisana obok */
        if (cel.hasAttribute('data-dodaj-wiersz')) {
            var dodaj = cel.closest('.qc-dodaj');
            var pole = dodaj.querySelector('[data-hook]');
            var nazwa = pole ? pole.value.trim() : '';

            if (nazwa && !nazwaPoprawna(nazwa)) {
                pole.focus();
                return;
            }

            /* Wiersz musi wylądować w liście sortowalnej obszaru, a nie obok niej:
               stamtąd odczytujemy rozstawienie przy wysyłce. */
            var lista = dodaj.parentElement.querySelector('[x-sort\\:group]');

            if (!lista) {
                return;
            }

            lista.appendChild(nowyWiersz(nazwa, lista.hasAttribute('data-jeden-w-wierszu')));

            if (pole) {
                pole.value = '';
            }

            policz(lista);
            return;
        }

        /* usunięcie miejsca, a przy ostatnim — całego wiersza */
        if (cel.hasAttribute('data-usun')) {
            var miejsce = cel.closest('.qc-miejsce');
            var caly = cel.closest('.qc-wiersz');
            var rodzic = caly.parentElement;

            if (caly.querySelectorAll('.qc-miejsce').length > 1) {
                miejsce.remove();
            } else {
                var szczegolyPod = caly.nextElementSibling;

                if (szczegolyPod && szczegolyPod.classList.contains('qc-rozwiniete')) {
                    szczegolyPod.remove();
                }
                caly.remove();
            }

            policz(rodzic);
        }
    });

    /* Enter zatwierdza, Escape poniecha. Bez tego wpisanie nazwy konczy sie
       siegnieciem po mysz, a nazwa hooka to kilkanascie znakow z klawiatury. */
    formularz.addEventListener('keydown', function (zdarzenie) {
        if (!zdarzenie.target.hasAttribute('data-nowy-hook')) {
            return;
        }

        var miejsceN = zdarzenie.target.closest('.qc-miejsce');

        if (zdarzenie.key === 'Enter') {
            zdarzenie.preventDefault();
            miejsceN.querySelector('[data-zatwierdz]').click();
        }

        if (zdarzenie.key === 'Escape') {
            zdarzenie.preventDefault();
            miejsceN.querySelector('[data-poniechaj]').click();
        }
    });

    formularz.addEventListener('change', function (zdarzenie) {
        if (zdarzenie.target.type === 'checkbox') {
            policz(zdarzenie.target);
        }
    });

    /**
     * Rozstawienie spisujemy dopiero przy wysyłce: kolejność wierszy w obszarze
     * i miejsc w wierszu odczytujemy wprost z drzewa, więc przeciąganie nie musi
     * niczego aktualizować po drodze.
     */
    formularz.addEventListener('submit', function () {
        var rozstawienie = {};

        /* Przełączenie układu tylko go otwiera — rozstawienia nie ruszamy,
           bo na ekranie stoi jeszcze poprzedni układ. */
        if (formularz.dataset.tylkoUklad === '1') {
            formularz.querySelector('[name="ARRANGEMENT"]').value = '';
            return;
        }

        formularz.querySelectorAll('[x-sort\\:group]').forEach(function (obszar) {
            var klucz = obszar.getAttribute('x-sort:group');

            rozstawienie[klucz] = [].map.call(obszar.querySelectorAll('.qc-wiersz'), function (wiersz) {
                return [].map.call(wiersz.querySelectorAll('.qc-miejsce'), function (miejsce) {
                    var pole = miejsce.querySelector('input[type="checkbox"]');
                    var nazwa = pole ? pole.name.replace(/^on\[/, '').replace(/\]$/, '') : '';

                    return { id: nazwa, on: pole ? pole.checked : false };
                }).filter(function (m) {
                    return m.id !== '';
                });
            }).filter(function (w) {
                return w.length > 0;
            });
        });

        formularz.querySelector('[name="ARRANGEMENT"]').value = JSON.stringify(rozstawienie);
    });
}());
