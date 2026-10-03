<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Logy;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Filament\Support\Radky;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Aktivita — kdo co v aplikaci změnil.
 *
 * Neměnná událost: nedá se upravit ani smazat (jen vypršet retencí). Proto
 * žádné akce kromě detailu — audit, do kterého jde sáhnout, není audit.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $cluster = Logy::class;

    protected static ?string $slug = 'aktivita';

    /**
     * Přepínač sekcí VODOROVNĚ nad tabulkou (kanón provozních logů), ne svisle
     * vlevo, jak to Filament dělá u clusteru ve výchozím stavu. Svislý sloupec
     * ukrojí ~300 px z šířky a tabulka logu se pak láme po slabikách.
     *
     * POZOR kde to musí být: u stránek resourcu si Filament tuhle volbu bere
     * z RESOURCU (Resources\Pages\Page::getSubNavigationPosition() volá
     * getResource()::getSubNavigationPosition()). Nastavit ji na stránce ani
     * na clusteru NEMÁ ŽÁDNÝ ÚČINEK — sekce tiše zůstanou vlevo.
     */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Aktivita';

    protected static ?string $modelLabel = 'záznam';

    protected static ?string $pluralModelLabel = 'aktivita';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSuperadmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateIcon('heroicon-o-list-bullet')
            ->emptyStateHeading('Zatím žádná aktivita')
            ->emptyStateDescription('Sem se zapisuje každá změna nastavení a účtů – kdo ji udělal a co přesně změnil.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Kdy')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->dateTime('j. n. Y H:i:s')
                    ->sortable(),

                // Kdo = jeden sloupec, tři patra POD SEBOU: jméno, role, IP.
                // Odděleně by to zabralo tři sloupce a rozbilo řádek; sražené
                // na jednu řádku oddělovačem se zas role a IP slijí v jeden
                // nečitelný řetězec. Kanón chce tři samostatné řádky.
                // POZOR: skládá se přes `getStateUsing`, ne `formatStateUsing`.
                // Filament u prázdného stavu formátování ani popisek vůbec
                // nespustí — u sloupce „kdo" je přitom prázdno ta nejhorší
                // odpověď: nepozná se systémová změna od chybějícího údaje.
                TextColumn::make('kdo')
                    ->label('Kdo')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->getStateUsing(fn (AuditLog $record) => $record->user?->getFilamentName()
                        ?? ($record->user_id ? 'smazaný účet #'.$record->user_id : 'systém'))
                    ->description(fn (AuditLog $record) => Radky::pod(
                        User::ROLE_POPISKY[$record->user_role] ?? $record->user_role ?? 'bez role',
                        $record->ip_address ?: 'bez IP',
                    ))
                    ->searchable(['user_role', 'ip_address']),

                TextColumn::make('event')
                    ->label('Událost')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->fontFamily('mono')
                    ->size('sm')
                    ->searchable(),

                TextColumn::make('summary')
                    ->label('Souhrn')
                    ->wrap()
                    // Nabere zbylou šířku řádku. Přes `style`, ne Tailwind
                    // třídu: Filament si CSS kompiluje sám a třídu, kterou
                    // nikde nepoužívá, do výsledku vůbec nedá.
                    ->extraHeaderAttributes(['style' => 'width:100%'])
                    ->limit(160)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Událost')
                    // Nabídka se skládá z toho, co v logu opravdu je — pevný
                    // seznam by zastaral při první nové události.
                    ->options(fn () => AuditLog::query()
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->all()),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (AuditLog $record) => $record->event.' · '.$record->created_at->format('j. n. Y H:i:s'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->modalContent(fn (AuditLog $record) => view('filament.logy.audit-detail', ['zaznam' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
