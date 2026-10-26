/*
 * Výběr ve vzhledu administrace místo nativní roletky prohlížeče.
 *
 * Formulářové Selecty a filtry tabulek kreslí Filament sám (->native(false)
 * v AppServiceProvider). Pár roletek ale Filament vykresluje natvrdo jako
 * <select> a nastavit to nejde: počet záznamů na stránku u stránkování,
 * měsíc v kalendáři výběru data, seskupení a řazení tabulky. V Safari se
 * z nich otvírá systémové menu přes celou stránku – proto se tady převlečou.
 *
 * <select> zůstává v DOM (Livewire i Alpine na něj mají wire:model / x-model)
 * a jen se schová (CSS: select:has(+ .simren-vyber)). Za něj se vloží tlačítko
 * s vlastním seznamem; výběr nastaví hodnotu selectu a vyvolá change, takže
 * se uloží stejně jako dřív. Livewire při překreslení vložené prvky smaže –
 * MutationObserver je hned vrátí.
 *
 * Ovládání: šipky, Home/End, Enter/mezerník vybere, Esc zavře, psaní skočí
 * na možnost. Výjimka pro konkrétní <select>: atribut data-simren-nativni.
 */
(function () {
    var SIPKA = '<svg class="simren-vyber-sipka" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
        + '<path stroke-linecap="round" stroke-linejoin="round" d="m6 8 4 4 4-4"/></svg>';
    var FAJFKA = '<svg class="simren-vyber-fajfka" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
        + '<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>';
    var pocitadlo = 0;
    var otevreny = null;

    function bezDiakritiky(text) {
        return String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function vhodny(select) {
        return !select.multiple && select.size <= 1 && !select.hasAttribute('data-simren-nativni');
    }

    function moznosti(select) {
        return Array.prototype.filter.call(select.options, function (o) { return !o.hidden; });
    }

    /* Tlačítko a seznam za selectem – vytvoří je, nebo vrátí existující. */
    function vylepsi(select) {
        var tlacitko = select.nextElementSibling;

        if (tlacitko && tlacitko.classList.contains('simren-vyber')) {
            obnov(select, tlacitko);
            return;
        }

        var id = 'simren-vyber-' + (++pocitadlo);
        tlacitko = document.createElement('button');
        tlacitko.type = 'button';
        tlacitko.className = 'simren-vyber';
        tlacitko.id = id;
        tlacitko.setAttribute('role', 'combobox');
        tlacitko.setAttribute('aria-haspopup', 'listbox');
        tlacitko.setAttribute('aria-expanded', 'false');
        tlacitko.setAttribute('aria-controls', id + '-seznam');

        if (select.classList.contains('fi-fo-date-time-picker-month-select')) {
            tlacitko.classList.add('is-mesic');
        }

        var popis = select.getAttribute('aria-label')
            || (select.labels && select.labels[0] && select.labels[0].textContent.trim());
        if (popis) {
            tlacitko.setAttribute('aria-label', popis);
        }

        tlacitko.innerHTML = '<span class="simren-vyber-hodnota"></span>' + SIPKA;

        var panel = document.createElement('div');
        panel.className = 'simren-vyber-panel';
        panel.id = id + '-seznam';
        panel.setAttribute('role', 'listbox');
        panel.hidden = true;

        select.after(tlacitko, panel);
        tlacitko._simrenSelect = select;
        tlacitko._simrenPanel = panel;
        obnov(select, tlacitko);
    }

    // Mění DOM jen při skutečné změně – jinak by se MutationObserver točil dokola.
    function obnov(select, tlacitko) {
        var vybrana = select.options[select.selectedIndex];
        var text = vybrana ? vybrana.textContent.trim() : '';
        var hodnota = tlacitko.querySelector('.simren-vyber-hodnota');

        if (hodnota.textContent !== text) {
            hodnota.textContent = text;
        }
        if (tlacitko.disabled !== select.disabled) {
            tlacitko.disabled = select.disabled;
        }
    }

    function projdi() {
        document.querySelectorAll('select').forEach(function (select) {
            if (vhodny(select)) {
                vylepsi(select);
            }
        });
    }

    /* ---------- seznam ---------- */

    function vykresli(tlacitko) {
        var select = tlacitko._simrenSelect;
        var panel = tlacitko._simrenPanel;
        panel.innerHTML = '';

        moznosti(select).forEach(function (o, i) {
            var radek = document.createElement('div');
            radek.className = 'simren-vyber-moznost';
            radek.id = panel.id + '-' + i;
            radek.setAttribute('role', 'option');
            radek.dataset.value = o.value;
            radek.setAttribute('aria-selected', o.selected ? 'true' : 'false');
            if (o.disabled) {
                radek.setAttribute('aria-disabled', 'true');
            }
            radek.innerHTML = '<span></span>' + FAJFKA;
            radek.firstChild.textContent = o.textContent.trim();
            panel.appendChild(radek);
        });
    }

    function radky(tlacitko) {
        return Array.prototype.slice.call(tlacitko._simrenPanel.querySelectorAll('[role="option"]:not([aria-disabled])'));
    }

    /*
     * Panel má position: fixed, ať ho neořízne tabulka ani okno s posuvníkem.
     * V okně Filamentu (transform) se fixed počítá od okna – proto se panel
     * položí na 0,0, změří a posune o rozdíl. Když se dolů nevejde, otevře se nahoru.
     */
    function umisti(tlacitko) {
        var panel = tlacitko._simrenPanel;
        var k = tlacitko.getBoundingClientRect();
        var okraj = 8;
        var mezera = 4;

        panel.style.left = '0px';
        panel.style.top = '0px';
        panel.style.minWidth = Math.max(k.width, 120) + 'px';
        panel.style.maxHeight = '';

        var pocatek = panel.getBoundingClientRect();
        var dole = window.innerHeight - k.bottom - mezera - okraj;
        var nahore = k.top - mezera - okraj;
        var nahoru = panel.offsetHeight > dole && nahore > dole;
        var vyska = Math.min(panel.offsetHeight, Math.max(120, nahoru ? nahore : dole));
        panel.style.maxHeight = vyska + 'px';

        var x = k.left;
        if (x + panel.offsetWidth > window.innerWidth - okraj) {
            x = Math.max(okraj, window.innerWidth - okraj - panel.offsetWidth);
        }
        var y = nahoru ? k.top - mezera - vyska : k.bottom + mezera;

        panel.style.left = (x - pocatek.left) + 'px';
        panel.style.top = (y - pocatek.top) + 'px';
    }

    function otevri(tlacitko) {
        if (tlacitko.disabled) {
            return;
        }
        if (otevreny && otevreny !== tlacitko) {
            zavri(otevreny, false);
        }

        vykresli(tlacitko);
        tlacitko._simrenPanel.hidden = false;
        tlacitko.setAttribute('aria-expanded', 'true');
        otevreny = tlacitko;

        var vybrany = tlacitko._simrenPanel.querySelector('[aria-selected="true"]') || radky(tlacitko)[0];
        zvyrazni(tlacitko, vybrany);
        umisti(tlacitko);
    }

    function zavri(tlacitko, vratitFokus) {
        tlacitko._simrenPanel.hidden = true;
        tlacitko.setAttribute('aria-expanded', 'false');
        tlacitko.removeAttribute('aria-activedescendant');
        if (otevreny === tlacitko) {
            otevreny = null;
        }
        if (vratitFokus) {
            tlacitko.focus();
        }
    }

    function zvyrazni(tlacitko, radek) {
        tlacitko._simrenPanel.querySelectorAll('.is-aktivni').forEach(function (r) { r.classList.remove('is-aktivni'); });
        if (!radek) {
            return;
        }
        radek.classList.add('is-aktivni');
        tlacitko.setAttribute('aria-activedescendant', radek.id);
        radek.scrollIntoView({ block: 'nearest' });
    }

    function vyber(tlacitko, radek) {
        var select = tlacitko._simrenSelect;

        if (radek && select.value !== radek.dataset.value) {
            select.value = radek.dataset.value;
            // Alpine x-model i wire:model poslouchají change; input pro jistotu.
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        obnov(select, tlacitko);
        zavri(tlacitko, true);
    }

    function posun(tlacitko, o) {
        var vse = radky(tlacitko);
        if (!vse.length) {
            return;
        }
        var i = vse.indexOf(tlacitko._simrenPanel.querySelector('.is-aktivni'));
        var novy = o === 'zacatek' ? 0
            : o === 'konec' ? vse.length - 1
            : i === -1 ? 0 : Math.min(vse.length - 1, Math.max(0, i + o));
        zvyrazni(tlacitko, vse[novy]);
    }

    function doplnit(tlacitko, znak) {
        var ted = Date.now();
        tlacitko._simrenPsani = ted - (tlacitko._simrenPsaniCas || 0) > 700 ? znak : (tlacitko._simrenPsani || '') + znak;
        tlacitko._simrenPsaniCas = ted;
        var hledane = bezDiakritiky(tlacitko._simrenPsani);
        var nalez = radky(tlacitko).find(function (r) { return bezDiakritiky(r.textContent.trim()).indexOf(hledane) === 0; });
        if (nalez) {
            zvyrazni(tlacitko, nalez);
        }
    }

    /* ---------- události ---------- */

    document.addEventListener('click', function (e) {
        var tlacitko = e.target.closest && e.target.closest('.simren-vyber');
        if (tlacitko) {
            e.preventDefault();
            tlacitko.getAttribute('aria-expanded') === 'true' ? zavri(tlacitko, true) : otevri(tlacitko);
            return;
        }

        var radek = e.target.closest && e.target.closest('.simren-vyber-panel [role="option"]');
        if (radek && otevreny && !radek.hasAttribute('aria-disabled')) {
            // Klik uvnitř panelu nesmí probublat k „klik mimo“ okolního
            // okna nebo kalendáře – panel je jeho součástí.
            e.stopImmediatePropagation();
            vyber(otevreny, radek);
        }
    }, true);

    // Klik do seznamu nesmí vzít fokus tlačítku (zavřelo by se okolí).
    document.addEventListener('mousedown', function (e) {
        if (e.target.closest && e.target.closest('.simren-vyber-panel')) {
            e.preventDefault();
            return;
        }
        if (otevreny && !otevreny.contains(e.target)) {
            zavri(otevreny, false);
        }
    }, true);

    document.addEventListener('mouseover', function (e) {
        var radek = e.target.closest && e.target.closest('.simren-vyber-panel [role="option"]:not([aria-disabled])');
        if (radek && otevreny && otevreny._simrenPanel.contains(radek)) {
            zvyrazni(otevreny, radek);
        }
    });

    document.addEventListener('keydown', function (e) {
        var tlacitko = e.target.closest && e.target.closest('.simren-vyber');
        if (!tlacitko) {
            return;
        }

        var otevreno = tlacitko.getAttribute('aria-expanded') === 'true';
        var znak = e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey;

        if (!otevreno) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) !== -1 || znak) {
                e.preventDefault();
                e.stopPropagation();
                otevri(tlacitko);
                if (znak && e.key !== ' ') {
                    doplnit(tlacitko, e.key);
                }
            }
            return;
        }

        switch (e.key) {
            case 'ArrowDown': posun(tlacitko, 1); break;
            case 'ArrowUp': posun(tlacitko, -1); break;
            case 'PageDown': posun(tlacitko, 8); break;
            case 'PageUp': posun(tlacitko, -8); break;
            case 'Home': posun(tlacitko, 'zacatek'); break;
            case 'End': posun(tlacitko, 'konec'); break;
            case 'Enter':
            case ' ':
                vyber(tlacitko, tlacitko._simrenPanel.querySelector('.is-aktivni'));
                break;
            case 'Escape':
                // Jen seznam – ne okno nebo kalendář, ve kterém je.
                zavri(tlacitko, true);
                break;
            case 'Tab':
                zavri(tlacitko, false);
                return;
            default:
                if (znak) {
                    doplnit(tlacitko, e.key);
                    break;
                }
                return;
        }

        e.preventDefault();
        e.stopPropagation();
    }, true);

    // <label for> míří na schovaný select – fokus patří tlačítku.
    document.addEventListener('focusin', function (e) {
        var t = e.target;
        if (t instanceof HTMLSelectElement && t.nextElementSibling && t.nextElementSibling.classList.contains('simren-vyber')) {
            t.nextElementSibling.focus();
        }
    });

    window.addEventListener('resize', function () { if (otevreny) umisti(otevreny); });
    document.addEventListener('scroll', function (e) {
        if (otevreny && !otevreny._simrenPanel.contains(e.target)) {
            umisti(otevreny);
        }
    }, true);

    /*
     * Hodnotu selectu mění i Alpine (x-model, šipky měsíců v kalendáři) – to
     * žádnou událost nevyvolá. Po každém kliknutí a psaní se proto popisky
     * srovnají, až Alpine doběhne.
     */
    function obnovVse() {
        document.querySelectorAll('.simren-vyber').forEach(function (tlacitko) {
            if (tlacitko._simrenSelect) {
                obnov(tlacitko._simrenSelect, tlacitko);
            }
        });
    }
    ['click', 'keyup', 'input', 'change'].forEach(function (udalost) {
        document.addEventListener(udalost, function () { setTimeout(obnovVse, 0); });
    });

    function start() {
        projdi();

        var naplanovano = false;
        new MutationObserver(function (zmeny) {
            // Vlastní změny (popisek, seznam) nic nového nepřinesly.
            var cizi = zmeny.some(function (z) {
                var cil = z.target.nodeType === 1 ? z.target : z.target.parentElement;
                return !(cil && cil.closest && cil.closest('.simren-vyber, .simren-vyber-panel'));
            });

            // Projde se hned v mikroúloze – dřív, než prohlížeč stihne
            // vykreslit odhalenou nativní roletku.
            if (!cizi || naplanovano) {
                return;
            }
            naplanovano = true;
            queueMicrotask(function () {
                naplanovano = false;
                projdi();
            });
        }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['selected', 'disabled'] });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
