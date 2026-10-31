<?php

namespace App\Filament\Pages;

use App\Filament\Support\CastObsahuWebu;
use App\Support\Fotky;
use App\Support\LogoWebu;
use App\Support\NastaveniWebu;
use App\Support\ObsahWebu;
use App\Support\SekceWebu;
use App\Support\ZakladniUdaje;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Obsah webu → Hlavička a patička: co je na každé stránce nahoře a dole –
 * název, kontakty, provozovatel (Základní údaje) a texty patičky.
 *
 * Exportex: logo je pevně ve vzhledu webu (config sablona.vzhled – administrace ho bere
 * sama, sekce Logo a ikona se neukazuje) a sociální sítě web nemá (WhatsApp
 * a Telegram vedou na telefon z Kontaktů) – pole šablony pro ně tu proto nejsou.
 * Web je dvojjazyčný: anglické podoby sídla, rejstříku a textů patičky ukládá
 * App\Support\ObsahWebu (klíč obsah.paticka).
 */
class HlavickaPaticka extends Page
{
    use CastObsahuWebu;

    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWindow;

    public static function getNavigationLabel(): string
    {
        return __('administrace.hlavicka_paticka.hlavicka_a_paticka');
    }

    public function getTitle(): string|Htmlable
    {
        return __('administrace.hlavicka_paticka.hlavicka_a_paticka');
    }

    protected static ?string $slug = 'hlavicka-a-paticka';

    /** Kotva sekce s logem – sem vede krok průvodce „Ikona a logo“. */
    public const KOTVA_LOGA = 'logo';

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
                Section::make(__('administrace.hlavicka_paticka.sekce_na_webu'))
                    ->description(__('administrace.hlavicka_paticka.poradi_pretazenim_plati_pro_menu_webu'))
                    ->visible(fn () => SekceWebu::stranky() !== [])
                    ->schema([
                        // Tabulka: řádek na sekci, nic se nerozbaluje. Skryté pole by
                        // v tabulce nepřežilo – klíč se při uložení dohledá podle názvu.
                        Repeater::make('sekce')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('administrace.hlavicka_paticka.sekce')),
                                TableColumn::make(__('administrace.hlavicka_paticka.na_webu'))->width('7rem'),
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
                Section::make(__('administrace.hlavicka_paticka.hlavicka'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('nazev')->label(__('administrace.hlavicka_paticka.nazev'))->required()->maxLength(120)
                            ->helperText(__('administrace.hlavicka_paticka.na_webu_v_zalozce_prohlizece_v')),
                        TextInput::make('tagline')->label(__('administrace.hlavicka_paticka.podtitulek'))->maxLength(160)
                            ->helperText(__('administrace.hlavicka_paticka.za_nazvem_v_zalozce_prohlizece_ve')),
                    ]),
                // Kotva pro krok průvodce „Ikona a logo“ (#logo). Exportex má logo pevně ve vzhledu
                // webu (config sablona.vzhled) – sekce se neukazuje, administrace ho bere sama.
                Section::make(__('administrace.hlavicka_paticka.logo_a_ikona'))
                    ->id(self::KOTVA_LOGA)->extraAttributes(['style' => 'scroll-margin-top: 5rem'])
                    ->description(__('administrace.hlavicka_paticka.logo_plati_pro_web_i_administraci'))
                    ->visible(fn () => ! LogoWebu::pevne())
                    ->columns(2)
                    ->schema([
                        Fotky::pole(FileUpload::make('logo'))->label(__('administrace.hlavicka_paticka.logo'))
                            ->image()->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                            ->disk('public')->directory('web')->maxSize(4096)
                            ->helperText(__('administrace.hlavicka_paticka.png_nebo_webp_s_pruhlednosti_svg')),
                        Fotky::pole(FileUpload::make('ikona'))->label(__('administrace.hlavicka_paticka.ikona_v_zalozce'))
                            ->image()->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                            ->disk('public')->directory('web')->maxSize(4096)
                            ->helperText(__('administrace.hlavicka_paticka.nepovinne_jen_kdyz_je_logo_siroke')),
                    ]),
                Section::make(__('administrace.hlavicka_paticka.kontakty'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')->label(__('administrace.hlavicka_paticka.kontaktni_e_mail'))->email(),
                        TextInput::make('telefon')->label(__('administrace.hlavicka_paticka.telefon'))->tel(),
                        TextInput::make('provozovna')->label(__('administrace.hlavicka_paticka.adresa_provozovny'))->columnSpanFull()
                            ->helperText(__('administrace.hlavicka_paticka.kde_vas_zakaznici_najdou_kdyz_se_2')),
                    ]),
                Section::make(__('administrace.hlavicka_paticka.provozovatel'))
                    ->description(__('administrace.hlavicka_paticka.do_paticky_a_na_doklady'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('firma')->label(__('administrace.hlavicka_paticka.firma_jmeno')),
                        TextInput::make('ico')->label(__('administrace.hlavicka_paticka.ico'))->regex('/^\d{8}$/')->validationMessages(['regex' => __('administrace.hlavicka_paticka.ico_je_osm_cislic')]),
                        TextInput::make('dic')->label(__('administrace.hlavicka_paticka.dic'))->helperText(__('administrace.hlavicka_paticka.prazdne_kdyz_provozovatel_neni_platce')),
                        Textarea::make('adresa')->label(__('administrace.hlavicka_paticka.sidlo'))->rows(2),
                        TextInput::make('rejstrik')->label(__('administrace.hlavicka_paticka.zapis_v_rejstriku'))->columnSpanFull()
                            ->helperText(__('administrace.hlavicka_paticka.u_firmy_povinny_udaj_435_obcanskeho')),
                        TextInput::make('paticka.adresa_en')->label(__('administrace.hlavicka_paticka.sidlo_anglicky'))->maxLength(160),
                        TextInput::make('paticka.rejstrik_en')->label(__('administrace.hlavicka_paticka.zapis_v_rejstriku_anglicky'))->maxLength(160),
                    ]),
                Section::make(__('administrace.hlavicka_paticka.paticka'))
                    ->description('Text pod logem a pruh dole za „© rok firma —“.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('paticka.text')->label(__('administrace.hlavicka_paticka.text_pod_logem'))->rows(3)->maxLength(400),
                        Textarea::make('paticka.text_en')->label(__('administrace.hlavicka_paticka.text_pod_logem_anglicky'))->rows(3)->maxLength(400),
                        TextInput::make('paticka.pruh')->label(__('administrace.hlavicka_paticka.text_v_pruhu_dole'))->maxLength(120),
                        TextInput::make('paticka.pruh_en')->label(__('administrace.hlavicka_paticka.text_v_pruhu_dole_anglicky'))->maxLength(120),
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

        Notification::make()->title(__('administrace.hlavicka_paticka.ulozeno'))->success()->send();
    }
}
