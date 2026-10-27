<?php

namespace App\Filament\Pages;

use App\Support\Pruvodce\Pruvodce;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

/** Úvodní stránka administrace: Dokončete nastavení (DokonceteNastaveni), Stav aplikace (StavAplikace) + widgety projektu. */
class Prehled extends Dashboard
{
    public function getTitle(): string|Htmlable
    {
        return __('administrace.prehled.prehled');
    }

    public static function getNavigationLabel(): string
    {
        return __('administrace.prehled.prehled');
    }

    /** Úplně nahoře, pod ním Zobrazit web (sort -1). */
    protected static ?int $navigationSort = -2;

    protected function getHeaderActions(): array
    {
        return [
            // Skrytý průvodce, který ještě není hotový, jde zase ukázat.
            Action::make('pruvodce')
                ->label(__('pruvodce.pruvodce_nastaveni.pruvodce_nastavenim'))
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->visible(fn () => (auth()->user()?->jeSpravce() ?? false) && Pruvodce::skryty() && ! Pruvodce::hotovo())
                ->action(function () {
                    Pruvodce::skryj(false);
                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
