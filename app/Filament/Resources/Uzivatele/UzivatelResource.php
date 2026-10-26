<?php

namespace App\Filament\Resources\Uzivatele;

use App\Bezpecnost\DruhyKrok;
use App\Filament\Navigace;
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

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return Navigace::nastaveni();
    }

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('administrace.uzivatel_resource.uzivatele');
    }

    public static function getModelLabel(): string
    {
        return __('administrace.uzivatel_resource.uzivatel');
    }

    public static function getPluralModelLabel(): string
    {
        return __('administrace.uzivatel_resource.uzivatele_2');
    }

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
        $role = User::popiskyRoli();

        if (! auth()->user()?->jeSuperadmin()) {
            unset($role['superadmin']);
        }

        return $role;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('jmeno')->label(__('administrace.uzivatel_resource.jmeno'))->required()->maxLength(60),
            TextInput::make('prijmeni')->label(__('administrace.uzivatel_resource.prijmeni'))->required()->maxLength(60),
            TextInput::make('email')
                ->label(__('administrace.uzivatel_resource.e_mail'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('role')
                ->label(__('administrace.uzivatel_resource.role'))
                ->options(fn () => static::nabidkaRoli())
                ->default('klient')
                ->required()
                ->in(fn () => array_keys(static::nabidkaRoli()))
                // Vlastní roli si nikdo nezmění – jinak by se šlo odříznout od administrace.
                ->disabled(fn (?User $record) => $record?->getKey() === auth()->id())
                ->dehydrated(fn (?User $record) => $record?->getKey() !== auth()->id())
                ->helperText(__('administrace.uzivatel_resource.admin_spravuje_aplikaci_a_vidi_vsechno')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('prijmeni')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('prijmeni')
                    ->label(__('administrace.uzivatel_resource.jmeno'))
                    ->getStateUsing(fn (User $record) => $record->getFilamentName())
                    ->sortable()
                    ->searchable(['jmeno', 'prijmeni']),
                TextColumn::make('email')->label(__('administrace.uzivatel_resource.e_mail'))->searchable(),
                TextColumn::make('role')
                    ->label(__('administrace.uzivatel_resource.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => User::popiskyRoli()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'superadmin' => 'danger',
                        'admin' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('heslo')
                    ->label(__('administrace.uzivatel_resource.heslo'))
                    ->getStateUsing(fn (User $record) => $record->password ? __('administrace.uzivatel_resource.nastavene') : __('administrace.uzivatel_resource.ceka_na_nastaveni'))
                    ->color(fn (string $state) => $state === __('administrace.uzivatel_resource.nastavene') ? null : 'warning'),
                TextColumn::make('druhy_krok')
                    ->label(__('administrace.uzivatel_resource.druhy_krok'))
                    ->getStateUsing(fn (User $record) => DruhyKrok::popis($record))
                    ->color(fn (string $state) => $state === __('administrace.druhy_krok.jen_heslo') ? 'gray' : 'success'),
                TextColumn::make('created_at')->label(__('administrace.uzivatel_resource.zalozen'))->date(__('formaty.datum'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('administrace.uzivatel_resource.role'))->options(fn () => static::nabidkaRoli()),
            ])
            ->recordActions([
                Action::make('odkaz')
                    ->label(__('administrace.uzivatel_resource.poslat_odkaz_na_heslo'))
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => __('administrace.uzivatel_resource.na_prijde_odkaz_kterym_si_nastavi', ['email' => $record->email]))
                    ->visible(fn (User $record) => $record->jeSpravce() && static::smiSpravovat($record))
                    ->action(function (User $record) {
                        static::posliOdkaz($record);

                        Notification::make()->title(__('administrace.uzivatel_resource.odkaz_odesel_na', ['email' => $record->email]))->success()->send();
                    }),
                // Kdo přišel o telefon i obnovovací kódy: správce mu druhý krok zruší
                // a člověk si ho po přihlášení heslem nastaví znovu v Zabezpečení.
                Action::make('zrusitDruhyKrok')
                    ->label(__('administrace.uzivatel_resource.zrusit_druhy_krok'))
                    ->icon('heroicon-o-finger-print')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (User $record) => __('administrace.uzivatel_resource.se_pak_prihlasi_jen_heslem_a', ['get_filament_name' => $record->getFilamentName()]))
                    ->visible(fn (User $record) => DruhyKrok::zapnuty($record) && $record->getKey() !== auth()->id() && static::smiSpravovat($record))
                    ->action(function (User $record) {
                        DruhyKrok::zrus($record);

                        Notification::make()->title(__('administrace.uzivatel_resource.druhy_krok_zrusen'))->body($record->getFilamentName())->success()->send();
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
