<?php

namespace App\Filament\Resources\Zpravy;

use App\Filament\Resources\Zpravy\Pages\ListZpravy;
use App\Models\Zprava;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Zprávy z webu (kontaktní formulář). S tím se pracuje denně, proto samostatně
 * v menu, ne v Obsahu webu. U aplikace bez webu není.
 */
class ZpravaResource extends Resource
{
    protected static ?string $model = Zprava::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Zprávy z webu';

    protected static ?string $modelLabel = 'zpráva';

    protected static ?string $pluralModelLabel = 'zprávy z webu';

    protected static ?string $slug = 'zpravy';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return config('sablona.obsah_webu') !== null && (auth()->user()?->jeSpravce() ?? false);
    }

    public static function getNavigationBadge(): ?string
    {
        $pocet = Zprava::query()->neprectene()->count();

        return $pocet > 0 ? (string) $pocet : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Zprava $z) => $z->precteno_at ? null : 'font-semibold')
            ->columns([
                TextColumn::make('created_at')->label('Přišlo')->dateTime('j. n. Y H:i')->sortable(),
                TextColumn::make('jmeno')->label('Od')
                    ->getStateUsing(fn (Zprava $z) => $z->celeJmeno())
                    ->description(fn (Zprava $z) => $z->email)
                    ->searchable(['jmeno', 'prijmeni', 'email']),
                // Exportex: poptávky jsou B2B – firma a jazyk webu (anglická = odpovědět anglicky).
                TextColumn::make('firma')->label('Firma')->searchable()->wrap(),
                TextColumn::make('jazyk')->label('Jazyk')->badge()->color(fn (?string $state) => $state === 'en' ? 'info' : 'gray')
                    ->formatStateUsing(fn (?string $state) => strtoupper((string) $state)),
                TextColumn::make('zprava')->label('Zpráva')->limit(80)->wrap()->searchable(),
                TextColumn::make('stav')->label('Stav')->badge()
                    ->getStateUsing(fn (Zprava $z) => $z->precteno_at ? 'přečteno' : 'nové')
                    ->color(fn (string $state) => $state === 'nové' ? 'warning' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('precteno_at')->label('Přečtené')->nullable()
                    ->trueLabel('Přečtené')->falseLabel('Nové'),
            ])
            ->recordActions([
                Action::make('detail')->label('Otevřít')->icon('heroicon-o-envelope-open')
                    ->modalHeading(fn (Zprava $z) => $z->celeJmeno())
                    ->modalDescription(fn (Zprava $z) => collect([$z->firma, $z->popisJazyka() ? 'psáno '.($z->jazyk === 'en' ? 'anglicky' : 'česky') : null])->filter()->join(' · '))
                    ->modalContent(fn (Zprava $z) => new HtmlString(
                        '<p><a href="mailto:'.e($z->email).'" style="text-decoration: underline;">'.e($z->email).'</a>'
                        .($z->telefon ? ' · '.e($z->telefon) : '').'</p>'
                        .'<p style="color: var(--gray-500); font-size: .85rem;">'.$z->created_at->format('j. n. Y H:i').'</p>'
                        .'<p style="white-space: pre-line; margin-top: 1rem;">'.e($z->zprava).'</p>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->mountUsing(fn (Zprava $z) => $z->precteno_at ?: $z->forceFill(['precteno_at' => now()])->save()),
                Action::make('neprecteno')->label('Označit jako nové')->icon('heroicon-o-envelope')->color('gray')
                    ->visible(fn (Zprava $z) => (bool) $z->precteno_at)
                    ->action(fn (Zprava $z) => $z->forceFill(['precteno_at' => null])->save()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Zatím žádné zprávy')
            ->emptyStateDescription('Zprávy z kontaktního formuláře na webu se objeví tady.');
    }

    public static function getPages(): array
    {
        return ['index' => ListZpravy::route('/')];
    }
}
