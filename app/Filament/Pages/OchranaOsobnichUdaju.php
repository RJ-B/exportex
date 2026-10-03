<?php

namespace App\Filament\Pages;

use App\Filament\Support\CastObsahuWebu;
use App\Support\OchranaUdaju;
use App\Support\SekceWebu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Obsah webu → Ochrana osobních údajů. Má ji každý web, vypnout nejde.
 *
 * Text je vzor (pravni/_ochrana-obsah) a skládá se sám ze Základních údajů
 * a z toho, co web dělá; tady se doplňuje jen to, co se liší web od webu.
 */
class OchranaOsobnichUdaju extends Page
{
    use CastObsahuWebu;

    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Ochrana osobních údajů';

    protected static ?string $title = 'Ochrana osobních údajů';

    protected static ?string $slug = 'ochrana-osobnich-udaju';

    /** Za sekcemi webu, před SEO a měřením. */
    protected static ?int $navigationSort = 900;

    public ?array $data = [];

    /** Chybějící údaj = text není úplný. */
    public static function getNavigationBadge(): ?string
    {
        return OchranaUdaju::chybejici() === [] ? null : 'doplnit';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function mount(): void
    {
        $udaje = OchranaUdaju::nacti();
        $udaje['smlouvy'] = $udaje['smlouvy'] === '1';

        $this->form->fill($udaje);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Co ještě chybí')
                    ->visible(fn () => OchranaUdaju::chybejici() !== [])
                    ->schema([
                        \Filament\Schemas\Components\Text::make(fn () => new HtmlString(
                            'Text ochrany osobních údajů není úplný. Doplňte: <strong>'.e(implode(', ', OchranaUdaju::chybejici())).'</strong>.'
                        ))->color('warning'),
                    ]),
                Section::make('Platnost')
                    ->description('Správce (firma, sídlo, IČO, kontakty) se bere z Hlavičky a patičky.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('ucinnost_od')->label('Účinné od')->native(false)->displayFormat('j. n. Y'),
                        TextInput::make('poverenec')->label('Pověřenec pro ochranu osobních údajů')
                            ->placeholder('nejmenovali jsme')
                            ->helperText('Jen když ho firma má (povinně např. úřady, nemocnice).'),
                    ]),
                Section::make('Kontaktní formulář')
                    ->description(fn () => SekceWebu::zapnuta('formular') ? 'Na webu je – do textu se tahle část zařadí.' : 'Formulář je vypnutý – tahle část se na webu nezobrazí.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('formular_udaje')->label('Jaké údaje formulář sbírá')->rows(2)->columnSpanFull()
                            ->helperText('IP adresa a čas odeslání se doplní samy.'),
                        TextInput::make('formular_doba')->label('Jak dlouho zprávy uchováváte'),
                    ]),
                Section::make('Smlouvy a zákazníci')
                    ->columns(2)
                    ->schema([
                        Toggle::make('smlouvy')->label('Přes web nebo po něm uzavíráme smlouvy (objednávky, zakázky)')->live()->columnSpanFull(),
                        TextInput::make('smlouvy_doba')->label('Jak dlouho údaje ze smluv uchováváte')->columnSpanFull()
                            ->visible(fn (Get $get) => (bool) $get('smlouvy')),
                    ]),
                Section::make('Další příjemci a doplňky')
                    ->description('Hosting, e-mail, IT a měření (podle SEO a měření) jsou v textu vždy. Sem patří, co je navíc.')
                    ->schema([
                        Textarea::make('dalsi_prijemci')->label('Další příjemci – každý na řádek')->rows(3)
                            ->placeholder("platební brána Comgate, a.s.\nrezervační systém …"),
                        RichEditor::make('doplnek')->label('Vlastní doplněk textu')
                            ->helperText('Další zpracování, které web dělá (např. rezervace, věrnostní program). Vloží se za ostatní účely.'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('zobrazit')
                ->label('Zobrazit na webu')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => route('ochrana-udaju'), shouldOpenInNewTab: true),
        ];
    }

    public function uloz(): void
    {
        OchranaUdaju::uloz($this->form->getState());

        Notification::make()->title('Uloženo')->success()->send();
    }
}
