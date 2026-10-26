<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

/** Úvodní stránka administrace: Stav aplikace (StavAplikace) + widgety projektu. */
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
}
