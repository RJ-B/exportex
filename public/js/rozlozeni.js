/**
 * Drobečková navigace v horní liště (App\Filament\Rozlozeni).
 *
 * Stránka Filamentu má drobečky v hlavičce (sekce › záznam › Upravit) – tady se
 * zkopírují do lišty a v hlavičce se na počítači schovají (rozlozeni.css).
 * Stránka bez drobečků (Přehled, Zabezpečení…) dostane skupinu menu › položku,
 * případně nadpis stránky. Kopíruje se, nepřesouvá – Livewire si hlavičku
 * překresluje sám a přesunutý prvek by mu chyběl.
 */
(() => {
    const text = (el) => (el?.textContent || '').replace(/\s+/g, ' ').trim();

    function sestav() {
        const cil = document.querySelector('.simren-drobecky');

        if (!cil) {
            return;
        }

        const zdroj = document.querySelector('.fi-main .fi-header .fi-breadcrumbs');

        if (zdroj) {
            cil.innerHTML = zdroj.outerHTML;
            document.body.classList.add('simren-ma-drobecky');

            return;
        }

        document.body.classList.remove('simren-ma-drobecky');

        const polozka = document.querySelector('.fi-sidebar-item.fi-active');
        const skupina = polozka?.closest('.fi-sidebar-group')?.dataset.groupLabel;
        const nazev = text(polozka?.querySelector('.fi-sidebar-item-label')) || text(document.querySelector('.fi-main .fi-header-heading'));
        const nadpis = text(document.querySelector('.fi-main .fi-header-heading'));
        const casti = [skupina, nazev];

        if (nadpis && nadpis !== nazev) {
            casti.push(nadpis);
        }

        const ol = document.createElement('ol');
        ol.className = 'simren-drobecky-list';

        casti.filter(Boolean).forEach((cast, i) => {
            if (i > 0) {
                const oddelovac = document.createElement('li');
                oddelovac.className = 'simren-oddelovac';
                oddelovac.setAttribute('aria-hidden', 'true');
                oddelovac.textContent = '›';
                ol.appendChild(oddelovac);
            }

            const li = document.createElement('li');
            li.textContent = cast;
            ol.appendChild(li);
        });

        cil.replaceChildren(ol);
    }

    document.addEventListener('DOMContentLoaded', sestav);
    document.addEventListener('livewire:navigated', sestav);

    // Po uložení se může změnit název záznamu v drobečcích stránky.
    const sleduj = () => window.Livewire?.hook('morphed', () => requestAnimationFrame(sestav));

    if (window.Livewire) {
        sleduj();
    } else {
        document.addEventListener('livewire:init', sleduj);
    }

    if (document.readyState !== 'loading') {
        sestav();
    }
})();
