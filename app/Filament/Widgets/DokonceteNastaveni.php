<?php

namespace App\Filament\Widgets;

use App\Support\Pruvodce\Pruvodce;
use Filament\Widgets\Widget;

/**
 * Přehled → Dokončete nastavení: kroky z config/pruvodce.php se zaškrtnutím
 * u hotových. Zmizí sám, když je vše hotovo; správce ho jde skrýt (a na Přehledu
 * zase ukázat tlačítkem Průvodce nastavením).
 */
class DokonceteNastaveni extends Widget
{
    protected string $view = 'filament.widgets.dokoncete-nastaveni';

    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (auth()->user()?->jeSpravce() ?? false) && ! Pruvodce::skryty() && ! Pruvodce::hotovo();
    }

    public function skryt(): void
    {
        abort_unless(auth()->user()?->jeSpravce(), 403);
        Pruvodce::skryj();
        $this->redirect(request()->header('Referer') ?: filament()->getUrl());
    }

    protected function getViewData(): array
    {
        return ['kroky' => Pruvodce::stav()];
    }
}
