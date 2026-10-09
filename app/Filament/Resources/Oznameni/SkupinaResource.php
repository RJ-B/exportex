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

    protected static ?string $navigationLabel = 'Skupiny příjemců';

    protected static ?string $navigationParentItem = 'Oznámení';

    protected static ?string $modelLabel = 'skupina příjemců';

    protected static ?string $pluralModelLabel = 'skupiny příjemců';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('nazev')->label('Název')->required()->maxLength(120)->unique(ignoreRecord: true),
            TextInput::make('popis')->label('Popis')->maxLength(255),
            Select::make('clenove')
                ->label('Členové')
                ->relationship('clenove', 'prijmeni', fn (Builder $query) => $query
                    ->when(! auth()->user()?->jeSuperadmin(), fn (Builder $query) => $query->where('role', '!=', 'superadmin'))
                    ->orderBy('prijmeni')->orderBy('jmeno'))
                ->getOptionLabelFromRecordUsing(fn (User $record) => $record->getFilamentName().' – '.$record->email)
                ->searchable(['jmeno', 'prijmeni', 'email'])
                ->multiple()
                ->helperText('Hledejte jménem, příjmením nebo e-mailem.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nazev')
            ->columns([
                TextColumn::make('nazev')->label('Název')->searchable()->sortable()->description(fn (OznameniSkupina $record) => $record->popis),
                TextColumn::make('clenove_count')->label('Členů')->counts('clenove')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Zatím žádné skupiny')
            ->emptyStateDescription('Skupina je pojmenovaný seznam lidí, kterým pak jde poslat oznámení najednou.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageSkupiny::route('/')];
    }
}
