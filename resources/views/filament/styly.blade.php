{{-- Vlastní vzhled administrace. Tailwind třídy ve vlastních šablonách nefungují
     (Filament si CSS kompiluje sám a třídu, kterou nepoužívá, do výsledku nedá),
     proto vlastní třídy s proměnnými Filamentu. Používat jen proměnné, které
     existují (--gray-500, --danger-600…) – neexistující se tiše zahodí. --}}
<style>
    /* Kompaktní boční menu (stejné jako v CRM): Filament dává položkám 24px ikony,
       8px odsazení a 28px mezi skupinami – na notebooku se menu nevejde na výšku. */
    .fi-sidebar-nav { padding: 14px 12px !important; row-gap: 14px !important; }
    .fi-sidebar-nav-groups { row-gap: 14px !important; }
    .fi-sidebar-group, .fi-sidebar-group-items { row-gap: 1px !important; }
    .fi-sidebar-item-btn { padding: 5px 8px !important; column-gap: 9px !important; border-radius: 6px !important; }
    .fi-sidebar-item-label { font-size: 13px !important; line-height: 18px !important; }
    .fi-sidebar-item-icon { width: 17px !important; height: 17px !important; }
    .fi-sidebar-group-btn { padding: 2px 8px 4px !important; }
    .fi-sidebar-group-label { font-size: 11px !important; font-weight: 600 !important; text-transform: uppercase; letter-spacing: .04em; }
    .fi-sidebar-group-collapse-btn .fi-icon { width: 14px !important; height: 14px !important; }
    .fi-sidebar-header { height: 52px !important; }
    /* Na počítači je menu jen tak široké, jak je nejdelší položka (Filament: pevných 20rem).
       Obsah stránky se posune za ním; na mobilu zůstává výsuvné menu Filamentu. */
    @media (min-width: 1024px) {
        .fi-main-sidebar { width: max-content !important; min-width: 11rem; max-width: 18rem; }
    }

    /* Cesty k souborům a názvy tříd nemají mezeru – jen tam smí zlom uprostřed. */
    .simren-zlom { overflow-wrap: anywhere; }

    .simren-detail { display: flex; flex-direction: column; gap: 1rem; font-size: .875rem; }
    .simren-udaje { display: grid; grid-template-columns: minmax(8rem, 1fr) 2fr; gap: .4rem 1rem; margin: 0; }
    .simren-udaje dt { color: var(--gray-500); }
    .simren-udaje dd { margin: 0; }
    .simren-slabe { color: var(--gray-500); font-size: .75rem; }
    .simren-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; }
    .simren-ramec { border-radius: .5rem; background: var(--gray-50); padding: .75rem; }
    .dark .simren-ramec { background: rgb(255 255 255 / .05); }
    .simren-chyba { border-radius: .5rem; background: var(--danger-50); padding: .75rem; }
    .dark .simren-chyba { background: rgb(239 68 68 / .1); }
    .simren-chyba strong { color: var(--danger-700); }
    .dark .simren-chyba strong { color: var(--danger-400); }
    .simren-pas { display: grid; grid-template-columns: repeat(auto-fit, minmax(7rem, 1fr)); gap: .75rem; }
    .simren-pas b { display: block; font-size: 1.5rem; font-weight: 600; }
    .simren-tabulka { width: 100%; font-size: .75rem; border-collapse: collapse; }
    .simren-tabulka th { text-align: left; color: var(--gray-500); font-weight: 500; padding: .25rem .75rem .25rem 0; }
    .simren-tabulka td { padding: .375rem .75rem .375rem 0; border-top: 1px solid var(--gray-100); vertical-align: top; }
    .dark .simren-tabulka td { border-color: rgb(255 255 255 / .1); }
    .simren-pred { color: var(--gray-500); text-decoration: line-through; }
    /* Jediné místo, kde je vodorovný posuv v pořádku: zásobník volání. */
    .simren-trace { margin-top: .5rem; overflow-x: auto; border-radius: .5rem; background: var(--gray-900); color: var(--gray-200); padding: .75rem; font-size: .75rem; }
    .simren-pre { white-space: pre-wrap; overflow-wrap: anywhere; margin: .25rem 0 0; }
</style>
