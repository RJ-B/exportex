<?php

namespace App\Filament\Widgets;

use App\Enums\StavWebu;
use App\Filament\Pages\Posta as PostaStranka;
use App\Filament\Pages\StavWebu as StavWebuStranka;
use App\Filament\Resources\ErrorLogResource;
use App\Filament\Resources\MailLogResource;
use App\Models\ErrorLog;
use App\Models\MailLog;
use App\Support\Posta;
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
        $kontrola = Posta::posledniKontrola();

        // Bez schránky v aplikaci může pošta pořád chodit podle nastavení serveru (.env).
        $zeServeru = ! in_array(config('mail.default'), ['log', 'array'], true);

        [$hodnota, $popis, $barva] = match (true) {
            ! Posta::kompletni() && $zeServeru => ['Ze serveru', 'posílá se podle .env – schránku nastavte v aplikaci', 'info'],
            ! Posta::kompletni() => ['Chybí', 'schránka není nastavená – e-maily neodchází', 'warning'],
            ($kontrola['ok'] ?? null) === false => ['Nefunguje', 'přihlášení ke schránce selhalo', 'danger'],
            default => ['V pořádku', Posta::nacti()['uzivatel'], 'success'],
        };

        return Stat::make('Pošta', $hodnota)->description($popis)->color($barva)->url(PostaStranka::getUrl());
    }
}
