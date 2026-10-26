<?php

namespace App\Filament\Resources\Oznameni;

use App\Filament\Resources\Oznameni\Pages\ManageSkupiny;
use App\Models\OznameniSkupina;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Skupiny příjemců oznámení (Stálí zákazníci, Členové klubu…). V cílení se
 * kombinují s rolemi a vybranými lidmi – každý dostane oznámení jednou.
 */
class SkupinaResource extends Resource
{
    protected static ?string $model = OznameniSkupina::class;

    protected static ?string $slug = 'oznameni-skupiny';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationLabel(): string
    {
        return __('administrace.skupina_resource.skupiny_prijemcu');
    }

    public static function getNavigationParentItem(): ?string
    {
        return OznameniResource::getNavigationLabel();
    }

    public static function getModelLabel(): string
    {
        return __('administrace.skupina_resource.skupina_prijemcu');
    }

    public static function getPluralModelLabel(): string
    {
        return __('administrace.skupina_resource.skupiny_prijemcu_2');
    }

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('nazev')->label(__('administrace.skupina_resource.nazev'))->required()->maxLength(120)->unique(ignoreRecord: true),
            TextInput::make('popis')->label(__('administrace.skupina_resource.popis'))->maxLength(255),
            Select::make('clenove')
                ->label(__('administrace.skupina_resource.clenove'))
                ->relationship('clenove', 'prijmeni', fn (Builder $query) => $query
                    ->when(! auth()->user()?->jeSuperadmin(), fn (Builder $query) => $query->where('role', '!=', 'superadmin'))
                    ->orderBy('prijmeni')->orderBy('jmeno'))
                ->getOptionLabelFromRecordUsing(fn (User $record) => $record->getFilamentName().' – '.$record->email)
                ->searchable(['jmeno', 'prijmeni', 'email'])
                ->multiple()
                ->helperText(__('administrace.skupina_resource.hledejte_jmenem_prijmenim_nebo_e_mailem')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nazev')
            ->columns([
                TextColumn::make('nazev')->label(__('administrace.skupina_resource.nazev'))->searchable()->sortable()->description(fn (OznameniSkupina $record) => $record->popis),
                TextColumn::make('clenove_count')->label(__('administrace.skupina_resource.clenu'))->counts('clenove')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('administrace.skupina_resource.zatim_zadne_skupiny'))
            ->emptyStateDescription(__('administrace.skupina_resource.skupina_je_pojmenovany_seznam_lidi'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageSkupiny::route('/')];
    }
}
