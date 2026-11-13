/**
 * Drzewo kategorii panelu: jedna maszyneria dla wszystkich ekranów.
 *
 * Dwie odmiany, ta sama logika pod spodem:
 *   `quinceDrzewo`: wybór JEDNEJ kategorii, przejście pod jej adres,
 *   `quinceDrzewoWiele`: zaznaczanie wielu, wartości idą z formularzem.
 *
 * Dane przychodzą z PHP jako tablica obiektów: `id`, `name`, `depth`, `parent`,
 * `sciezka` (przodkowie od rodzica w górę), `dzieci` (czy ma potomków).
 */

/* Wspólna część: co jest widoczne, co rozwinięte, jak działa szukanie. */
function quinceDrzewoBaza(kategorie, rozwinieteNaStarcie) {
    return {
        kategorie: kategorie,
        otwarte: false,
        szukaj: '',
        rozwiniete: rozwinieteNaStarcie || [],

        get podpisLiczby() {
            return this.kategorie.filter(this.widoczna, this).length + ' / ' + this.kategorie.length;
        },

        /* Przy szukaniu drzewo spłaszcza się do trafień: zwinięta gałąź nie może
           chować wyniku, bo wtedy wyszukiwarka wygląda na zepsutą. */
        widoczna: function (k) {
            if (this.szukaj.trim() !== '') {
                return k.name.toLowerCase().indexOf(this.szukaj.trim().toLowerCase()) !== -1;
            }

            return k.sciezka.every(function (id) {
                return this.rozwiniete.indexOf(id) !== -1;
            }, this);
        },

        przelacz: function (id) {
            var i = this.rozwiniete.indexOf(id);

            if (i === -1) {
                this.rozwiniete.push(id);
            } else {
                this.rozwiniete.splice(i, 1);
            }
        },

        rozwinWszystko: function () {
            this.rozwiniete = this.kategorie.map(function (k) { return k.id; });
        },

        zwinWszystko: function () {
            this.rozwiniete = [];
        },
    };
}

/** Wybór jednej kategorii: przycisk z nazwą, klik w gałąź przechodzi pod jej adres. */
function quinceDrzewo(kategorie, wybranaId) {
    var wybrana = kategorie.find(function (k) { return k.id === wybranaId; });
    var baza = quinceDrzewoBaza(kategorie, wybrana ? wybrana.sciezka.slice() : []);

    baza.wybranaId = wybranaId;

    baza.nazwaWybranej = wybrana ? wybrana.name : '';

    return baza;
}

/**
 * Wybór jednej kategorii DO POLA FORMULARZA.
 *
 * Różni się od `quinceDrzewo` tym, że nie prowadzi nigdzie: zapisuje wybór
 * w zmiennej nadrzędnego komponentu. Dlatego działa też w powtarzalniku, gdzie
 * każdy wiersz ma własne pole, a nazwa pola powstaje z indeksu.
 */
function quinceDrzewoPole(kategorie) {
    var baza = quinceDrzewoBaza(kategorie, []);

    baza.nazwaDla = function (id) {
        var k = this.kategorie.find(function (x) { return x.id === Number(id); });

        return k ? k.name : '';
    };

    /* Po wyborze rozwijamy gałąź wybranej kategorii, żeby przy ponownym otwarciu
       było widać, gdzie się jest. Inaczej drzewo wraca zwinięte do korzenia. */
    baza.pokazSciezke = function (id) {
        var k = this.kategorie.find(function (x) { return x.id === Number(id); });

        this.rozwiniete = k ? k.sciezka.slice() : [];
    };

    return baza;
}

/**
 * Pole wyszukiwania kategorii, po nazwie ALBO po numerze.
 *
 * Dla miejsc, gdzie rozwinięte drzewo zabiera pół ekranu: wiersze powtarzalnika
 * i listy wykluczeń.
 *
 * `wiele` przełącza na zaznaczanie wielu: wybrane lądują na liście pod polem
 * i każde da się zdjąć osobno.
 */
function quinceSzukajkaKategorii(kategorie, wybrane, wiele) {
    return {
        kategorie: kategorie,
        wiele: !!wiele,
        fraza: '',
        // Wybór pojedynczy trzyma liczbę, wielokrotny tablicę, ale obsługa jest
        // jedna, więc pojedynczy też siedzi w tablicy i wychodzi z niej przy zapisie.
        wybrane: (wiele ? (wybrane || []) : (wybrane ? [wybrane] : [])).map(Number).filter(Boolean),
        otwarte: false,


        nazwaDla: function (id) {
            var k = this.kategorie.find(function (x) { return x.id === Number(id); });

            return k ? k.name : '';
        },

        /* Lista zawsze POD polem, jak każda inna lista w panelu. Gdy pod polem
           brakuje miejsca, przewijamy stronę zamiast odwracać kierunek. */
        otworz: function (element) {
            this.otwarte = true;

            var brakuje = 280 - (window.innerHeight - element.getBoundingClientRect().bottom);

            if (brakuje > 0) {
                window.scrollBy({ top: brakuje + 20, behavior: 'smooth' });
            }
        },

        /* Ścieżka nad nazwą, bo „Mercedes" bywa i pod filtrami, i pod olejami. */
        sciezkaDla: function (k) {
            return k.sciezka.slice().reverse().map(function (id) {
                return this.nazwaDla(id);
            }, this).filter(Boolean).join(' › ');
        },

        /* Lista pokazuje się po wejściu w pole, a nie dopiero przy pisaniu.
           Puste pole daje początek listy, wpisanie zawęża. */
        get trafienia() {
            if (!this.otwarte) {
                return [];
            }

            var f = this.fraza.trim().toLowerCase();

            return this.kategorie.filter(function (k) {
                if (this.wybrane.indexOf(k.id) !== -1) {
                    return false;
                }

                return f === '' || String(k.id) === f || k.name.toLowerCase().indexOf(f) !== -1;
            }, this).slice(0, 12);
        },

        dodaj: function (id) {
            if (this.wiele) {
                if (this.wybrane.indexOf(id) === -1) {
                    this.wybrane.push(id);
                }
            } else {
                this.wybrane = [id];
                // Wybór pojedynczy zamyka listę: nie ma czego dobierać.
                this.otwarte = false;
            }

            this.fraza = '';
        },

        zdejmij: function (id) {
            var i = this.wybrane.indexOf(id);

            if (i !== -1) {
                this.wybrane.splice(i, 1);
            }
        },
    };
}

/**
 * Zaznaczanie wielu kategorii.
 *
 * Zaznaczone trzymamy w tablicy, a nie w atrybutach pól: dzięki temu licznik
 * „zaznaczono 4" i przyciski „zaznacz widoczne / odznacz wszystkie" widzą to
 * samo, co pola wyboru, i nie rozjeżdżają się przy szukaniu.
 */
function quinceDrzewoWiele(kategorie, zaznaczone) {
    var baza = quinceDrzewoBaza(kategorie, []);

    baza.zaznaczone = (zaznaczone || []).map(Number);

    baza.przelaczZaznaczenie = function (id) {
        var i = this.zaznaczone.indexOf(id);

        if (i === -1) {
            this.zaznaczone.push(id);
        } else {
            this.zaznaczone.splice(i, 1);
        }
    };

    baza.zaznaczWidoczne = function () {
        this.kategorie.filter(this.widoczna, this).forEach(function (k) {
            if (this.zaznaczone.indexOf(k.id) === -1) {
                this.zaznaczone.push(k.id);
            }
        }, this);
    };

    baza.odznaczWszystkie = function () {
        this.zaznaczone = [];
    };

    /* Podpis na przycisku, gdy drzewo jest schowane w menu. Nazwa przy jednym
       wyborze, liczba przy kilku. */
    baza.podpisWyboru = function (pusty) {
        if (this.zaznaczone.length === 0) {
            return pusty || '';
        }

        if (this.zaznaczone.length === 1) {
            var k = this.kategorie.find(function (x) { return x.id === this.zaznaczone[0]; }, this);

            return k ? k.name : '';
        }

        return this.zaznaczone.length;
    };

    return baza;
}
