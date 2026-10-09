<?php

namespace App\Filament\Resources\Oznameni;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Enums\StavOznameni;
use App\Enums\ZavaznostOznameni;
use App\Filament\Resources\Oznameni\Pages\CreateOznameni;
use App\Filament\Resources\Oznameni\Pages\EditOznameni;
use App\Filament\Resources\Oznameni\Pages\ListOznameni;
use App\Filament\Resources\Oznameni\Pages\ViewOznameni;
use App\Filament\Support\Radky;
use App\Models\Oznameni;
use App\Models\OznameniSkupina;
use App\Models\User;
use App\Support\Oznameni\Cileni;
use App\Support\Oznameni\NastaveniOznameni;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Oznámení – správce píše zprávu lidem aplikace (docs/oznameni.md).
 *
 * Koncept → (náhled, zkouška sobě) → Odeslat / Naplánovat s potvrzením počtu
 * příjemců. Odeslané se už neupravuje (lidé ho mají v centru a v e-mailu),
 * jen se u něj ukazují čísla a jde ukončit pruh. Odesílá jen
 * App\Support\Oznameni\Odeslani.
 */
class OznameniResource extends Resource
{
    protected static ?string $model = Oznameni::class;

    protected static ?string $slug = 'oznameni';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Oznámení';

    protected static ?string $modelLabel = 'oznámení';

    protected static ?string $pluralModelLabel = 'oznámení';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    /** Upravit jde jen koncept – naplánované se nejdřív vrátí do konceptu (Zrušit plán). */
    public static function canEdit(Model $record): bool
    {
        return static::canAccess() && $record->stav === StavOznameni::Koncept;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess() && $record->stav === StavOznameni::Koncept;
    }

    public static function form(Schema $schema): Schema
    {
        $vybrani = fn (Get $get): bool => $get('cileni.komu') === 'vybrani';
        $pruh = fn (Get $get): bool => in_array(KanalOznameni::Pruh->value, (array) $get('kanaly'), true);
        $druh = fn (Get $get): ?DruhOznameni => DruhOznameni::tryFrom((string) $get('druh'));

        return $schema->columns(1)->components([
            Section::make('Zpráva')->schema([
                ToggleButtons::make('druh')
                    ->label('Druh')
                    ->options(fn () => collect(NastaveniOznameni::druhyZAdministrace())->mapWithKeys(fn (DruhOznameni $d) => [$d->value => $d->nazev()])->all())
                    ->inline()
                    ->required()
                    ->live()
                    ->default(DruhOznameni::Servisni->value)
                    ->helperText(fn (Get $get) => ($d = $druh($get))
                        ? $d->popis().($d->vyzadujeSouhlas() ? ' E-mailem jen těm, kdo souhlasili; každý e-mail má odhlášení jedním kliknutím.' : '')
                        : null),
                TextInput::make('titulek')->label('Titulek')->required()->maxLength(160),
                MarkdownEditor::make('text')
                    ->label('Text')
                    ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                    ->maxLength(5000),
                Grid::make(2)->schema([
                    TextInput::make('odkaz')
                        ->label('Odkaz')
                        ->placeholder('/akce nebo https://…')
                        ->maxLength(500)
                        ->rule('regex:#^(/(?!/)|https?://)#')
                        ->validationMessages(['regex' => 'Odkaz začíná lomítkem (stránka webu) nebo https://.'])
                        ->helperText('Nepovinné. Kam tlačítko v oznámení vede.'),
                    TextInput::make('odkaz_text')->label('Text tlačítka')->placeholder('Zobrazit')->maxLength(60),
                ]),
            ]),

            Section::make('Kudy')->schema([
                CheckboxList::make('kanaly')
                    ->label('Kanály')
                    ->options(collect(KanalOznameni::cases())->mapWithKeys(fn (KanalOznameni $k) => [$k->value => $k->nazev()])->all())
                    ->descriptions(collect(KanalOznameni::cases())->mapWithKeys(fn (KanalOznameni $k) => [$k->value => $k->popis()])->all())
                    ->disableOptionWhen(fn (string $value, Get $get) => ! KanalOznameni::from($value)->dostupny()
                        || ($value === KanalOznameni::Pruh->value && ! $druh($get)?->smiPruh()))
                    ->required()
                    ->live()
                    ->columns(2)
                    ->default([KanalOznameni::Centrum->value, KanalOznameni::Email->value]),
                Grid::make(2)->visible($pruh)->schema([
                    ToggleButtons::make('zavaznost')
                        ->label('Barva pruhu')
                        ->options(collect(ZavaznostOznameni::cases())->mapWithKeys(fn (ZavaznostOznameni $z) => [$z->value => $z->nazev()])->all())
                        ->colors(collect(ZavaznostOznameni::cases())->mapWithKeys(fn (ZavaznostOznameni $z) => [$z->value => $z->barva()])->all())
                        ->inline()
                        ->default(ZavaznostOznameni::Info->value)
                        ->required()
                        ->columnSpanFull(),
                    DateTimePicker::make('pruh_od')->label('Pruh ukázat od')->seconds(false)->helperText('Prázdné = hned po odeslání.'),
                    DateTimePicker::make('pruh_do')->label('Pruh schovat po')->seconds(false)->after('pruh_od')->helperText('Prázdné = dokud ho neukončíte.'),
                ]),
                Grid::make(2)->visible(fn (Get $get) => $druh($get) === DruhOznameni::Servisni)->schema([
                    DateTimePicker::make('udalost_od')->label('Odstávka od')->seconds(false)->helperText('Nepovinné. Pruh ukáže odpočet, e-mail termín.'),
                    DateTimePicker::make('udalost_do')->label('Odstávka do')->seconds(false)->after('udalost_od'),
                ]),
            ]),

            Section::make('Komu')->schema([
                ToggleButtons::make('cileni.komu')
                    ->label('Komu')
                    ->options(['vsichni' => 'Všem', 'vybrani' => 'Vybraným'])
                    ->inline()
                    ->live()
                    ->required()
                    ->default('vsichni')
                    ->helperText('Všem = správcům i klientům aplikace. Každý dostane oznámení jen jednou, i když spadá do víc výběrů.'),
                CheckboxList::make('cileni.role')
                    ->label('Role')
                    ->options(Cileni::ROLE)
                    ->columns(2)
                    ->visible($vybrani),
                Select::make('cileni.skupiny')
                    ->label('Skupiny')
                    ->multiple()
                    ->options(fn () => OznameniSkupina::query()->orderBy('nazev')->pluck('nazev', 'id')->all())
                    ->visible(fn (Get $get) => $vybrani($get) && OznameniSkupina::query()->exists())
                    ->helperText('Skupiny příjemců se spravují v Oznámení → Skupiny příjemců.'),
                Select::make('cileni.uzivatele')
                    ->label('Vybraní lidé')
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::hledejLidi($search))
                    ->getOptionLabelsUsing(fn (array $values) => static::popisyLidi($values))
                    ->visible($vybrani)
                    ->helperText('Hledejte jménem, příjmením nebo e-mailem a vybírejte postupně – kolik chcete.'),
            ]),

            Section::make('Kdy')->schema([
                DateTimePicker::make('naplanovano_na')
                    ->label('Naplánovat na')
                    ->seconds(false)
                    ->minDate(fn (?Oznameni $record) => $record?->naplanovano_na?->isPast() ? null : now()->startOfMinute())
                    ->helperText('Prázdné = odejde hned po kliknutí na Odeslat.'),
            ]),
        ]);
    }

    /** Hledání lidí do cílení: jméno, příjmení, e-mail. Superadminy admin nevidí. @return array<int, string> */
    public static function hledejLidi(string $hledat): array
    {
        $hledat = trim($hledat);

        return User::query()
            ->when(! auth()->user()?->jeSuperadmin(), fn (Builder $query) => $query->where('role', '!=', 'superadmin'))
            ->where(fn (Builder $query) => $query
                ->where('jmeno', 'like', "%{$hledat}%")
                ->orWhere('prijmeni', 'like', "%{$hledat}%")
                ->orWhere('email', 'like', "%{$hledat}%"))
            ->orderBy('prijmeni')->orderBy('jmeno')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->getKey() => $u->getFilamentName().' – '.$u->email])
            ->all();
    }

    /** @return array<int, string> */
    public static function popisyLidi(array $ids): array
    {
        return User::query()->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (User $u) => [$u->getKey() => $u->getFilamentName().' – '.$u->email])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            // Rozpracované a naplánované nahoře (k řešení), pak odeslané od nejnovějšího.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("case stav when 'odesila' then 0 when 'naplanovano' then 1 when 'koncept' then 2 else 3 end")
                ->orderByDesc('id'))
            ->paginated([25, 50, 100])
            ->recordUrl(fn (Oznameni $record) => static::canEdit($record)
                ? static::getUrl('edit', ['record' => $record])
                : static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('titulek')
                    ->label('Oznámení')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Oznameni $record) => Radky::pod($record->druh->nazev().' · '.Cileni::popis((array) $record->cileni))),
                TextColumn::make('stav')
                    ->label('Stav')
                    ->badge()
                    ->getStateUsing(fn (Oznameni $record) => $record->stav->nazev())
                    ->color(fn (Oznameni $record) => $record->stav->barva()),
                TextColumn::make('kanaly')
                    ->label('Kudy')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Oznameni $record) => array_map(fn (KanalOznameni $k) => $k->nazev(), $record->kanalyEnum())),
                TextColumn::make('kdy')
                    ->label('Kdy')
                    ->getStateUsing(fn (Oznameni $record) => match ($record->stav) {
                        StavOznameni::Naplanovano => 'na '.$record->naplanovano_na?->format('j. n. Y H:i'),
                        StavOznameni::Koncept => 'upraveno '.$record->updated_at?->format('j. n. Y H:i'),
                        default => $record->odeslano_at?->format('j. n. Y H:i') ?? '–',
                    }),
                TextColumn::make('pocet_prijemcu')
                    ->label('Příjemců')
                    ->getStateUsing(fn (Oznameni $record) => $record->pocet_prijemcu === null
                        ? ($record->jeProVsechny() && $record->maKanal(KanalOznameni::Pruh) && $record->stav === StavOznameni::Odeslano ? 'všichni (pruh)' : '–')
                        : number_format($record->pocet_prijemcu, 0, ',', ' ')),
            ])
            ->filters([
                SelectFilter::make('stav')->label('Stav')
                    ->options(collect(StavOznameni::cases())->mapWithKeys(fn (StavOznameni $s) => [$s->value => $s->nazev()])->all()),
                SelectFilter::make('druh')->label('Druh')
                    ->options(collect(DruhOznameni::cases())->mapWithKeys(fn (DruhOznameni $d) => [$d->value => $d->nazev()])->all()),
            ])
            ->emptyStateHeading('Zatím žádná oznámení')
            ->emptyStateDescription('Napište lidem aplikace novinku nebo je upozorněte na odstávku – v centru oznámení, pruhem přes web nebo e-mailem.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOznameni::route('/'),
            'create' => CreateOznameni::route('/create'),
            'edit' => EditOznameni::route('/{record}/edit'),
            'view' => ViewOznameni::route('/{record}'),
        ];
    }
}
