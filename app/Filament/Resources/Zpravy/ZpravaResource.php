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

    public static function getNavigationLabel(): string
    {
        return __('administrace.zprava_resource.zpravy_z_webu');
    }

    public static function getModelLabel(): string
    {
        return __('administrace.zprava_resource.zprava');
    }

    public static function getPluralModelLabel(): string
    {
        return __('administrace.zprava_resource.zpravy_z_webu_2');
    }

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
                TextColumn::make('created_at')->label(__('administrace.zprava_resource.prislo'))->dateTime(__('formaty.datum_cas'))->sortable(),
                TextColumn::make('jmeno')->label(__('administrace.zprava_resource.od'))
                    ->getStateUsing(fn (Zprava $z) => $z->celeJmeno())
                    ->description(fn (Zprava $z) => $z->email)
                    ->searchable(['jmeno', 'prijmeni', 'email']),
                // Exportex: poptávky jsou B2B – firma a jazyk webu (anglická = odpovědět anglicky).
                TextColumn::make('firma')->label(__('administrace.zprava_resource.firma'))->searchable()->wrap(),
                TextColumn::make('jazyk')->label(__('administrace.zprava_resource.jazyk'))->badge()->color(fn (?string $state) => $state === 'en' ? 'info' : 'gray')
                    ->formatStateUsing(fn (?string $state) => strtoupper((string) $state)),
                TextColumn::make('zprava')->label(__('administrace.zprava_resource.zprava_2'))->limit(80)->wrap()->searchable(),
                TextColumn::make('stav')->label(__('administrace.zprava_resource.stav'))->badge()
                    ->getStateUsing(fn (Zprava $z) => $z->precteno_at ? __('administrace.zprava_resource.precteno') : __('administrace.zprava_resource.nove'))
                    ->color(fn (string $state) => $state === __('administrace.zprava_resource.nove') ? 'warning' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('precteno_at')->label(__('administrace.zprava_resource.prectene'))->nullable()
                    ->trueLabel(__('administrace.zprava_resource.prectene'))->falseLabel(__('administrace.zprava_resource.nove_2')),
            ])
            ->recordActions([
                Action::make('detail')->label(__('administrace.zprava_resource.otevrit'))->icon('heroicon-o-envelope-open')
                    ->modalHeading(fn (Zprava $z) => $z->celeJmeno())
                    ->modalDescription(fn (Zprava $z) => collect([$z->firma, $z->popisJazyka() ? __('administrace.zprava_resource.psano', ['hodnota' => ($z->jazyk === 'en' ? 'anglicky' : __('administrace.zprava_resource.cesky'))]) : null])->filter()->join(' · '))
                    ->modalContent(fn (Zprava $z) => new HtmlString(
                        '<p><a href="mailto:'.e($z->email).'" style="text-decoration: underline;">'.e($z->email).'</a>'
                        .($z->telefon ? ' · '.e($z->telefon) : '').'</p>'
                        .'<p style="color: var(--gray-500); font-size: .85rem;">'.$z->created_at->format(__('formaty.datum_cas')).'</p>'
                        .'<p style="white-space: pre-line; margin-top: 1rem;">'.e($z->zprava).'</p>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('administrace.zprava_resource.zavrit'))
                    ->mountUsing(fn (Zprava $z) => $z->precteno_at ?: $z->forceFill(['precteno_at' => now()])->save()),
                Action::make('neprecteno')->label(__('administrace.zprava_resource.oznacit_jako_nove'))->icon('heroicon-o-envelope')->color('gray')
                    ->visible(fn (Zprava $z) => (bool) $z->precteno_at)
                    ->action(fn (Zprava $z) => $z->forceFill(['precteno_at' => null])->save()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('administrace.zprava_resource.zatim_zadne_zpravy'))
            ->emptyStateDescription(__('administrace.zprava_resource.zpravy_z_kontaktniho_formulare_na_webu'));
    }

    public static function getPages(): array
    {
        return ['index' => ListZpravy::route('/')];
    }
}
