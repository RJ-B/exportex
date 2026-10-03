<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/** Úvodní stránka administrace: Stav aplikace (StavAplikace) + widgety projektu. */
class Prehled extends Dashboard
{
    protected static ?string $title = 'Přehled';

    protected static ?string $navigationLabel = 'Přehled';

    /** Úplně nahoře, pod ním Zobrazit web (sort -1). */
    protected static ?int $navigationSort = -2;
}
