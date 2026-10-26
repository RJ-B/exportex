<?php

namespace App\Platby\Filament;

use App\Platby\Filament\Pages\ListPlatby;
use App\Platby\Filament\Pages\ViewPlatba;
use App\Platby\NastaveniPlateb;
use App\Platby\Platba;
use App\Platby\Rezim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Platby (doplněk Platby): přehled se stavem, částkou, bránou a časem, detail
 * s historií stavů, ruční ověření stavu, zrušení a vrácení. S tím se pracuje
 * denně – samostatně v menu, před Obsahem webu.
 */
class PlatbaResource extends Resource
{
    protected static ?string $model = Platba::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationLabel(): string
    {
        return __('platby.platba_resource.platby');
    }

    public static function getModelLabel(): string
    {
        return __('platby.platba_resource.platba');
    }

    public static function getPluralModelLabel(): string
    {
        return __('platby.platba_resource.platby_2');
    }

    protected static ?string $slug = 'platby';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return NastaveniPlateb::stitek() ? 'test' : (NastaveniPlateb::prehled()['chyba'] ? '!' : null);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return NastaveniPlateb::stitek() ? 'warning' : 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return NastaveniPlateb::stitek() ? (NastaveniPlateb::prehled()['skutecna'] ? __('platby.platba_resource.skutecne_platby_pres_testovaci_branu') : __('platby.platba_resource.testovaci_platby_nic_se_nestrhne')) : NastaveniPlateb::prehled()['chyba'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')->label(__('platby.platba_resource.zalozena'))->dateTime(__('formaty.datum_cas'))->sortable()
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
                TextColumn::make('popis')->label(__('platby.platba_resource.za_co'))
                    ->description(fn (Platba $p) => $p->reference)
                    ->searchable(['popis', 'reference', 'verejne_id', 'externi_id'])
                    ->wrap(),
                TextColumn::make('email')->label(__('platby.platba_resource.zakaznik'))
                    ->formatStateUsing(fn (Platba $p) => $p->celeJmeno() ?: $p->email)
                    ->description(fn (Platba $p) => $p->celeJmeno() ? $p->email : null)
                    ->searchable(['email', 'jmeno', 'prijmeni']),
                TextColumn::make('castka')->label(__('platby.platba_resource.castka'))->alignEnd()->sortable()
                    ->formatStateUsing(fn (Platba $p) => $p->castkaKc())
                    ->description(fn (Platba $p) => $p->vraceno > 0 ? __('platby.platba_resource.vraceno', ['kc' => Platba::kc($p->vraceno, $p->mena)]) : null)
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
                TextColumn::make('stav')->label(__('platby.platba_resource.stav'))->badge()
                    ->formatStateUsing(fn (Platba $p) => $p->stav->popis())
                    ->color(fn (Platba $p) => $p->stav->barva())
                    ->description(fn (Platba $p) => $p->zaplaceno_v?->format(__('formaty.datum_cas')))
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
                TextColumn::make('brana')->label(__('platby.platba_resource.brana'))->badge()
                    ->formatStateUsing(fn (Platba $p) => $p->nazevBrany().($p->testovaci() ? ' · test' : ''))
                    ->color(fn (Platba $p) => $p->testovaci() ? 'warning' : 'gray')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
            ])
            ->filters([
                SelectFilter::make('brana')->label(__('platby.platba_resource.brana'))
                    ->options(['comgate' => __('platby.platba_resource.comgate'), 'moone' => 'Mo.one', 'simulace' => __('platby.platba_resource.simulace')])
                    ->placeholder(__('platby.platba_resource.vse')),
                SelectFilter::make('rezim')->label(__('platby.platba_resource.rezim'))
                    ->options(collect(Rezim::cases())->mapWithKeys(fn (Rezim $r) => [$r->value => $r->popis()])->all())
                    ->placeholder(__('platby.platba_resource.vse')),
            ])
            ->recordUrl(fn (Platba $p) => static::getUrl('view', ['record' => $p]))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading(__('platby.platba_resource.zatim_zadne_platby'))
            ->emptyStateDescription(__('platby.platba_resource.platby_z_webu_a_odkazy_k'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatby::route('/'),
            'view' => ViewPlatba::route('/{record}'),
        ];
    }
}
