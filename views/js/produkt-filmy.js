/**
 * Pole filmów na karcie produktu.
 *
 * PrestaShop trzyma adresy w jednym polu tekstowym na język, po przecinku, bo
 * tak wchodzą do importu i do API bez rozbierania niczego. Sklepikarzowi jedno
 * długie pole nic nie mówi, więc rysujemy nad nim wiersze.
 *
 * WIERSZ TO FILM, nie tłumaczenie. Ten sam wiersz istnieje w każdym języku,
 * a przełącznik przy nim pokazuje i zapisuje adres wersji językowej tego filmu,
 * tak jak przełącznik przy nazwie produktu. Dlatego „Usuń" kasuje film ze
 * wszystkich języków: kasuje wiersz, a nie tłumaczenie. Żeby zdjąć samo
 * tłumaczenie, czyści się pole w danym języku.
 *
 * Adres wpisany w jednym języku przepisuje się do tych języków, w których to
 * miejsce jest jeszcze puste. Film jest zwykle ten sam wszędzie, więc sklep
 * wpisuje go raz; gdy w drugim języku ma być inne nagranie, wpisanie go tam
 * zatrzymuje przepisywanie dla tego wiersza.
 *
 * Miniatura jest sprawdzeniem, a nie ozdobą: adres z literówką nie pokazuje
 * obrazka i widać to od razu, bez czytania komunikatów po zapisie.
 */
(function () {
    'use strict';

    var USTAWIENIA = window.quinceVideos || {};
    var LIMIT = USTAWIENIA.limit || 5;
    var NAPISY = USTAWIENIA.labels || {};
    var JEZYKI = USTAWIENIA.languages || [];
    var DOMYSLNY = USTAWIENIA.defaultLanguage || (JEZYKI[0] && JEZYKI[0].id);

    /**
     * Rozpoznaje dostawcę i identyfikator filmu.
     *
     * Wszystkie postacie adresu, które sklepikarz może skopiować z paska
     * przeglądarki: zwykły film, skrócony odnośnik z przycisku „Udostępnij",
     * shorts, osadzenie i Vimeo.
     */
    function rozpoznaj(adres) {
        var czysty = String(adres || '').trim();

        if (czysty === '') {
            return null;
        }

        var wzory = [
            { dostawca: 'youtube', wzor: /(?:youtube\.com\/watch\?(?:.*&)?v=)([A-Za-z0-9_-]{6,})/ },
            { dostawca: 'youtube', wzor: /(?:youtu\.be\/)([A-Za-z0-9_-]{6,})/ },
            { dostawca: 'youtube', wzor: /(?:youtube\.com\/shorts\/)([A-Za-z0-9_-]{6,})/ },
            { dostawca: 'youtube', wzor: /(?:youtube\.com\/embed\/)([A-Za-z0-9_-]{6,})/ },
            { dostawca: 'vimeo', wzor: /vimeo\.com\/(?:video\/)?([0-9]{6,})/ }
        ];

        for (var i = 0; i < wzory.length; i++) {
            var trafienie = czysty.match(wzory[i].wzor);

            if (trafienie) {
                return { dostawca: wzory[i].dostawca, id: trafienie[1], adres: czysty };
            }
        }

        return null;
    }

    /**
     * Wstawia miniaturę i nazwę dostawcy.
     *
     * YouTube oddaje obrazek wprost z adresu, bez klucza i bez zapytania.
     * Vimeo wymaga jednego zapytania do oEmbed; gdy nie odpowie, wiersz zostaje
     * bez obrazka, ale adres jest zapisany i film zadziała.
     */
    function pokazPodglad(wiersz, adres) {
        var info = rozpoznaj(adres);
        var pusty = String(adres || '').trim() === '';
        var obraz = wiersz.querySelector('.quince-videos__thumb');
        var podpis = wiersz.querySelector('.quince-videos__tytul');

        obraz.innerHTML = '';
        podpis.textContent = '';

        // Puste pole to nie błąd, tylko pole, którego jeszcze nie wypełniono.
        // Ostrzeżenie należy się dopiero wpisanemu adresowi, którego nie znamy.
        wiersz.classList.toggle('is-nierozpoznany', !info && !pusty);

        if (!info) {
            podpis.textContent = pusty ? '' : (NAPISY.nieznany || '');

            return;
        }

        if (info.dostawca === 'youtube') {
            var img = document.createElement('img');

            img.src = 'https://img.youtube.com/vi/' + info.id + '/mqdefault.jpg';
            img.alt = '';
            obraz.appendChild(img);
            podpis.textContent = 'YouTube';

            return;
        }

        podpis.textContent = 'Vimeo';
        fetch('https://vimeo.com/api/oembed.json?url=' + encodeURIComponent(info.adres))
            .then(function (o) { return o.ok ? o.json() : null; })
            .then(function (dane) {
                if (!dane || !dane.thumbnail_url) {
                    return;
                }

                var v = document.createElement('img');

                v.src = dane.thumbnail_url;
                v.alt = '';
                obraz.appendChild(v);
                podpis.textContent = dane.title || 'Vimeo';
            })
            .catch(function () { /* brak miniatury nie psuje zapisu */ });
    }

    function rozbij(wartosc) {
        return String(wartosc || '').split(',').map(function (a) { return a.trim(); });
    }

    function zbuduj(grupa) {
        // Pole na każdy język: PrestaShop rysuje je osobno i przełącza swoim
        // przełącznikiem. Nasza kontrolka bierze je wszystkie i przełącza sama,
        // przy każdym wierszu, więc tamten przełącznik jest już niepotrzebny.
        var pola = {};
        var kolejnosc = [];

        [].forEach.call(grupa.querySelectorAll('input[name*="quince_videos"]'), function (pole) {
            var dopasowanie = pole.name.match(/\[(\d+)\]$/);

            if (!dopasowanie) {
                return;
            }

            var idJezyka = parseInt(dopasowanie[1], 10);

            pola[idJezyka] = pole;
            kolejnosc.push(idJezyka);
            pole.classList.add('quince-videos__zrodlo');
        });

        if (!kolejnosc.length) {
            return;
        }

        var przelacznikPS = grupa.querySelector('.dropdown, .btn-group');

        if (przelacznikPS) {
            przelacznikPS.hidden = true;
        }

        // Wartości jako tablice w pamięci: wiersz sięga do miejsca o swoim
        // numerze w każdym języku, więc listy muszą być równej długości.
        var wartosci = {};
        var ile = 0;

        kolejnosc.forEach(function (id) {
            wartosci[id] = rozbij(pola[id].value);
            ile = Math.max(ile, wartosci[id].length);
        });

        ile = Math.max(1, Math.min(ile, LIMIT));

        kolejnosc.forEach(function (id) {
            while (wartosci[id].length < ile) {
                wartosci[id].push('');
            }
        });

        var opakowanie = document.createElement('div');
        var lista = document.createElement('div');
        var dodaj = document.createElement('button');

        opakowanie.className = 'quince-videos';
        lista.className = 'quince-videos__list';
        dodaj.type = 'button';
        dodaj.className = 'btn btn-outline-secondary quince-videos__add';
        dodaj.textContent = NAPISY.dodaj || '+';

        opakowanie.appendChild(lista);
        grupa.parentNode.insertBefore(opakowanie, grupa.nextSibling);

        function zapisz() {
            kolejnosc.forEach(function (id) {
                // Puste miejsca na końcu odpadają, w środku zostają: w tym
                // języku ten film po prostu nie ma swojej wersji.
                pola[id].value = wartosci[id].join(', ').replace(/(?:,\s*)+$/, '');
                // Formularz produktu pilnuje niezapisanych zmian po `change`.
                pola[id].dispatchEvent(new Event('change', { bubbles: true }));
            });

            var ostatni = lista.lastElementChild;

            if (ostatni) {
                ostatni.appendChild(dodaj);
            }

            dodaj.hidden = lista.children.length >= LIMIT;
        }

        function zbudujWiersz() {
            var wiersz = document.createElement('div');

            wiersz.className = 'quince-videos__row';
            wiersz.innerHTML =
                '<div class="quince-videos__thumb"></div>'
                + '<div class="quince-videos__pole">'
                + '<input type="text" class="form-control quince-videos__input" placeholder="'
                + (NAPISY.placeholder || '') + '">'
                + '<div class="quince-videos__tytul"></div>'
                + '</div>'
                + '<select class="quince-videos__lang"></select>'
                + '<button type="button" class="btn btn-outline-secondary quince-videos__remove">'
                + (NAPISY.usun || '') + '</button>';

            var wpis = wiersz.querySelector('.quince-videos__input');
            var wybor = wiersz.querySelector('.quince-videos__lang');

            JEZYKI.forEach(function (jezyk) {
                var opcja = document.createElement('option');

                opcja.value = String(jezyk.id);
                opcja.textContent = jezyk.iso;
                opcja.title = jezyk.name;
                wybor.appendChild(opcja);
            });

            wybor.value = String(kolejnosc.indexOf(DOMYSLNY) !== -1 ? DOMYSLNY : kolejnosc[0]);

            function numer() {
                return [].indexOf.call(lista.children, wiersz);
            }

            function odswiez() {
                var jezyk = parseInt(wybor.value, 10);

                wpis.value = wartosci[jezyk][numer()] || '';
                pokazPodglad(wiersz, wpis.value);
            }

            wpis.addEventListener('input', function () {
                var jezyk = parseInt(wybor.value, 10);
                var indeks = numer();

                wartosci[jezyk][indeks] = wpis.value.trim();

                // Ten sam film w pozostałych językach, dopóki nikt nie wpisał
                // tam własnego adresu. Sklep wpisuje raz, a wersję odrębną
                // ustawia tylko tam, gdzie naprawdę jest inna.
                kolejnosc.forEach(function (id) {
                    if (id !== jezyk && (wartosci[id][indeks] || '') === '') {
                        wartosci[id][indeks] = wpis.value.trim();
                    }
                });

                pokazPodglad(wiersz, wpis.value);
                zapisz();
            });

            wybor.addEventListener('change', odswiez);

            wiersz.querySelector('.quince-videos__remove').addEventListener('click', function () {
                var indeks = numer();

                // Kasujemy miejsce we wszystkich językach: wiersz jest filmem,
                // a nie tłumaczeniem. Inaczej listy w językach rozjechałyby się
                // i drugi wiersz znaczyłby co innego w każdym z nich.
                kolejnosc.forEach(function (id) {
                    wartosci[id].splice(indeks, 1);
                });

                wiersz.remove();

                if (!lista.children.length) {
                    dodajWiersz();
                }

                zapisz();
            });

            lista.appendChild(wiersz);
            odswiez();

            return wiersz;
        }

        function dodajWiersz() {
            if (lista.children.length >= LIMIT) {
                return;
            }

            kolejnosc.forEach(function (id) {
                while (wartosci[id].length < lista.children.length + 1) {
                    wartosci[id].push('');
                }
            });

            zbudujWiersz();
        }

        for (var i = 0; i < ile; i++) {
            zbudujWiersz();
        }

        dodaj.addEventListener('click', function () { dodajWiersz(); });
        zapisz();
    }

    /**
     * Pole wyboru dla multisklepu.
     *
     * Karta produktu otwiera się tylko w jednym sklepie, więc bez tego adresy
     * trzeba by wpisywać w każdym sklepie osobno. Dotyczy całej listy, nie
     * jednego filmu, więc stoi raz, pod kontrolką.
     */
    function dodajWyborSklepow(gniazdo) {
        if (!USTAWIENIA.multistore || gniazdo.querySelector('.quince-videos__shops')) {
            return;
        }

        var pole = document.createElement('div');

        pole.className = 'form-check quince-videos__shops';
        pole.innerHTML =
            '<input type="checkbox" class="form-check-input" id="quince-videos-shops"'
            + ' name="quince_videos_all_shops" value="1">'
            + '<label class="form-check-label" for="quince-videos-shops"></label>';
        pole.querySelector('label').textContent = NAPISY.wszystkieSklepy || '';
        gniazdo.appendChild(pole);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var pierwsze = document.querySelector('input[name*="quince_videos"]');

        if (!pierwsze) {
            return;
        }

        var grupa = pierwsze.closest('.locale-input-group') || pierwsze.parentNode;

        zbuduj(grupa);
        dodajWyborSklepow(grupa.closest('.form-group') || grupa.parentNode);
    });
}());
