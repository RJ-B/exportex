/*
 * Oznámení – zvoneček (centrum) a pruh přes web. Bez knihoven, web i administrace.
 *
 * Zvoneček načte seznam až při otevření (GET data-ozn-centrum), klik na položku
 * ji označí jako přečtenou (a s odkazem přejde přes podepsaný proklik), křížek
 * ji dá do archivu. Čas z API je ISO 8601 s posunem – ukazuje se popis ze serveru.
 * Pruh: zavřený si pamatuje prohlížeč (localStorage), odstávka má odpočet.
 */
(function () {
    'use strict';

    function el(tag, trida, text) {
        var e = document.createElement(tag);
        if (trida) { e.className = trida; }
        if (text) { e.textContent = text; }
        return e;
    }

    function posli(url, csrf) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.ok ? r.json() : null; });
    }

    function zvonecek(koren) {
        if (koren.dataset.oznHotovo) { return; }
        koren.dataset.oznHotovo = '1';

        var tlacitko = koren.querySelector('.ozn-tlacitko');
        var panel = koren.querySelector('.ozn-panel');
        var seznam = koren.querySelector('.ozn-seznam');
        var pocet = koren.querySelector('.ozn-pocet');
        var vsePrecteno = koren.querySelector('[data-ozn-vse-precteno]');
        var csrf = koren.dataset.oznCsrf;

        function nastavPocet(n) {
            pocet.hidden = !n;
            pocet.textContent = n > 99 ? '99+' : String(n);
            vsePrecteno.hidden = !n;
            tlacitko.setAttribute('aria-label', 'Oznámení' + (n ? ', nepřečtených ' + n : ''));
        }

        function vykresli(data) {
            if (!data) { return; }
            nastavPocet(data.neprectenych);
            seznam.textContent = '';

            if (!data.polozky.length) {
                seznam.appendChild(el('p', 'ozn-prazdno', 'Zatím tu nic není.'));
                return;
            }

            data.polozky.forEach(function (p) {
                var radek = el('div', 'ozn-polozka' + (p.precteno ? '' : ' ozn-polozka--neprectene'));
                radek.tabIndex = 0;
                radek.setAttribute('role', 'button');
                radek.appendChild(el('span', 'ozn-tecka'));

                var telo = el('span', 'ozn-telo');
                telo.appendChild(el('span', 'ozn-meta', p.druh_nazev + (p.odeslano_popis ? ' · ' + p.odeslano_popis : '')));
                telo.appendChild(el('span', 'ozn-titulek', p.titulek));
                if (p.text) { telo.appendChild(el('span', 'ozn-text', p.text)); }
                radek.appendChild(telo);

                var archiv = el('button', 'ozn-archivovat');
                archiv.type = 'button';
                archiv.title = 'Do archivu';
                archiv.setAttribute('aria-label', 'Do archivu');
                archiv.innerHTML = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M5 5l10 10M15 5L5 15"/></svg>';
                archiv.addEventListener('click', function (e) {
                    e.stopPropagation();
                    posli(p.url_archiv, csrf).then(vykresli);
                });
                radek.appendChild(archiv);

                function otevri() {
                    if (p.odkaz) {
                        window.location.href = p.odkaz;   // proklik zapíše přečteno i proklik
                    } else if (!p.precteno) {
                        posli(p.url_precteno, csrf).then(vykresli);
                    }
                }
                radek.addEventListener('click', otevri);
                radek.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); otevri(); }
                });

                seznam.appendChild(radek);
            });
        }

        function nacti() {
            fetch(koren.dataset.oznCentrum, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(vykresli)
                .catch(function () { seznam.textContent = ''; seznam.appendChild(el('p', 'ozn-prazdno', 'Oznámení se nepodařilo načíst.')); });
        }

        function zavri() {
            panel.hidden = true;
            tlacitko.setAttribute('aria-expanded', 'false');
        }

        tlacitko.addEventListener('click', function (e) {
            e.stopPropagation();
            if (panel.hidden) {
                panel.hidden = false;
                tlacitko.setAttribute('aria-expanded', 'true');
                nacti();
            } else {
                zavri();
            }
        });

        vsePrecteno.addEventListener('click', function () {
            posli(koren.dataset.oznPrectenoVse, csrf).then(vykresli);
        });

        document.addEventListener('click', function (e) {
            if (!panel.hidden && !koren.contains(e.target)) { zavri(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) { zavri(); tlacitko.focus(); }
        });
    }

    function odpocet(prvek) {
        var od = new Date(prvek.dataset.oznOd);   // ISO s posunem → místní čas prohlížeče

        function obnov() {
            var zbyva = Math.floor((od.getTime() - Date.now()) / 1000);
            if (zbyva <= 0) { prvek.textContent = 'právě probíhá'; return; }
            var d = Math.floor(zbyva / 86400), h = Math.floor(zbyva % 86400 / 3600), m = Math.floor(zbyva % 3600 / 60);
            prvek.textContent = 'začne za ' + (d ? d + ' d ' : '') + (d || h ? h + ' h ' : '') + (d ? '' : m + ' min');
            setTimeout(obnov, 30000);
        }
        obnov();
    }

    function pruhy() {
        document.querySelectorAll('[data-ozn-pruh] [data-ozn-zavrit]').forEach(function (krizek) {
            if (krizek.dataset.oznHotovo) { return; }
            krizek.dataset.oznHotovo = '1';
            krizek.addEventListener('click', function () {
                var pruh = krizek.closest('[data-ozn-pruh]');
                pruh.hidden = true;
                try { localStorage.setItem('ozn-pruh:' + pruh.dataset.oznPruh, '1'); } catch (e) {}
            });
        });
        document.querySelectorAll('.ozn-odpocet[data-ozn-od]').forEach(function (p) {
            if (!p.dataset.oznHotovo) { p.dataset.oznHotovo = '1'; odpocet(p); }
        });
    }

    function start() {
        document.querySelectorAll('.ozn-zvonecek').forEach(zvonecek);
        pruhy();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
    // Administrace (Livewire) – po přechodu na jinou stránku bez znovunačtení.
    document.addEventListener('livewire:navigated', start);
})();
