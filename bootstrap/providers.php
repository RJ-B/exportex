<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PostaServiceProvider;

return [
    AppServiceProvider::class,
    PostaServiceProvider::class,
    AdminPanelProvider::class,
];
