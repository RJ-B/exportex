<?php

use App\Platby\PlatbyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PostaServiceProvider;

return [
    AppServiceProvider::class,
    PostaServiceProvider::class,
    // Doplněk Platby – bez zapnutého sablona.doplnky.platby nic nenačte.
    PlatbyServiceProvider::class,
    AdminPanelProvider::class,
];
