<?php

namespace App\Support\Posta;

use App\Filament\Pages\Posta as PostaStranka;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** GET /posta/propojeni/navrat – kód z Pošty se vymění za klíče ze serveru aplikace. */
class NavratZPosty
{
    public function __invoke(Request $request, Propojeni $propojeni): RedirectResponse
    {
        if (! $request->user()?->jeSpravce()) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        try {
            $vysledek = $propojeni->dokonci($request->query());
            $prevzeti = $vysledek['prevzeti_vysledek'] ?? null;
            Notification::make()->title('Propojeno s Poštou')
                ->body($prevzeti
                    ? $prevzeti['zprava'].($prevzeti['ok'] ? ' Pošli zkušební e-mail – po jeho odeslání se stará schránka z aplikace smaže.' : '')
                    : 'Smí posílat z: '.(collect(Propojeni::adresy())->pluck('adresa')->implode(', ') ?: '–').'.')
                ->{$prevzeti && ! $prevzeti['ok'] ? 'warning' : 'success'}()
                ->persistent((bool) $prevzeti)
                ->send();
        } catch (Throwable $e) {
            Notification::make()->title('Propojení s Poštou se nepovedlo')->body($e->getMessage())->danger()->persistent()->send();
        }

        return redirect()->to(PostaStranka::getUrl());
    }
}
