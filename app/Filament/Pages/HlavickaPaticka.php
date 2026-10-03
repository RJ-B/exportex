<?php

namespace App\Filament\Pages;

use App\Filament\Support\CastObsahuWebu;
use App\Support\NastaveniWebu;
use App\Support\SekceWebu;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Toggle;
use App\Support\ObsahWebu;
use App\Support\ZakladniUdaje;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Obsah webu → Hlavička a patička: co je na každé stránce nahoře a dole –
 * název, kontakty, provozovatel (Základní údaje) a texty patičky.
 *
 * Exportex: logo je pevně ve vzhledu webu a sociální sítě web nemá (WhatsApp
 * a Telegram vedou na telefon z Kontaktů) – pole šablony pro ně tu proto nejsou.
 * Web je dvojjazyčný: anglické podoby sídla, rejstříku a textů patičky ukládá
 * App\Support\ObsahWebu (klíč obsah.paticka).
 */
class HlavickaPaticka extends Page
{
    use CastObsahuWebu;

    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    protected static ?string $navigationLabel = 'Hlavička a patička';

    protected static ?string $title = 'Hlavička a patička';

    protected static ?string $slug = 'hlavicka-a-paticka';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(ZakladniUdaje::nacti() + NastaveniWebu::nacti()
            + ['paticka' => ObsahWebu::sekce('paticka')]
            + ['sekce' => collect(SekceWebu::stranky())
                ->map(fn ($trida, $klic) => ['klic' => $klic, 'nazev' => $trida::nazevSekce(), 'zapnuto' => SekceWebu::zapnuta($klic)])
                ->values()->all()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Sekce na webu')
                    ->description('Pořadí (přetažením) platí pro menu webu i pro Obsah webu v administraci. Vypnutá sekce na webu není – zmizí z menu a její stránky vrátí 404. Obsah zůstane a přihlášení ji vidí dál.')
                    ->visible(fn () => SekceWebu::stranky() !== [])
                    ->schema([
                        // Tabulka: řádek na sekci, nic se nerozbaluje. Skryté pole by
                        // v tabulce nepřežilo – klíč se při uložení dohledá podle názvu.
                        Repeater::make('sekce')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Sekce'),
                                TableColumn::make('Na webu')->width('7rem'),
                            ])
                            ->schema([
                                TextInput::make('nazev')->hiddenLabel()->disabled()->dehydrated(),
                                // Jádro webu (rozvrh, rezervace) vypnout nejde – jen přesunout.
                                Toggle::make('zapnuto')->hiddenLabel()
                                    ->disabled(fn ($get) => ($trida = self::tridaPodleNazvu($get('nazev'))) && ! $trida::vypnoutJde()),
                            ])
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->addable(false)
                            ->deletable(false),
                    ]),
                Section::make('Hlavička')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nazev')->label('Název')->required()->maxLength(120)
                            ->helperText('Na webu, v záložce prohlížeče, v e-mailech i v administraci.'),
                        TextInput::make('tagline')->label('Podtitulek')->maxLength(160)
                            ->helperText('Za názvem v záložce prohlížeče, ve vyhledávačích a v náhledu odkazu.'),
                    ]),
                Section::make('Kontakty')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')->label('Kontaktní e-mail')->email(),
                        TextInput::make('telefon')->label('Telefon')->tel(),
                        TextInput::make('provozovna')->label('Adresa provozovny')->columnSpanFull()
                            ->helperText('Kde vás zákazníci najdou, když se liší od sídla. Exportex ji na webu nemá – v patičce je sídlo.'),
                    ]),
                Section::make('Provozovatel')
                    ->description('Do patičky a na doklady.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('firma')->label('Firma / jméno'),
                        TextInput::make('ico')->label('IČO')->regex('/^\d{8}$/')->validationMessages(['regex' => 'IČO je osm číslic.']),
                        TextInput::make('dic')->label('DIČ')->helperText('Prázdné, když provozovatel není plátce DPH.'),
                        Textarea::make('adresa')->label('Sídlo')->rows(2),
                        TextInput::make('rejstrik')->label('Zápis v rejstříku')->columnSpanFull()
                            ->helperText('U firmy povinný údaj (§ 435 občanského zákoníku) – soud, oddíl a vložka.'),
                        TextInput::make('paticka.adresa_en')->label('Sídlo – anglicky')->maxLength(160),
                        TextInput::make('paticka.rejstrik_en')->label('Zápis v rejstříku – anglicky')->maxLength(160),
                    ]),
                Section::make('Patička')
                    ->description('Text pod logem a pruh dole za „© rok firma —“.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('paticka.text')->label('Text pod logem')->rows(3)->maxLength(400),
                        Textarea::make('paticka.text_en')->label('Text pod logem – anglicky')->rows(3)->maxLength(400),
                        TextInput::make('paticka.pruh')->label('Text v pruhu dole')->maxLength(120),
                        TextInput::make('paticka.pruh_en')->label('Text v pruhu dole – anglicky')->maxLength(120),
                    ]),
            ]);
    }

    /** @return class-string|null */
    private static function tridaPodleNazvu(?string $nazev): ?string
    {
        return collect(SekceWebu::stranky())->first(fn ($trida) => $trida::nazevSekce() === $nazev);
    }

    public function uloz(): void
    {
        $data = $this->form->getState();

        ZakladniUdaje::uloz($data);
        NastaveniWebu::uloz($data);
        ObsahWebu::uloz('paticka', (array) ($data['paticka'] ?? []));

        $platne = array_keys(SekceWebu::stranky());
        $podleNazvu = collect(SekceWebu::stranky())->mapWithKeys(fn ($trida, $klic) => [$trida::nazevSekce() => $klic]);
        $poradi = [];

        foreach ((array) ($data['sekce'] ?? []) as $polozka) {
            $polozka['klic'] ??= $podleNazvu[$polozka['nazev'] ?? ''] ?? null;

            if (in_array($polozka['klic'] ?? null, $platne, true)) {
                SekceWebu::nastav($polozka['klic'], (bool) ($polozka['zapnuto'] ?? true));
                $poradi[] = $polozka['klic'];
            }
        }

        SekceWebu::nastavPoradi($poradi);

        Notification::make()->title('Uloženo')->success()->send();
    }
}
