<?php

use App\Fakturace\FakturaceServiceProvider;
use App\Platby\PlatbyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PostaServiceProvider;

return [
    AppServiceProvider::class,
    PostaServiceProvider::class,
    // Doplněk Platby – bez zapnutého sablona.doplnky.platby nic nenačte.
    PlatbyServiceProvider::class,
    // Doplněk Fakturace – bez zapnutého sablona.doplnky.fakturace nic nenačte.
    FakturaceServiceProvider::class,
    AdminPanelProvider::class,
];
