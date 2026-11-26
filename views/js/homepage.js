/**
 * Ekran „Strona główna": to, czego nie obsługuje sam znacznik.
 *
 * Sekcje stoją w stronie jako gotowy znacznik z Twiga, a kolejność zmienia
 * Alpine (`x-sort`), tak samo jak rozstawienie elementów na stronie produktu.
 * Skrypt robi cztery rzeczy:
 *
 *   1. rozwija i zwija wiersz sekcji,
 *   2. dokłada sekcję z gotowego wzoru w `<template>` i kasuje pozycje,
 *   3. pokazuje i gasi pola, które mają sens przy danym wyborze, oraz dolicza
 *      to, czego się nie ustawia: resztę szerokości i liczbę widocznych zajawek,
 *   4. spisuje całość do pola `SECTIONS` przy wysyłce.
 *
 * Żadna lista wartości nie jest tu przepisana: etykiety skrypt czyta z opcji,
 * które wstawił Twig, więc dołożenie skóry albo źródła produktów nie wymaga
 * ruszania tego pliku.
 */
(function () {
    'use strict';

    var formularz = document.getElementById('quince-homepage');

    if (!formularz) {
        return;
    }

    var listaSekcji = formularz.querySelector('[data-sekcje]');
    var wzorSekcji = formularz.querySelector('[data-wzor-sekcji]');
    var poleZapisu = formularz.querySelector('[name="SECTIONS"]');

    var siatka = parseInt(formularz.dataset.siatka, 10) || 4;
    var minProcent = parseInt(formularz.dataset.minProcent, 10) || 10;
    var maxZapewnien = parseInt(formularz.dataset.maxZapewnien, 10) || 4;
    var jezyki = JSON.parse(formularz.dataset.languages || '[]');
    var kategorie = JSON.parse(formularz.dataset.kategorie || '[]');
    var napisy = JSON.parse(formularz.dataset.napisy || '{}');

    // ── drobiazgi ────────────────────────────────────────────────────────

    function pole(gdzie, nazwa) {
        return gdzie.querySelector('[data-pole="' + nazwa + '"]');
    }

    /** Wartość przełącznika segmentowego: ta, której przycisk jest zaznaczony. */
    function segment(pojemnik) {
        var wybrany = pojemnik ? pojemnik.querySelector('.qc-seg-btn.is-active') : null;

        return wybrany ? wybrany.dataset.wartosc : '';
    }

    /** Napis z zaznaczonego przycisku: etykieta przyszła z Twiga, więc jest przetłumaczona. */
    function segmentNapis(pojemnik) {
        var wybrany = pojemnik ? pojemnik.querySelector('.qc-seg-btn.is-active') : null;

        return wybrany ? wybrany.textContent.trim() : '';
    }

    // ── kontrast tekstu z gradientem ─────────────────────────────────────
    // Ta sama rachuba co w silniku (`HomepageSettings::sectionTone()`):
    // tekst jasny albo ciemny, a ostrzeżenie, gdy lepszy z nich ma z którymś
    // końcem mniej niż 4.5:1.

    var paleta = JSON.parse(formularz.dataset.paleta || '{}');

    /** Stopień palety albo kod na [r, g, b]; nieznane: null. */
    function rgb(wartosc) {
        var kod = wartosc.charAt(0) === '#' ? wartosc : (paleta[wartosc] || '');
        // Sześć znaków przed trzema: inaczej „#0b2a5b” dopasowuje się jako „#0b2”.
        var m = /^#([0-9a-f]{6}|[0-9a-f]{3})\b/i.exec(kod);

        if (!m) {
            return null;
        }

        var h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];

        return [0, 2, 4].map(function (i) { return parseInt(h.substr(i, 2), 16); });
    }

    function luminancja(kanaly) {
        var l = kanaly.map(function (v) {
            v /= 255;

            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });

        return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
    }

    function kontrast(a, b) {
        var la = luminancja(a);
        var lb = luminancja(b);

        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    /** Czy jasny albo ciemny tekst ma co najmniej 4.5:1 z oboma końcami. */
    function gradientCzytelny(od, doBarwy) {
        var konce = [rgb(od), rgb(doBarwy)];
        var jasny = rgb('neutral-1');
        var ciemny = rgb('neutral-12');

        // Wpisu, którego nie da się odczytać, nie oceniamy: bez ostrzeżenia.
        if (!konce[0] || !konce[1] || !jasny || !ciemny) {
            return true;
        }

        var najslabszy = function (tekst) {
            return Math.min(kontrast(tekst, konce[0]), kontrast(tekst, konce[1]));
        };

        return Math.max(najslabszy(jasny), najslabszy(ciemny)) >= 4.5;
    }

    function wybranyNapis(select) {
        return select && select.selectedIndex >= 0 ? select.options[select.selectedIndex].text.trim() : '';
    }

    function nazwaKategorii(id) {
        for (var i = 0; i < kategorie.length; i += 1) {
            if (String(kategorie[i].id) === String(id)) {
                return kategorie[i].name;
            }
        }

        return String(id);
    }

    /** Napisy z komponentu wielojęzycznego, sparowane po kolejności języków. */
    function napisyPola(pojemnik) {
        var wynik = {};

        if (!pojemnik) {
            return wynik;
        }

        pojemnik.querySelectorAll('.qc-multilang-row input[type="text"]').forEach(function (wejscie, i) {
            var tekst = wejscie.value.trim();

            if (tekst !== '' && jezyki[i] !== undefined) {
                wynik[jezyki[i]] = tekst;
            }
        });

        return wynik;
    }

    // ── podział sekcji ───────────────────────────────────────────────────

    function tryb(sekcja) {
        return segment(pole(sekcja, 'mode')) || 'grid';
    }

    function ileKolumn(sekcja) {
        return parseInt(segment(pole(sekcja, 'count')), 10) || 1;
    }

    function calosc(sekcja) {
        return tryb(sekcja) === 'grid' ? siatka : 100;
    }

    function najmniej(sekcja) {
        return tryb(sekcja) === 'grid' ? 1 : minProcent;
    }

    function polaCzesci(sekcja) {
        return Array.prototype.slice.call(sekcja.querySelectorAll('[data-czesc]'));
    }

    /** Kontrolka szerokości właściwa dla wybranego sposobu dzielenia. */
    function wyborCzesci(blok, jakiTryb) {
        return blok.querySelector('[data-pole="part-' + jakiTryb + '"]');
    }

    /**
     * Szerokości kolumn.
     *
     * Ostatnia bierze resztę, więc podział zawsze się sumuje. Pozostałe są
     * przycinane tak, żeby dla każdej następnej zostało po jednej najmniejszej
     * części. Inaczej wybór w pierwszej kolumnie mógłby nie zostawić miejsca
     * na drugą.
     */
    function czesci(sekcja) {
        var jakiTryb = tryb(sekcja);
        var ile = ileKolumn(sekcja);
        var zostalo = calosc(sekcja);
        var naj = najmniej(sekcja);
        var wynik = [];

        polaCzesci(sekcja).forEach(function (blok, i) {
            if (i >= ile - 1) {
                return;
            }

            var kontrolka = wyborCzesci(blok, jakiTryb);
            var najwiecej = zostalo - (ile - i - 1) * naj;
            var wartosc = Math.max(naj, Math.min(parseInt(kontrolka.value, 10) || naj, najwiecej));

            wynik.push(wartosc);
            zostalo -= wartosc;
        });

        wynik.push(Math.max(naj, zostalo));

        return wynik;
    }

    /**
     * Rozkłada szerokości równo.
     *
     * Potrzebne przy zmianie sposobu dzielenia i liczby kolumn: wartość „1”
     * znaczy co innego w polach siatki, a co innego w procentach, więc
     * przeniesiona bez przeliczenia daje kolumnę na jeden procent.
     */
    function rozlozRowno(sekcja) {
        var jakiTryb = tryb(sekcja);
        var ile = ileKolumn(sekcja);
        var naj = najmniej(sekcja);
        var udzial = Math.max(naj, Math.floor(calosc(sekcja) / ile));

        polaCzesci(sekcja).forEach(function (blok, i) {
            if (i >= ile - 1) {
                return;
            }

            var kontrolka = wyborCzesci(blok, jakiTryb);
            var najblizsza = null;

            // Lista wartości jest skokowa, więc bierzemy największą, która
            // nie przekracza równego udziału.
            kontrolka.querySelectorAll('option').forEach(function (opcja) {
                var wartosc = parseInt(opcja.value, 10);

                if (wartosc <= udzial && (najblizsza === null || wartosc > najblizsza)) {
                    najblizsza = wartosc;
                }
            });

            kontrolka.value = String(najblizsza === null ? naj : najblizsza);
        });
    }

    /** Ile zajawek widać w kolumnie obok siebie. */
    function widoczne(sekcja, podzial, i) {
        var suma = podzial.reduce(function (a, b) { return a + b; }, 0);

        return tryb(sekcja) === 'grid'
            ? podzial[i]
            : Math.max(1, Math.floor(siatka * podzial[i] / suma));
    }

    function procent(podzial, i) {
        var suma = podzial.reduce(function (a, b) { return a + b; }, 0);

        return Math.round(podzial[i] / suma * 100);
    }

    // ── odświeżanie sekcji ───────────────────────────────────────────────

    function odswiezSekcje(sekcja) {
        if (!sekcja) {
            return;
        }

        var jakiTryb = tryb(sekcja);
        var ile = ileKolumn(sekcja);
        var podzial = czesci(sekcja);

        /* Przy podziale kolumnowym nie da się mieć więcej kolumn niż pól siatki:
           kolumna węższa od jednego pola nie pomieści zajawki. Zaznaczenie,
           które przy przejściu z podziału procentowego wypadło poza siatkę,
           zjeżdża na największą dopuszczalną wartość. */
        var przyciskiLiczby = pole(sekcja, 'count').querySelectorAll('.qc-seg-btn');

        przyciskiLiczby.forEach(function (przycisk) {
            przycisk.hidden = jakiTryb === 'grid' && parseInt(przycisk.dataset.wartosc, 10) > siatka;
        });

        if (jakiTryb === 'grid' && ile > siatka) {
            przyciskiLiczby.forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', parseInt(przycisk.dataset.wartosc, 10) === siatka);
            });

            rozlozRowno(sekcja);
            odswiezSekcje(sekcja);

            return;
        }

        var opisTrybu = sekcja.querySelector('[data-tryb-opis]');

        if (opisTrybu) {
            opisTrybu.textContent = jakiTryb === 'grid' ? napisy.modeGrid : napisy.modePercent;
        }

        /* Pola szerokości: tyle, ile kolumn, a ostatnie pokazuje resztę. */
        polaCzesci(sekcja).forEach(function (blok, i) {
            blok.hidden = i >= ile || ile === 1;

            var ostatnia = i === ile - 1;
            var reszta = blok.querySelector('[data-reszta]');
            var opis = blok.querySelector('[data-czesc-opis]');

            blok.querySelectorAll('[data-gdy-tryb]').forEach(function (wybor) {
                wybor.hidden = ostatnia || wybor.dataset.gdyTryb !== jakiTryb;
            });

            reszta.hidden = !ostatnia;

            if (ostatnia) {
                reszta.textContent = jakiTryb === 'grid'
                    ? podzial[i] + ' / ' + siatka
                    : podzial[i] + '%';
                opis.textContent = napisy.leftover;
            } else {
                var kontrolka = wyborCzesci(blok, jakiTryb);

                /* Wartości, które nie zostawiłyby miejsca następnym kolumnom,
                   znikają z listy zamiast dawać się wybrać i zostać poprawione. */
                var zajete = 0;

                podzial.forEach(function (c, j) { if (j < i) { zajete += c; } });

                var najwiecej = calosc(sekcja) - zajete - (ile - i - 1) * najmniej(sekcja);

                kontrolka.querySelectorAll('option').forEach(function (opcja) {
                    opcja.hidden = parseInt(opcja.value, 10) > najwiecej;
                });

                kontrolka.value = String(podzial[i]);
                opis.textContent = jakiTryb === 'grid' ? napisy.gridFields : napisy.percentWidth;
            }
        });

        /* Pasek proporcji: szerokość pola bierze się z jego udziału. */
        var podglad = sekcja.querySelector('[data-podglad]');

        podglad.textContent = '';

        podzial.forEach(function (_, i) {
            var p = document.createElement('div');

            p.className = 'qc-podglad-pole' + (i % 2 ? ' qc-podglad-pole--barwna' : '');
            p.style.flex = String(podzial[i]);
            p.textContent = procent(podzial, i) + '%';
            podglad.appendChild(p);
        });

        /* Kolumn jest tyle, ile części podziału. */
        var kolumny = sekcja.querySelectorAll('[data-kolumna]');

        kolumny.forEach(function (kolumna, i) {
            kolumna.hidden = i >= ile;

            if (i < ile) {
                odswiezKolumne(sekcja, kolumna, podzial, i);
            }
        });

        var opisKolumn = sekcja.querySelector('[data-kolumny-opis]');

        if (opisKolumn) {
            opisKolumn.textContent = ile === 1 ? napisy.oneColumn : ile + ' ' + napisy.manyColumns;
        }

        /* Nagłówek i jego napisy. */
        var naglowek = pole(sekcja, 'header').querySelector('input[type="checkbox"]').checked;

        sekcja.querySelectorAll('[data-gdy-naglowek]').forEach(function (blok) { blok.hidden = !naglowek; });

        // Napis odnośnika pokazujemy, gdy odnośnik ma którykolwiek język.
        var odnosnik = Object.keys(napisyPola(pole(sekcja, 'link'))).length > 0;
        var napisOdnosnika = sekcja.querySelector('[data-gdy-odnosnik]');

        napisOdnosnika.hidden = !naglowek || !odnosnik;

        sekcja.querySelector('[data-krok-naglowek]').classList.toggle('qc-krok--gotowy', naglowek);
        sekcja.querySelector('[data-naglowek-opis]').textContent = naglowek ? napisy.headerOn : napisy.headerOff;

        /* Pola należą do rodzaju tła: pas na barwie nie ma po co pytać
           o zdjęcie ani o kąt gradientu. */
        var skora = pole(sekcja, 'skin').value;

        sekcja.querySelectorAll('[data-gdy-skora]').forEach(function (blok) {
            blok.hidden = blok.dataset.gdySkora !== skora;
        });

        if (skora === 'gradient') {
            sekcja.querySelector('[data-gradient-ostrzezenie]').hidden = gradientCzytelny(
                pole(sekcja, 'skin_from').value.trim() || 'neutral-4',
                pole(sekcja, 'skin_to').value.trim() || 'neutral-6'
            );
        }

        sekcja.querySelector('[data-krok-oprawa]')
            .classList.toggle('qc-krok--gotowy', skora !== 'none');

        opiszSekcje(sekcja, podzial);
    }

    function odswiezKolumne(sekcja, kolumna, podzial, i) {
        var rodzaj = segment(pole(kolumna, 'type'));
        var ileWidac = widoczne(sekcja, podzial, i);

        kolumna.querySelector('[data-udzial]').textContent = tryb(sekcja) === 'grid'
            ? podzial[i] + ' / ' + siatka + ' · ' + procent(podzial, i) + '%'
            : procent(podzial, i) + '%';

        kolumna.querySelectorAll('[data-gdy]').forEach(function (blok) {
            blok.hidden = blok.dataset.gdy !== rodzaj;
        });

        if (rodzaj === 'products') {
            var zrodlo = pole(kolumna, 'source').value;

            kolumna.querySelectorAll('[data-gdy-zrodla]').forEach(function (blok) {
                blok.hidden = blok.dataset.gdyZrodla !== zrodlo;
            });

            kolumna.querySelector('[data-widoczne]').textContent = String(ileWidac);
            kolumna.querySelector('[data-widoczne-opis]').textContent =
                tryb(sekcja) === 'grid' ? napisy.visibleGrid : napisy.visiblePercent;

            var sztuk = parseInt(pole(kolumna, 'count').value, 10) || 1;
            var postac = segment(pole(kolumna, 'display'));

            kolumna.querySelector('[data-reszta-opis]').textContent = sztuk <= ileWidac
                ? napisy.restNone
                : (postac === 'carousel' ? napisy.restCarousel : napisy.restRows);
        }

        if (rodzaj === 'product') {
            /* Ustawienie zdjęcia zależy od układu: przy produkcie jedynym
               w sekcji „nad nazwą” nic nie zmienia (zdjęcie i tak stoi obok),
               a „pół na pół” istnieje tylko tam. Zaznaczona opcja, która
               w tym układzie znika, przechodzi na „obok nazwy”, żeby to, co
               widać, było tym, co trafi do zapisu. */
            var jedynaKolumna = ileKolumn(sekcja) === 1;
            var zdjecie = pole(kolumna, 'image');

            zdjecie.querySelectorAll('[data-wartosc]').forEach(function (przycisk) {
                przycisk.hidden = jedynaKolumna
                    ? przycisk.dataset.wartosc === 'above'
                    : przycisk.dataset.wartosc === 'split';
            });

            var wybraneZdjecie = zdjecie.querySelector('.is-active');

            if (!wybraneZdjecie || wybraneZdjecie.hidden) {
                zdjecie.querySelectorAll('[data-wartosc]').forEach(function (przycisk) {
                    przycisk.classList.toggle('is-active', przycisk.dataset.wartosc === 'beside');
                });
            }

            var postac = segment(pole(kolumna, 'extraStyle'));

            kolumna.querySelectorAll('[data-gdy-postac]').forEach(function (blok) {
                blok.hidden = blok.dataset.gdyPostac !== postac;
            });

            kolumna.querySelector('[data-zapewnienia-opis]').textContent =
                postac === 'blocks' ? napisy.extrasBlocks : napisy.extrasPills;

            /* Pierwszy wiersz stoi od razu. Dokładamy go raz, przy pierwszym
               rysowaniu kolumny; skasowany przez sklep już nie wraca. */
            if (!kolumna.dataset.zapewnieniaGotowe) {
                kolumna.dataset.zapewnieniaGotowe = '1';

                if (!kolumna.querySelector('[data-zapewnienie]')) {
                    kolumna.querySelector('[data-zapewnienia]').appendChild(
                        kolumna.querySelector('[data-wzor-zapewnienia]').content.firstElementChild.cloneNode(true)
                    );
                }
            }

            /* Dokładanie gaśnie po osiągnięciu sufitu: dalsze pozycje i tak
               nie zmieściłyby się przy zajawce. */
            var ile = kolumna.querySelectorAll('[data-zapewnienie]').length;
            var dodaj = kolumna.querySelector('[data-dodaj-zapewnienie]');

            dodaj.disabled = ile >= maxZapewnien;
            kolumna.querySelector('[data-zapewnienia-licznik]').textContent =
                ile + ' / ' + maxZapewnien + ' ' + (ile === 1 ? napisy.linesOne : napisy.linesMany);
        }

        if (rodzaj === 'categories') {
            var naKaflu = segment(pole(kolumna, 'tileImage'));

            var cien = pole(kolumna, 'shade').value;

            /* Barwy zasłony i obramowania tylko przy stylu własnym; gotowy
               styl bierze je z palety. */
            kolumna.querySelectorAll('[data-gdy-kafel]').forEach(function (blok) {
                blok.hidden = blok.dataset.gdyKafel !== naKaflu
                    || (blok.dataset.gdyCien !== undefined && blok.dataset.gdyCien !== cien);
            });

            kolumna.querySelectorAll('.qc-slider-row').forEach(function (wiersz) {
                var suwak = wiersz.querySelector('input[type="range"]');
                var wartosc = wiersz.querySelector('[data-suwak-wartosc]');

                if (suwak && wartosc) {
                    wartosc.textContent = suwak.value;
                }
            });

            kolumna.querySelector('[data-kafel-opis]').textContent =
                naKaflu === 'icon' ? napisy.tileIcon : napisy.tilePhoto;

            rysujKategorie(kolumna, naKaflu === 'icon');
        }
    }

    /**
     * Chipy wybranych kategorii i pole ikony przy każdej z nich.
     *
     * Rysowane skryptem, bo lista zmienia się w trakcie pracy. Wybrane
     * identyfikatory siedzą na pojemniku, żeby przetrwały przerysowanie.
     */
    function rysujKategorie(kolumna, zIkonami) {
        var pojemnik = pole(kolumna, 'categories');
        var wybrane = JSON.parse(pojemnik.dataset.wybrane || '[]');
        var ikony = JSON.parse(pojemnik.dataset.ikony || '{}');

        pojemnik.textContent = '';

        wybrane.forEach(function (id, idx) {
            var chip = document.createElement('span');

            chip.className = 'qc-tag';
            chip.innerHTML = '<span class="qc-tags-name"></span>';
            chip.firstChild.textContent = nazwaKategorii(id);

            var x = document.createElement('button');

            x.type = 'button';
            x.className = 'qc-tag-x';
            x.title = napisy.removeCategory;
            x.textContent = '×';
            x.addEventListener('click', function () {
                wybrane.splice(idx, 1);
                pojemnik.dataset.wybrane = JSON.stringify(wybrane);
                odswiezSekcje(kolumna.closest('[data-sekcja]'));
            });

            chip.appendChild(x);
            pojemnik.appendChild(chip);
        });

        var pozostale = kategorie.filter(function (k) {
            return wybrane.indexOf(k.id) === -1;
        });

        if (pozostale.length) {
            var wrap = document.createElement('div');

            wrap.className = 'qc-select-wrap';

            var select = document.createElement('select');

            select.className = 'qc-field-base qc-select qc-focusable';
            select.appendChild(new Option('(' + napisy.addCategory + ')', ''));

            pozostale.forEach(function (k) { select.appendChild(new Option(k.name, k.id)); });

            select.addEventListener('change', function () {
                if (!select.value) {
                    return;
                }

                wybrane.push(parseInt(select.value, 10));
                pojemnik.dataset.wybrane = JSON.stringify(wybrane);
                odswiezSekcje(kolumna.closest('[data-sekcja]'));
            });

            wrap.appendChild(select);
            pojemnik.appendChild(wrap);
        }

        var listaIkon = pole(kolumna, 'icons');

        listaIkon.textContent = '';

        if (!zIkonami) {
            return;
        }

        wybrane.forEach(function (id) {
            var rzad = document.createElement('div');

            rzad.style.cssText = 'display:flex;align-items:center;gap:8px';

            var podpis = document.createElement('span');

            podpis.style.cssText = 'font-size:var(--text-sm);inline-size:220px';
            podpis.textContent = nazwaKategorii(id);
            rzad.appendChild(podpis);

            var wejscie = document.createElement('input');

            wejscie.type = 'text';
            wejscie.className = 'qc-field-base qc-field-sm qc-focusable';
            wejscie.style.inlineSize = '220px';
            wejscie.placeholder = napisy.iconPlaceholder;
            wejscie.value = ikony[id] || '';
            wejscie.addEventListener('input', function () {
                ikony[id] = wejscie.value;
                pojemnik.dataset.ikony = JSON.stringify(ikony);
            });

            rzad.appendChild(wejscie);
            listaIkon.appendChild(rzad);
        });
    }

    /** Jedno zdanie w pasku: co ta sekcja pokazuje. */
    function opiszSekcje(sekcja, podzial) {
        var czesciOpisu = [tryb(sekcja) === 'grid'
            ? podzial.join(' + ') + ' / ' + siatka
            : podzial.map(function (_, i) { return procent(podzial, i) + '%'; }).join(' + ')];

        var ile = ileKolumn(sekcja);

        sekcja.querySelectorAll('[data-kolumna]').forEach(function (kolumna, i) {
            if (i >= ile) {
                return;
            }

            var rodzaj = segment(pole(kolumna, 'type'));

            if (rodzaj === 'hook') {
                czesciOpisu.push(pole(kolumna, 'hook').value.trim() || napisy.noHook);
            } else if (rodzaj === 'products') {
                var sztuk = parseInt(pole(kolumna, 'count').value, 10) || 1;
                var ileWidac = widoczne(sekcja, podzial, i);

                czesciOpisu.push(wybranyNapis(pole(kolumna, 'source')).toLowerCase()
                    + ', ' + sztuk + ' ' + napisy.tiles
                    + (sztuk > ileWidac ? ' (' + ileWidac + ' ' + napisy.visible + ')' : ''));
            } else if (rodzaj === 'product') {
                var id = parseInt(pole(kolumna, 'product').value, 10) || 0;
                var ileZapewnien = 0;

                kolumna.querySelectorAll('[data-zapewnienie]').forEach(function (wiersz) {
                    if (Object.keys(napisyPola(pole(wiersz, 'extra-text'))).length) {
                        ileZapewnien += 1;
                    }
                });

                czesciOpisu.push(napisy.bigTile + ': ' + (id > 0 ? '#' + id : napisy.noProduct)
                    + (ileZapewnien ? ', ' + ileZapewnien + ' ' + napisy.claims : ''));
            } else {
                var wybrane = JSON.parse(pole(kolumna, 'categories').dataset.wybrane || '[]');

                czesciOpisu.push(wybrane.length + ' ' + napisy.categoriesWord
                    + ', ' + segmentNapis(pole(kolumna, 'tileImage')).toLowerCase());
            }
        });

        czesciOpisu.push(napisy.background + ': ' + wybranyNapis(pole(sekcja, 'skin')).toLowerCase());
        czesciOpisu.push(segment(pole(sekcja, 'width')) === 'full' ? napisy.widthFull : napisy.widthBoxed);

        sekcja.querySelector('[data-opis]').textContent = czesciOpisu.join(' · ');
    }

    function odswiezWszystko() {
        formularz.querySelectorAll('[data-sekcja]').forEach(function (sekcja, i) {
            var nazwa = sekcja.querySelector('[data-nazwa]');

            nazwa.textContent = nazwa.textContent.replace(/\s*\d+\s*$/, '') + ' ' + (i + 1);

            odswiezSekcje(sekcja);
        });
    }

    // ── obsługa kliknięć ─────────────────────────────────────────────────

    formularz.addEventListener('click', function (zdarzenie) {
        var cel = zdarzenie.target.closest('button');
        var sekcja = zdarzenie.target.closest('[data-sekcja]');

        /* Klik w pasek rozwija wiersz. Przełącznik, chwyt i ikonki mają własne
           zadania, więc ich nie dotyczy. */
        if (!cel && zdarzenie.target.closest('[data-pasek]') && !zdarzenie.target.closest('.qc-toggle, .qc-rep-handle')) {
            rozwin(sekcja);

            return;
        }

        if (!cel) {
            return;
        }

        if (cel.classList.contains('qc-seg-btn')) {
            cel.parentElement.querySelectorAll('.qc-seg-btn').forEach(function (przycisk) {
                przycisk.classList.toggle('is-active', przycisk === cel);
            });

            var przelacznik = cel.parentElement.dataset.pole;

            if (przelacznik === 'mode' || przelacznik === 'count') {
                rozlozRowno(sekcja);
            }

            odswiezSekcje(sekcja);

            return;
        }

        if (cel.hasAttribute('data-rozwin')) {
            rozwin(sekcja);

            return;
        }

        if (cel.hasAttribute('data-dodaj-sekcje')) {
            dodajSekcje();

            return;
        }

        if (cel.hasAttribute('data-dodaj-zapewnienie')) {
            var kolumnaZ = cel.closest('[data-kolumna]');
            var wzor = kolumnaZ.querySelector('[data-wzor-zapewnienia]');

            kolumnaZ.querySelector('[data-zapewnienia]')
                .appendChild(wzor.content.firstElementChild.cloneNode(true));

            odswiezSekcje(sekcja);

            return;
        }

        if (cel.hasAttribute('data-usun-zapewnienie')) {
            cel.closest('[data-zapewnienie]').remove();
            odswiezSekcje(sekcja);

            return;
        }

        if (cel.hasAttribute('data-usun-sekcje')) {
            sekcja.remove();
            odswiezWszystko();
        }
    });

    function rozwin(sekcja) {
        /* Ciało sekcji to pierwsze `qc-rep-body` W NIEJ, a nie w jej kolumnach:
           kolumny stoją na tym samym komponencie i mają własne. */
        var body = sekcja.querySelector(':scope > .qc-rep-body');

        body.hidden = !body.hidden;
        sekcja.classList.toggle('qc-rep-row--otwarty', !body.hidden);
    }

    function dodajSekcje() {
        var nowa = wzorSekcji.content.firstElementChild.cloneNode(true);

        /* Nazwa własna sekcji jest identyfikatorem: silnik rozpoznaje po niej
           sekcję, więc musi być niepowtarzalna w obrębie strony. */
        var zajete = [];

        formularz.querySelectorAll('[data-sekcja] [data-pole="id"]').forEach(function (p) { zajete.push(p.value); });

        var numer = zajete.length + 1;

        while (zajete.indexOf('section-' + numer) !== -1) {
            numer += 1;
        }

        pole(nowa, 'id').value = 'section-' + numer;
        nowa.setAttribute('x-sort:item', "'section-" + numer + "'");

        listaSekcji.appendChild(nowa);
        rozwin(nowa);
        odswiezWszystko();
    }

    formularz.addEventListener('change', function (zdarzenie) {
        odswiezSekcje(zdarzenie.target.closest('[data-sekcja]'));
    });

    formularz.addEventListener('input', function (zdarzenie) {
        odswiezSekcje(zdarzenie.target.closest('[data-sekcja]'));
    });

    /* Przeciągnięcie zmienia kolejność w drzewie strony, a nie kliknięciem,
       więc numery sekcji odświeża obserwator listy. */
    if (listaSekcji) {
        new MutationObserver(odswiezWszystko).observe(listaSekcji, { childList: true });
    }

    // ── spis do pola formularza ──────────────────────────────────────────

    formularz.addEventListener('submit', function () {
        var sekcje = [];

        formularz.querySelectorAll('[data-sekcja]').forEach(function (sekcja) {
            var ile = ileKolumn(sekcja);
            var podzial = czesci(sekcja);
            var kolumny = [];

            sekcja.querySelectorAll('[data-kolumna]').forEach(function (kolumna, i) {
                if (i >= ile) {
                    return;
                }

                var rodzaj = segment(pole(kolumna, 'type'));

                if (rodzaj === 'hook') {
                    kolumny.push({ type: 'hook', hook: pole(kolumna, 'hook').value.trim() });

                    return;
                }

                if (rodzaj === 'product') {
                    var zapewnienia = [];

                    kolumna.querySelectorAll('[data-zapewnienie]').forEach(function (wiersz) {
                        var tresc = napisyPola(pole(wiersz, 'extra-text'));

                        // O tym, czy pozycja istnieje, rozstrzyga sama treść:
                        // wyróżniona wartość bez niej nie mówi nic.
                        if (!Object.keys(tresc).length) {
                            return;
                        }

                        zapewnienia.push({
                            lead: napisyPola(pole(wiersz, 'extra-lead')),
                            text: tresc
                        });
                    });

                    kolumny.push({
                        type: 'product',
                        product: parseInt(pole(kolumna, 'product').value, 10) || 0,
                        image: segment(pole(kolumna, 'image')),
                        extra_style: segment(pole(kolumna, 'extraStyle')),
                        extras: zapewnienia
                    });

                    return;
                }

                if (rodzaj === 'product') {
            var postac = segment(pole(kolumna, 'extraStyle'));

            kolumna.querySelectorAll('[data-gdy-postac]').forEach(function (blok) {
                blok.hidden = blok.dataset.gdyPostac !== postac;
            });

            kolumna.querySelector('[data-zapewnienia-opis]').textContent =
                postac === 'blocks' ? napisy.extrasBlocks : napisy.extrasPills;

            /* Pierwszy wiersz stoi od razu. Dokładamy go raz, przy pierwszym
               rysowaniu kolumny; skasowany przez sklep już nie wraca. */
            if (!kolumna.dataset.zapewnieniaGotowe) {
                kolumna.dataset.zapewnieniaGotowe = '1';

                if (!kolumna.querySelector('[data-zapewnienie]')) {
                    kolumna.querySelector('[data-zapewnienia]').appendChild(
                        kolumna.querySelector('[data-wzor-zapewnienia]').content.firstElementChild.cloneNode(true)
                    );
                }
            }

            /* Dokładanie gaśnie po osiągnięciu sufitu: dalsze pozycje i tak
               nie zmieściłyby się przy zajawce. */
            var ile = kolumna.querySelectorAll('[data-zapewnienie]').length;
            var dodaj = kolumna.querySelector('[data-dodaj-zapewnienie]');

            dodaj.disabled = ile >= maxZapewnien;
            kolumna.querySelector('[data-zapewnienia-licznik]').textContent =
                ile + ' / ' + maxZapewnien + ' ' + (ile === 1 ? napisy.linesOne : napisy.linesMany);
        }

        if (rodzaj === 'categories') {
                    var pojemnik = pole(kolumna, 'categories');

                    kolumny.push({
                        type: 'categories',
                        categories: JSON.parse(pojemnik.dataset.wybrane || '[]'),
                        image: segment(pole(kolumna, 'tileImage')),
                        colour: pole(kolumna, 'colour').value,
                        icons: JSON.parse(pojemnik.dataset.ikony || '{}'),
                        button: napisyPola(pole(kolumna, 'button')),
                        direction: segment(pole(kolumna, 'direction')),
                        lead: pole(kolumna, 'lead').querySelector('input[type="checkbox"]').checked,
                        shade: pole(kolumna, 'shade').value,
                        shade_strength: parseInt(pole(kolumna, 'shadeStrength').value, 10),
                        photo_strength: parseInt(pole(kolumna, 'photoStrength').value, 10),
                        veil: pole(kolumna, 'veil').value.trim(),
                        frame: pole(kolumna, 'frame').value.trim()
                    });

                    return;
                }

                kolumny.push({
                    type: 'products',
                    source: pole(kolumna, 'source').value,
                    count: parseInt(pole(kolumna, 'count').value, 10) || 1,
                    display: segment(pole(kolumna, 'display')),
                    category: parseInt(pole(kolumna, 'category').value, 10) || 0,
                    manual: pole(kolumna, 'manual').value.split(',').map(function (n) {
                        return parseInt(n.trim(), 10) || 0;
                    }).filter(function (n) { return n > 0; })
                });
            });

            sekcje.push({
                id: pole(sekcja, 'id').value,
                active: pole(sekcja, 'active').querySelector('input[type="checkbox"]').checked,
                mode: tryb(sekcja),
                parts: podzial,
                skin: pole(sekcja, 'skin').value,
                skin_from: pole(sekcja, 'skin_from').value.trim(),
                skin_to: pole(sekcja, 'skin_to').value.trim(),
                skin_angle: parseInt(pole(sekcja, 'skin_angle').value, 10) || 0,
                skin_image: pole(sekcja, 'skin_image').value.trim(),
                skin_shade: pole(sekcja, 'skin_shade').querySelector('input[type="checkbox"]').checked,
                width: segment(pole(sekcja, 'width')),
                bleed: pole(sekcja, 'bleed').querySelector('input[type="checkbox"]').checked,
                header: pole(sekcja, 'header').querySelector('input[type="checkbox"]').checked,
                icon: pole(sekcja, 'icon').querySelector('input[type="hidden"]').value,
                title: napisyPola(pole(sekcja, 'title')),
                link: napisyPola(pole(sekcja, 'link')),
                link_label: napisyPola(pole(sekcja, 'link_label')),
                columns: kolumny
            });
        });

        poleZapisu.value = JSON.stringify(sekcje);
    });

    odswiezWszystko();
})();
