<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

/**
 * Obsah webu jako jedna položka menu s vodorovnou lištou sekcí
 * (config sablona.obsah_webu = 'sekce'). Sekce do něj zařazuje
 * App\Filament\Support\CastObsahuWebu.
 */
class ObsahWebu extends Cluster
{
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Obsah webu';

    protected static ?string $clusterBreadcrumb = 'Obsah webu';

    protected static ?string $slug = 'obsah-webu';

    /** Hned pod sekcemi provozu webu, nad Nastavením. */
    protected static ?int $navigationSort = 50;
}
