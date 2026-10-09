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

    protected static ?string $navigationLabel = 'Platby';

    protected static ?string $modelLabel = 'platba';

    protected static ?string $pluralModelLabel = 'platby';

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
        return NastaveniPlateb::stitek() ? 'Testovací platby – nic se nestrhne' : NastaveniPlateb::prehled()['chyba'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')->label('Založena')->dateTime('j. n. Y H:i')->sortable()
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
                TextColumn::make('popis')->label('Za co')
                    ->description(fn (Platba $p) => $p->reference)
                    ->searchable(['popis', 'reference', 'verejne_id', 'externi_id'])
                    ->wrap(),
                TextColumn::make('email')->label('Zákazník')
                    ->formatStateUsing(fn (Platba $p) => $p->celeJmeno() ?: $p->email)
                    ->description(fn (Platba $p) => $p->celeJmeno() ? $p->email : null)
                    ->searchable(['email', 'jmeno', 'prijmeni']),
                TextColumn::make('castka')->label('Částka')->alignEnd()->sortable()
                    ->formatStateUsing(fn (Platba $p) => $p->castkaKc())
                    ->description(fn (Platba $p) => $p->vraceno > 0 ? 'vráceno '.Platba::kc($p->vraceno, $p->mena) : null)
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
                TextColumn::make('stav')->label('Stav')->badge()
                    ->formatStateUsing(fn (Platba $p) => $p->stav->popis())
                    ->color(fn (Platba $p) => $p->stav->barva())
                    ->description(fn (Platba $p) => $p->zaplaceno_v?->format('j. n. Y H:i'))
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
                TextColumn::make('brana')->label('Brána')->badge()
                    ->formatStateUsing(fn (Platba $p) => $p->nazevBrany().($p->testovaci() ? ' · test' : ''))
                    ->color(fn (Platba $p) => $p->testovaci() ? 'warning' : 'gray')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap']),
            ])
            ->filters([
                SelectFilter::make('brana')->label('Brána')
                    ->options(['comgate' => 'Comgate', 'moone' => 'Mo.one', 'simulace' => 'Simulace'])
                    ->placeholder('Vše'),
                SelectFilter::make('rezim')->label('Režim')
                    ->options(collect(Rezim::cases())->mapWithKeys(fn (Rezim $r) => [$r->value => $r->popis()])->all())
                    ->placeholder('Vše'),
            ])
            ->recordUrl(fn (Platba $p) => static::getUrl('view', ['record' => $p]))
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('Zatím žádné platby')
            ->emptyStateDescription('Platby z webu (a odkazy k zaplacení z tlačítka Nová platba) se objeví tady.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatby::route('/'),
            'view' => ViewPlatba::route('/{record}'),
        ];
    }
}
