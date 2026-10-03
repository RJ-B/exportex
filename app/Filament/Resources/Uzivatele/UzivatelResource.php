<?php

namespace App\Filament\Resources\Uzivatele;

use App\Filament\Resources\Uzivatele\Pages\CreateUzivatel;
use App\Filament\Resources\Uzivatele\Pages\EditUzivatel;
use App\Filament\Resources\Uzivatele\Pages\ListUzivatele;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;

/**
 * Uživatelé a jejich role (superadmin > admin > klient).
 *
 * Spravuje admin (majitel) i superadmin. Superadminy (vývojáře Sim&Ren) admin
 * nevidí ani nezaloží – zakládá je CRM tlačítkem Můj účet správce. Hesla se
 * nikomu nezadávají ani neposílají: nový účet dostane odkaz, kterým si heslo
 * nastaví sám.
 */
class UzivatelResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'uzivatele';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Uživatelé';

    protected static ?string $modelLabel = 'uživatel';

    protected static ?string $pluralModelLabel = 'uživatelé';

    public static function canAccess(): bool
    {
        return auth()->user()?->jeSpravce() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $dotaz = parent::getEloquentQuery();

        // Superadmini jsou vývojáři, ne lidé majitele – nepočítají se a admin je nespravuje.
        return auth()->user()?->jeSuperadmin() ? $dotaz : $dotaz->where('role', '!=', 'superadmin');
    }

    public static function canEdit(Model $record): bool
    {
        return static::smiSpravovat($record);
    }

    public static function canDelete(Model $record): bool
    {
        // Sám sebe nikdo nesmaže – jinak by se šlo omylem zamknout venku.
        return static::smiSpravovat($record) && $record->getKey() !== auth()->id();
    }

    private static function smiSpravovat(User $cil): bool
    {
        $ja = auth()->user();

        return $ja?->jeSuperadmin() || ($ja?->jeSpravce() && ! $cil->jeSuperadmin());
    }

    /** Role, které smí přihlášený přidělit. Superadmina jen superadmin. */
    public static function nabidkaRoli(): array
    {
        $role = User::ROLE_POPISKY;

        if (! auth()->user()?->jeSuperadmin()) {
            unset($role['superadmin']);
        }

        return $role;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('jmeno')->label('Jméno')->required()->maxLength(60),
            TextInput::make('prijmeni')->label('Příjmení')->required()->maxLength(60),
            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('role')
                ->label('Role')
                ->options(fn () => static::nabidkaRoli())
                ->default('klient')
                ->required()
                ->in(fn () => array_keys(static::nabidkaRoli()))
                // Vlastní roli si nikdo nezmění – jinak by se šlo odříznout od administrace.
                ->disabled(fn (?User $record) => $record?->getKey() === auth()->id())
                ->dehydrated(fn (?User $record) => $record?->getKey() !== auth()->id())
                ->helperText('Admin spravuje aplikaci a vidí všechno kromě provozních logů. Klient do administrace nesmí.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('prijmeni')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('prijmeni')
                    ->label('Jméno')
                    ->getStateUsing(fn (User $record) => $record->getFilamentName())
                    ->sortable()
                    ->searchable(['jmeno', 'prijmeni']),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => User::ROLE_POPISKY[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'superadmin' => 'danger',
                        'admin' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('heslo')
                    ->label('Heslo')
                    ->getStateUsing(fn (User $record) => $record->password ? 'nastavené' : 'čeká na nastavení')
                    ->color(fn (string $state) => $state === 'nastavené' ? null : 'warning'),
                TextColumn::make('created_at')->label('Založen')->date('j. n. Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Role')->options(fn () => static::nabidkaRoli()),
            ])
            ->recordActions([
                Action::make('odkaz')
                    ->label('Poslat odkaz na heslo')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => 'Na '.$record->email.' přijde odkaz, kterým si nastaví nové heslo. Dosavadní heslo platí, dokud ho nezmění.')
                    ->visible(fn (User $record) => $record->jeSpravce() && static::smiSpravovat($record))
                    ->action(function (User $record) {
                        static::posliOdkaz($record);

                        Notification::make()->title('Odkaz odešel na '.$record->email.'.')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Odkaz na nastavení hesla do administrace (platí hodinu). Stejná cesta
     * jako „Zapomenuté heslo“ na přihlášení – jen ho pošle správce.
     */
    public static function posliOdkaz(User $user): void
    {
        $token = Password::broker(Filament::getAuthPasswordBroker())->createToken($user);

        $oznameni = app(ResetPassword::class, ['token' => $token]);
        $oznameni->url = Filament::getResetPasswordUrl($token, $user);

        $user->notify($oznameni);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUzivatele::route('/'),
            'create' => CreateUzivatel::route('/create'),
            'edit' => EditUzivatel::route('/{record}/edit'),
        ];
    }
}
