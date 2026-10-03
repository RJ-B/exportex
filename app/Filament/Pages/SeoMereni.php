<?php

namespace App\Filament\Pages;

use App\Filament\Support\CastObsahuWebu;
use App\Support\NastaveniWebu;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → SEO a měření: výchozí popis pro vyhledávače, měření návštěvnosti a cookie lišta. */
class SeoMereni extends Page
{
    use CastObsahuWebu;

    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'SEO a měření';

    protected static ?string $title = 'SEO a měření';

    protected static ?string $slug = 'seo-a-mereni';

    /** Na konec Obsahu webu – sekce stránek webu (Úvod, Služby…) přijdou mezi. */
    protected static ?int $navigationSort = 950;

    public ?array $data = [];

    /** Vypnutá = na webu se nespustí žádné měření ani cookie lišta (SEO popis platí dál). */
    public static function klicSekce(): ?string
    {
        return 'mereni';
    }

    public static function nazevSekce(): string
    {
        return 'Měření návštěvnosti';
    }

    public function mount(): void
    {
        $hodnoty = NastaveniWebu::nacti();
        $hodnoty['cookie_lista'] = ($hodnoty['cookie_lista'] ?? '1') === '1';

        $this->form->fill($hodnoty);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Vyhledávače')
                    ->schema([
                        Textarea::make('meta_popis')->label('Výchozí popis webu')->rows(2)->maxLength(160)
                            ->helperText('Co ukáže Google pod odkazem, když stránka nemá vlastní popis. Do 160 znaků.'),
                    ]),
                Section::make('Měření návštěvnosti')
                    ->description('Kódy se spustí až po souhlasu návštěvníka v cookie liště. Lišta se ukáže, jen když je vyplněný aspoň jeden nástroj – bez měření souhlas není potřeba. Zásady cookies a ochrana osobních údajů se podle toho upraví samy.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('cookie_lista')->label('Zobrazovat cookie lištu')->columnSpanFull()
                            ->helperText('Vypínat jen tehdy, když na webu žádné měření neběží.'),
                        TextInput::make('ga4_id')->label('Google Analytics 4 – ID měření')->placeholder('G-XXXXXXXXXX')
                            ->regex('/^G-[A-Z0-9]{4,20}$/')->validationMessages(['regex' => 'ID měření má tvar G-XXXXXXXXXX.'])
                            ->helperText('Analytické – po souhlasu se statistikou.'),
                        TextInput::make('seznam_id')->label('Seznam – ID měření')
                            ->regex('/^[0-9]{1,12}$/')->validationMessages(['regex' => 'ID měření Seznamu je číslo.'])
                            ->helperText('Analytické – po souhlasu se statistikou.'),
                        TextInput::make('google_ads_id')->label('Google Ads – ID konverzí')->placeholder('AW-XXXXXXXXX')
                            ->regex('/^AW-[0-9]{6,15}$/')->validationMessages(['regex' => 'ID konverzí má tvar AW-123456789.'])
                            ->helperText('Marketingové – po souhlasu s reklamou.'),
                        TextInput::make('sklik_id')->label('Sklik – retargeting ID')->integer()->minValue(1)
                            ->helperText('Marketingové – po souhlasu s reklamou.'),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        NastaveniWebu::uloz($this->form->getState());

        Notification::make()->title('Uloženo')->success()->send();
    }
}
