<?php

namespace App\Filament\Pages;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Models\OznameniPrijemce;
use App\Support\Oznameni\NastaveniOznameni;
use App\Support\Oznameni\Predvolby;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Moje oznámení v administraci – co přišlo přihlášenému (zvoneček → Zobrazit
 * všechna) a jeho předvolby. Totéž co stránka /oznameni na webu, jen ve
 * vzhledu administrace; přečtení se zapisuje k témuž příjemci.
 */
class MojeOznameni extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $slug = 'moje-oznameni';

    public function getTitle(): string|Htmlable
    {
        return __('administrace.moje_oznameni.moje_oznameni');
    }

    protected static bool $shouldRegisterNavigation = false;

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => OznameniPrijemce::query()->vCentru(auth()->user())->with('oznameni'))
            ->defaultSort('id', 'desc')
            ->paginated([25, 50])
            ->recordClasses(fn (OznameniPrijemce $record) => $record->precteno_at ? null : 'font-semibold')
            ->columns([
                TextColumn::make('titulek')
                    ->label(__('administrace.moje_oznameni.oznameni'))
                    ->getStateUsing(fn (OznameniPrijemce $record) => $record->oznameni->titulek)
                    ->description(fn (OznameniPrijemce $record) => Str::limit($record->oznameni->textProsty(), 120))
                    ->wrap(),
                TextColumn::make('druh')
                    ->label(__('administrace.moje_oznameni.druh'))
                    ->badge()
                    ->getStateUsing(fn (OznameniPrijemce $record) => $record->oznameni->druh->nazev())
                    ->color(fn (OznameniPrijemce $record) => $record->oznameni->druh->barva()),
                TextColumn::make('odeslano')
                    ->label(__('administrace.moje_oznameni.prislo'))
                    ->getStateUsing(fn (OznameniPrijemce $record) => $record->oznameni->odeslano_at?->format(__('formaty.datum_cas'))),
                TextColumn::make('stav')
                    ->label(__('administrace.moje_oznameni.stav'))
                    ->badge()
                    ->getStateUsing(fn (OznameniPrijemce $record) => match (true) {
                        (bool) $record->archivovano_at => __('administrace.moje_oznameni.v_archivu'),
                        (bool) $record->precteno_at => __('administrace.moje_oznameni.precteno'),
                        default => __('administrace.moje_oznameni.nove'),
                    })
                    ->color(fn (string $state) => $state === __('administrace.moje_oznameni.nove') ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('zobrazit')
                    ->label(__('administrace.moje_oznameni.zobrazit'))
                    ->options(['neprectene' => __('administrace.moje_oznameni.neprectene'), 'archiv' => __('administrace.moje_oznameni.archiv')])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'neprectene' => $query->neprectene(),
                        'archiv' => $query->whereNotNull('archivovano_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('otevrit')
                    ->label(__('administrace.moje_oznameni.otevrit'))
                    ->icon('heroicon-o-envelope-open')
                    ->modalHeading(fn (OznameniPrijemce $record) => $record->oznameni->titulek)
                    ->modalContent(fn (OznameniPrijemce $record) => new HtmlString(
                        '<div class="simren-detail"><p class="simren-slabe">'.e($record->oznameni->druh->nazev()).' · '.e($record->oznameni->odeslano_at?->format(__('formaty.datum_cas'))).'</p>'
                        .'<div>'.$record->oznameni->textHtml().'</div>'
                        .(($odkaz = $record->odkazProkliku()) ? '<p><a href="'.e($odkaz).'" style="text-decoration: underline;">'.e($record->oznameni->odkaz_text ?: __('administrace.moje_oznameni.zobrazit')).'</a></p>' : '')
                        .'</div>'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('administrace.moje_oznameni.zavrit'))
                    ->mountUsing(fn (OznameniPrijemce $record) => $record->oznacPrectene()),
                Action::make('archiv')
                    ->label(fn (OznameniPrijemce $record) => $record->archivovano_at ? __('administrace.moje_oznameni.vratit_z_archivu') : __('administrace.moje_oznameni.do_archivu'))
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->action(fn (OznameniPrijemce $record) => $record->forceFill([
                        'archivovano_at' => $record->archivovano_at ? null : now(),
                        'precteno_at' => $record->precteno_at ?? now(),
                    ])->save()),
            ])
            ->emptyStateHeading(__('administrace.moje_oznameni.zatim_zadna_oznameni'))
            ->emptyStateDescription(__('administrace.moje_oznameni.co_vam_aplikace_nebo_spravce_posle'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prectenoVse')
                ->label(__('administrace.moje_oznameni.oznacit_vse_jako_prectene'))
                ->icon('heroicon-o-check')
                ->color('gray')
                ->visible(fn () => OznameniPrijemce::query()->vCentru(auth()->user())->neprectene()->exists())
                ->action(fn () => OznameniPrijemce::query()->vCentru(auth()->user())->neprectene()->update(['precteno_at' => now()])),
            Action::make('predvolby')
                ->label(__('administrace.moje_oznameni.predvolby'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->modalHeading(__('administrace.moje_oznameni.predvolby_oznameni'))
                ->modalDescription(__('administrace.moje_oznameni.co_a_kudy_vam_chodi_provozni'))
                ->fillForm(fn () => collect(Predvolby::matice(auth()->user()))
                    ->map(fn (array $kanaly) => collect($kanaly)->map(fn (array $v) => $v['zapnuto'])->all())->all())
                ->schema(fn () => collect(NastaveniOznameni::druhy())->map(fn (DruhOznameni $druh) => Section::make($druh->nazev())
                    ->description($druh->popis())
                    ->compact()
                    ->schema([
                        Grid::make(2)->schema(collect(KanalOznameni::predvolby())->map(fn (KanalOznameni $kanal) => Toggle::make($druh->value.'.'.$kanal->value)
                            ->label($kanal === KanalOznameni::Centrum ? __('administrace.moje_oznameni.v_oznamenich') : $kanal->nazev())
                            ->disabled(in_array($kanal, $druh->zamceneKanaly(), true))
                            ->helperText($druh->vyzadujeSouhlas() && $kanal->vnejsi() ? __('administrace.moje_oznameni.zapnutim_souhlasite_se_zasilanim') : null))->all()),
                    ]))->all())
                ->action(function (array $data) {
                    Predvolby::uloz(auth()->user(), $data, 'predvolby', request());
                    Notification::make()->title(__('administrace.moje_oznameni.predvolby_ulozene'))->success()->send();
                }),
        ];
    }
}
