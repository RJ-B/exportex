<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\Logy;
use App\Filament\Resources\MailLogResource\Pages\ListMailLogs;
use App\Filament\Support\Radky;
use App\Models\MailLog;
use App\Services\MailRetrier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * E-maily — co odešlo, co ne a co se s tím dělalo.
 *
 * Mail na rozdíl od auditu prochází stavy a dá se s ním ještě něco udělat,
 * proto tu je akce „Poslat znovu" (zařadí ho znovu Pošta). Posílá se přes
 * Poštu (posta.simren.cz): „ve frontě Pošty“ = předáno, výsledek přijde
 * webhookem; „selhalo“ = Pošta ho nedoručila nebo odmítla. Stav se schválně skládá ze čtyř údajů
 * (co / kdy / kdo / kolikátý pokus): „Odesláno" samo o sobě neodpoví na
 * otázku, kvůli které se sem člověk dívá — jestli to doopravdy došlo a
 * jestli u toho někdo musel zasáhnout.
 */
class MailLogResource extends Resource
{
    protected static ?string $model = MailLog::class;

    protected static ?string $cluster = Logy::class;

    protected static ?string $slug = 'maily';

    /** Vodorovný přepínač nad tabulkou — vysvětleno v AuditLogResource. */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'E-maily';

    protected static ?string $modelLabel = 'e-mail';

    protected static ?string $pluralModelLabel = 'e-maily';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSuperadmin() ?? false;
    }

    /** Odznak jen z toho, co neodešlo — úspěšné maily po pozornosti nevolají. */
    public static function getNavigationBadge(): ?string
    {
        $pocet = MailLog::failed()->count() + MailLog::stuck()->count();

        return $pocet > 0 ? (string) $pocet : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateHeading('Zatím neodešel žádný e-mail')
            ->emptyStateDescription('Sem se zapisuje každý odchozí e-mail — komu šel, jestli dorazil a kolikátý pokus to byl.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Kdy')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->dateTime('j. n. Y H:i')
                    ->description(fn (MailLog $record) => $record->created_at?->diffForHumans())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Co')
                    ->wrap()
                    ->limit(80)
                    // Chyba patří pod předmět: u selhaného mailu je to první,
                    // co člověk potřebuje vidět, a po úspěšném opakování
                    // zůstává jako stopa po výpadku (proto jen ztlumená).
                    ->description(fn (MailLog $record) => $record->error
                        ? Str::limit($record->error, 90)
                        : null)
                    ->color(fn (MailLog $record) => $record->isFailed() ? 'danger' : null)
                    // Nabere zbylou šířku řádku. Přes `style`, ne Tailwind
                    // třídu: Filament si CSS kompiluje sám a třídu, kterou
                    // nikde nepoužívá, do výsledku vůbec nedá.
                    ->extraHeaderAttributes(['style' => 'width:100%'])
                    // Chybová hláška ze SMTP bývá jeden dlouhý řetězec.
                    ->extraAttributes(['class' => 'simren-zlom'])
                    ->searchable(),

                // `getStateUsing`, ne `formatStateUsing`: u mailu bez adresy
                // (rozpadlá fronta) je prázdný stav a Filament by formátování
                // přeskočil — sloupec „Komu" by zůstal prázdný právě u záznamu,
                // kvůli kterému se do logu chodí.
                TextColumn::make('komu')
                    ->label('Komu')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->getStateUsing(fn (MailLog $record) => $record->prijemciPopis() ?: 'bez adresy')
                    ->description(fn (MailLog $record) => $record->to_name ?: ($record->user_id ? 'účet #'.$record->user_id : null))
                    ->searchable(['to_email', 'to_name']),

                TextColumn::make('status')
                    ->label('Stav')
                    ->extraHeaderAttributes(['style' => 'width:1%;white-space:nowrap'])
                    ->badge()
                    ->formatStateUsing(fn (MailLog $record) => $record->statusLabel())
                    ->color(fn (MailLog $record) => match (true) {
                        $record->wasRetried() => 'warning',
                        $record->status === MailLog::STATUS_SENT => 'success',
                        $record->status === MailLog::STATUS_FAILED => 'danger',
                        $record->status === MailLog::STATUS_QUEUED => 'info',
                        default => 'gray',
                    })
                    // Čtyři patra POD SEBOU: stav, kdy, kdo, kolikátý pokus.
                    // „Odesláno" bez toho, jestli to poslal člověk nebo
                    // automatika, je polovina informace — a slité do jedné
                    // řádky se to nedá přečíst.
                    ->description(fn (MailLog $record) => Radky::pod(
                        ($record->sent_at ?? $record->failed_at ?? $record->created_at)?->format('j. n. Y H:i'),
                        $record->odesilatelPopis(),
                        $record->attempts > 1 ? $record->attempts.'. pokus' : null,
                    ))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options([
                        MailLog::STATUS_SENT => 'Odesláno',
                        MailLog::STATUS_QUEUED => 'Ve frontě Pošty',
                        MailLog::STATUS_FAILED => 'Selhalo',
                        MailLog::STATUS_HELD => 'Zadrženo (test)',
                        MailLog::STATUS_SENDING => 'Odesílá se',
                    ])
                    ->native(false),
                Filter::make('neodeslane')
                    ->label('Jen neodeslané')
                    // Parametr se MUSÍ jmenovat `$query` — Filament vstřikuje
                    // závislosti podle názvu, ne podle typu.
                    ->query(fn (Builder $query) => $query->where(fn ($w) => $w->failed()->orWhere(
                        fn ($s) => $s->stuck()
                    ))),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (MailLog $record) => $record->subject ?: 'E-mail')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->modalContent(fn (MailLog $record) => view('filament.logy.mail-detail', ['mail' => $record])),

                Action::make('poslat_znovu')
                    ->label('Poslat znovu')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Pošta zprávu zařadí znovu – stejný obsah, tíž příjemci.')
                    // Jen nedoručené v Poště – Pošta drží obsah (90 dní).
                    ->visible(fn (MailLog $record) => $record->isRetryable())
                    ->action(function (MailLog $record) {
                        $vysledek = app(MailRetrier::class)->retry($record);

                        Notification::make()
                            ->title($vysledek['message'])
                            ->{$vysledek['ok'] ? 'success' : 'danger'}()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMailLogs::route('/')];
    }
}
