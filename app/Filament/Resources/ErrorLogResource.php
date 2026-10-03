<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Logy;
use App\Filament\Resources\ErrorLogResource\Pages\ListErrorLogs;
use App\Filament\Support\Radky;
use App\Models\ErrorLog;
use Filament\Actions\Action;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Chyby aplikace — AGREGÁT, ne seznam výskytů.
 *
 * Jeden řádek = jedna chyba (otisk), ne jeden pád. Bez agregace by jedna
 * chyba v cyklu zaplavila tabulku tisícem stejných řádků a to podstatné —
 * KOLIKRÁT se to stalo a jestli to pořád trvá — by se z ní nedalo přečíst.
 */
class ErrorLogResource extends Resource
{
    protected static ?string $model = ErrorLog::class;

    protected static ?string $cluster = Logy::class;

    protected static ?string $slug = 'chyby';

    /** Vodorovný přepínač nad tabulkou — vysvětleno v AuditLogResource. */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Chyby';

    protected static ?string $modelLabel = 'chyba';

    protected static ?string $pluralModelLabel = 'chyby';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSuperadmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pocet = ErrorLog::unresolved()->count();

        return $pocet > 0 ? (string) $pocet : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Žádné chyby')
            ->emptyStateDescription('Všechno běží, jak má. Kdyby aplikace spadla, objeví se to tady a portál Sim&Ren to nahlásí.')
            ->columns([
                TextColumn::make('last_seen_at')
                    ->label('Kdy')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->dateTime('j. n. Y H:i')
                    ->description(fn (ErrorLog $record) => $record->last_seen_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('exception')
                    ->label('Co')
                    ->formatStateUsing(fn (ErrorLog $record) => $record->shortException())
                    ->description(fn (ErrorLog $record) => Str::limit($record->message, 100)
                        .($record->shortFile() ? ' — '.$record->shortFile().':'.$record->line : ''))
                    ->wrap()
                    // Nabere zbylou šířku řádku. Přes `style`, ne Tailwind
                    // třídu: Filament si CSS kompiluje sám a třídu, kterou
                    // nikde nepoužívá, do výsledku vůbec nedá.
                    ->extraHeaderAttributes(['style' => 'width:100%'])
                    // Cesty k souborům a názvy tříd nemají mezeru, po které by
                    // se `wrap()` zlomilo — bez tohohle tabulka ujede do strany.
                    ->extraAttributes(['class' => 'simren-zlom'])
                    ->searchable(['exception', 'message']),

                TextColumn::make('occurrences')
                    ->label('Výskytů')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state.'×')
                    // Deset výskytů už není nahodilá kolize, ale něco, co se děje
                    // pořád — ať to v seznamu praští do očí.
                    ->color(fn (int $state) => $state > 10 ? 'danger' : 'gray')
                    ->sortable(),

                // Stejná past jako u „Kdo" v Aktivitě: nevyřešená chyba má
                // `resolved_at` prázdné a Filament by u prázdného stavu odznak
                // vůbec nevykreslil — sloupec „Stav" by u chyb, na kterých
                // záleží nejvíc, zůstal prázdný. Proto `getStateUsing`.
                TextColumn::make('stav')
                    ->label('Stav')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->badge()
                    ->getStateUsing(fn (ErrorLog $record) => $record->resolved_at ? 'Vyřešeno' : 'Nevyřešeno')
                    ->color(fn (string $state) => $state === 'Vyřešeno' ? 'success' : 'danger')
                    // Tři patra POD SEBOU: stav, kdy se vyřešila, kdo ji zavřel.
                    ->description(fn (ErrorLog $record) => $record->resolved_at
                        ? Radky::pod(
                            $record->resolved_at->format('j. n. Y H:i'),
                            $record->resolvedBy?->getFilamentName() ?? 'neznámo kdo',
                        )
                        : null),
            ])
            ->filters([
                // Schválně NENÍ přednastavený: log se otevírá i proto, aby bylo
                // vidět, co se vyřešilo. Předfiltrovaný výpis navíc vypadá jako
                // celý obsah a člověk pak marně hledá chybu, kterou sám zavřel.
                Filter::make('nevyresene')
                    ->label('Jen nevyřešené')
                    ->query(fn (Builder $query) => $query->whereNull('resolved_at')),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ErrorLog $record) => $record->shortException())
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->modalContent(fn (ErrorLog $record) => view('filament.logy.chyba-detail', ['chyba' => $record])),

                Action::make('vyresit')
                    ->label('Vyřešeno')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ErrorLog $record) => $record->resolved_at === null)
                    // Kdo to odbavil se bere z přihlášeného účtu — „vyřešeno"
                    // bez jména je u chyby, která se vrátí, k ničemu.
                    ->action(fn (ErrorLog $record) => $record->update([
                        'resolved_at' => now(),
                        'resolved_by' => auth()->id(),
                    ])),

                Action::make('znovu_otevrit')
                    ->label('Vrátit zpět')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (ErrorLog $record) => $record->resolved_at !== null)
                    ->action(fn (ErrorLog $record) => $record->update([
                        'resolved_at' => null,
                        'resolved_by' => null,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListErrorLogs::route('/')];
    }
}
