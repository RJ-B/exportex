<?php

namespace App\Filament\Clusters;

use App\Models\ErrorLog;
use App\Models\MailLog;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

/**
 * Provozní logy – tři samostatné sekce pod jedním rozcestníkem (kanón Sim&Ren,
 * skill `provozni-logy`).
 *
 * Dřív to byla JEDNA sjednocená tabulka s filtrem druhu. Vypadalo to úsporně,
 * ale nefungovalo: audit, mail, chyba a podnět mají nesmiřitelně jiné sloupce,
 * takže společná tabulka ukazovala u každého druhu tři čtvrtiny prázdna a
 * u žádného to podstatné. Mail potřebuje stav a komu, chyba počet výskytů a
 * kdo ji zavřel – do jedné hlavičky se to nevejde.
 *
 * Cluster je Filamentí obdoba záložkového přepínače z kanónu: jeden bod v menu,
 * nahoře pás sekcí, každá s vlastní tabulkou. Barvy a ikony zůstávají naše.
 */
class Logy extends Cluster
{
    /**
     * Přepínač sekcí patří VODOROVNĚ NAD tabulku, na střed – tak to má kanón.
     *
     * Filament dává clusteru ve výchozím stavu svislý sloupec vlevo (`Start`).
     * Vypadá to jako druhé menu vedle menu a hlavně to ukrojí ~300 px z šířky,
     * takže se do tabulky logu nevejdou sloupce a text se láme po slabikách.
     * Vodorovný pás nad obsahem nechá tabulce celou šířku stránky.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Provoz';

    protected static ?string $navigationLabel = 'Logy';

    protected static ?string $clusterBreadcrumb = 'Logy';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSuperadmin() ?? false;
    }

    /**
     * Odznak u položky v menu: jen to, co volá po pozornosti — nevyřešené chyby
     * a maily, které neodešly. Ostatní sekce se počítat nemají, jinak by číslo
     * svítilo pořád a přestalo cokoliv znamenat.
     */
    public static function getNavigationBadge(): ?string
    {
        $pocet = ErrorLog::unresolved()->count()
            + MailLog::failed()->count()
            + MailLog::stuck()->count();

        return $pocet > 0 ? (string) $pocet : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
