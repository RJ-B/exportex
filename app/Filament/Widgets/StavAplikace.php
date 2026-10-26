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

    protected function getHeading(): ?string
    {
        return __('administrace.stav_aplikace.stav_aplikace');
    }

    protected function getStats(): array
    {
        $statistiky = [$this->posta()];

        if (auth()->user()?->jeSuperadmin()) {
            $stav = StavWebu::aktualni();
            $statistiky[] = Stat::make(__('administrace.stav_aplikace.stav_webu'), $stav->nazev())
                ->description($stav === StavWebu::Online ? __('administrace.stav_aplikace.verejny') : __('administrace.stav_aplikace.navstevnici_web_nevidi'))
                ->color($stav->barva())
                ->url(StavWebuStranka::getUrl());

            $chyby = ErrorLog::query()->unresolved()->count();
            $statistiky[] = Stat::make(__('administrace.stav_aplikace.nevyresene_chyby'), $chyby)
                ->description($chyby ? 'Logy → Chyby' : __('administrace.stav_aplikace.zadne'))
                ->color($chyby ? 'danger' : 'success')
                ->url(ErrorLogResource::getUrl());

            $maily = MailLog::query()->failed()->count() + MailLog::query()->stuck()->count();
            $statistiky[] = Stat::make(__('administrace.stav_aplikace.neodeslane_e_maily'), $maily)
                ->description($maily ? 'Logy → E-maily' : __('administrace.stav_aplikace.vse_odeslo'))
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
            ! Propojeni::propojeno() => [__('administrace.stav_aplikace.nepropojeno'), __('administrace.stav_aplikace.propojit_s_postou_e_maily_neodchazeji'), 'warning'],
            $stav['ok'] === false => [__('administrace.stav_aplikace.problem'), mb_substr((string) $stav['zprava'], 0, 90), 'danger'],
            $fronta > 0 => [__('administrace.stav_aplikace.ceka', ['fronta' => $fronta]), __('administrace.stav_aplikace.posta_zrovna_neprijima_preda_se_samo'), 'info'],
            default => [__('administrace.stav_aplikace.v_poradku'), __('administrace.stav_aplikace.pres_postu_z', ['odesilatel' => (Propojeni::odesilatel()['adresa'] ?? '–')]), 'success'],
        };

        return Stat::make(__('administrace.stav_aplikace.posta'), $hodnota)->description($popis)->color($barva)->url(PostaStranka::getUrl());
    }
}
