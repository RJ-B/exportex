<?php

namespace App\Filament\Widgets;

use App\Enums\StavWebu;
use App\Filament\Pages\Posta as PostaStranka;
use App\Filament\Pages\StavWebu as StavWebuStranka;
use App\Filament\Resources\ErrorLogResource;
use App\Filament\Resources\MailLogResource;
use App\Models\ErrorLog;
use App\Models\MailLog;
use App\Support\Posta\Odchozi;
use App\Support\Posta\Propojeni;
use App\Support\Posta\StavPosty;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Přehled: co v aplikaci potřebuje pozornost – v každém projektu stejné.
 * Projekt přidává vlastní widgety (nové poptávky, objednávky…) vedle.
 *
 * Pošta vidí všichni správci (neodchází-li, klient nedostává poptávky);
 * chyby, nedoručené e-maily a stav webu jen superadmin.
 */
class StavAplikace extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    // Stav je to první, co má být na Přehledu vidět – ne až po dalším požadavku.
    protected static bool $isLazy = false;

    protected ?string $heading = 'Stav aplikace';

    protected function getStats(): array
    {
        $statistiky = [$this->posta()];

        if (auth()->user()?->jeSuperadmin()) {
            $stav = StavWebu::aktualni();
            $statistiky[] = Stat::make('Stav webu', $stav->nazev())
                ->description($stav === StavWebu::Online ? 'veřejný' : 'návštěvníci web nevidí')
                ->color($stav->barva())
                ->url(StavWebuStranka::getUrl());

            $chyby = ErrorLog::query()->unresolved()->count();
            $statistiky[] = Stat::make('Nevyřešené chyby', $chyby)
                ->description($chyby ? 'Logy → Chyby' : 'žádné')
                ->color($chyby ? 'danger' : 'success')
                ->url(ErrorLogResource::getUrl());

            $maily = MailLog::query()->failed()->count() + MailLog::query()->stuck()->count();
            $statistiky[] = Stat::make('Neodeslané e-maily', $maily)
                ->description($maily ? 'Logy → E-maily' : 'vše odešlo')
                ->color($maily ? 'danger' : 'success')
                ->url(MailLogResource::getUrl());
        }

        return $statistiky;
    }

    private function posta(): Stat
    {
        $stav = StavPosty::proZdravi();
        $fronta = Odchozi::query()->count();

        [$hodnota, $popis, $barva] = match (true) {
            ! Propojeni::propojeno() => ['Nepropojeno', 'propojit s Poštou – e-maily neodcházejí', 'warning'],
            $stav['ok'] === false => ['Problém', mb_substr((string) $stav['zprava'], 0, 90), 'danger'],
            $fronta > 0 => ['Čeká '.$fronta, 'Pošta zrovna nepřijímá – předá se samo', 'info'],
            default => ['V pořádku', 'přes Poštu z '.(Propojeni::odesilatel()['adresa'] ?? '–'), 'success'],
        };

        return Stat::make('Pošta', $hodnota)->description($popis)->color($barva)->url(PostaStranka::getUrl());
    }
}
