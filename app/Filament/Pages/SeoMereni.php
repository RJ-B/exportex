<?php

namespace App\Filament\Pages;

use App\Filament\Support\CastObsahuWebu;
use App\Support\NastaveniWebu;
use App\Support\Seo\NastaveniSeo;
use App\Support\Seo\SeoPole;
use App\Support\ZakladniUdaje;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Obsah webu → SEO a měření: výchozí popis a obrázek pro sdílení, kdo web
 * provozuje (strukturovaná data), vlastní titulek a popis stránek, měření
 * návštěvnosti a cookie lišta.
 */
class SeoMereni extends Page
{
    use CastObsahuWebu;

    protected string $view = 'filament.pages.formular';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationLabel(): string
    {
        return __('seo.seo_mereni.seo_a_mereni');
    }

    public function getTitle(): string|Htmlable
    {
        return __('seo.seo_mereni.seo_a_mereni');
    }

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
        return __('seo.seo_mereni.mereni_navstevnosti');
    }

    public function mount(): void
    {
        $hodnoty = NastaveniWebu::nacti();
        $hodnoty['cookie_lista'] = ($hodnoty['cookie_lista'] ?? '1') === '1';

        $seo = NastaveniSeo::nacti();
        $hodnoty['seo_obrazek'] = $seo['obrazek'];
        $hodnoty['subjekt'] = $seo['subjekt'];
        $hodnoty['kategorie'] = $seo['kategorie'];
        $hodnoty['oteviraci_doba'] = $seo['oteviraci_doba'];
        $hodnoty['geo_sirka'] = $seo['geo_sirka'];
        $hodnoty['geo_delka'] = $seo['geo_delka'];
        $hodnoty['stranky'] = collect(NastaveniSeo::stranky())
            ->map(fn ($stranka, string $klic) => NastaveniSeo::stranka($klic))
            ->all();

        $this->form->fill($hodnoty);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('seo.seo_mereni.vyhledavace_a_sdileni'))
                    ->description(__('seo.seo_mereni.co_ukaze_google_pod_odkazem_a'))
                    ->columns(2)
                    ->schema([
                        Textarea::make('meta_popis')->label(__('seo.seo_mereni.vychozi_popis_webu'))->rows(3)->maxLength(160)
                            ->live(onBlur: true)
                            ->helperText(__('seo.seo_mereni.do_160_znaku_delsi_google_zkrati')),
                        SeoPole::obrazek('seo_obrazek')->label(__('seo.seo_mereni.vychozi_obrazek_pro_sdileni')),
                        Html::make(fn ($get) => SeoPole::nahled('/', null, $get('meta_popis'), $get('seo_obrazek'), uvod: true))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('seo.seo_mereni.kdo_web_provozuje'))
                    ->description(new HtmlString(__('seo.seo_mereni.pro_strukturovana_data_vizitka_firmy_ve', ['get_url' => e(HlavickaPaticka::getUrl())])))
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('subjekt')->label(__('seo.seo_mereni.typ'))->inline()->live()
                            ->options([
                                NastaveniSeo::SUBJEKT_ORGANIZACE => __('seo.seo_mereni.organizace'),
                                NastaveniSeo::SUBJEKT_MISTNI_FIRMA => __('seo.seo_mereni.mistni_firma'),
                            ])
                            ->helperText(__('seo.seo_mereni.mistni_firma_provozovna_kam_zakaznici')),
                        Select::make('kategorie')->label(__('seo.seo_mereni.druh_firmy'))->searchable()
                            ->options(NastaveniSeo::kategorie())
                            ->visible(fn ($get) => $get('subjekt') === NastaveniSeo::SUBJEKT_MISTNI_FIRMA),
                        Html::make(fn () => new HtmlString('<p style="color: var(--warning-600); font-size: .875rem;">'.__('seo.seo_mereni.mistni_firma_potrebuje_adresu_provozovny').'</p>'))
                            ->visible(fn ($get) => $get('subjekt') === NastaveniSeo::SUBJEKT_MISTNI_FIRMA
                                && blank(ZakladniUdaje::get('provozovna')) && blank(ZakladniUdaje::get('adresa')))
                            ->columnSpanFull(),
                        Repeater::make('oteviraci_doba')->label(__('seo.seo_mereni.oteviraci_doba'))
                            ->visible(fn ($get) => $get('subjekt') === NastaveniSeo::SUBJEKT_MISTNI_FIRMA)
                            ->table([
                                TableColumn::make(__('seo.seo_mereni.dny')),
                                TableColumn::make(__('seo.seo_mereni.od'))->width('8rem'),
                                TableColumn::make(__('seo.seo_mereni.do'))->width('8rem'),
                            ])
                            ->schema([
                                Select::make('dny')->hiddenLabel()->multiple()->options(NastaveniSeo::dny())->required(),
                                TextInput::make('od')->hiddenLabel()->placeholder('8:00')->required()
                                    ->regex('/^([01]?\d|2[0-3]):[0-5]\d$/')->validationMessages(['regex' => __('seo.seo_mereni.cas_ve_tvaru_8_00')]),
                                TextInput::make('do')->hiddenLabel()->placeholder('17:00')->required()
                                    ->regex('/^([01]?\d|2[0-4]):[0-5]\d$/')->validationMessages(['regex' => __('seo.seo_mereni.cas_ve_tvaru_17_00')]),
                            ])
                            ->addActionLabel(__('seo.seo_mereni.pridat_radek'))
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->helperText(__('seo.seo_mereni.radek_na_skupinu_dni_se_stejnymi')),
                        TextInput::make('geo_sirka')->label(__('seo.seo_mereni.zemepisna_sirka'))->placeholder('50.0874654')->numeric()->minValue(-90)->maxValue(90)
                            ->visible(fn ($get) => $get('subjekt') === NastaveniSeo::SUBJEKT_MISTNI_FIRMA)
                            ->helperText(__('seo.seo_mereni.nepovinne_z_mapy_cz_nebo_google')),
                        TextInput::make('geo_delka')->label(__('seo.seo_mereni.zemepisna_delka'))->placeholder('14.4212535')->numeric()->minValue(-180)->maxValue(180)
                            ->visible(fn ($get) => $get('subjekt') === NastaveniSeo::SUBJEKT_MISTNI_FIRMA),
                    ]),
                Section::make(__('seo.seo_mereni.stranky_webu'))
                    ->description(__('seo.seo_mereni.vlastni_titulek_popis_a_obrazek_pro'))
                    ->collapsible()
                    ->schema(collect(NastaveniSeo::stranky())
                        ->map(fn (array $stranka, string $klic) => Section::make($stranka['nazev'])
                            ->description($stranka['adresa'])
                            ->collapsible()->collapsed()
                            ->compact()
                            ->columns(2)
                            ->schema(SeoPole::pole('stranky.'.$klic, $stranka['adresa'], $stranka['nazev'], $klic === 'uvod')))
                        ->values()->all()),
                Section::make(__('seo.seo_mereni.mereni_navstevnosti'))
                    ->description(__('seo.seo_mereni.kody_se_spusti_az_po_souhlasu'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('cookie_lista')->label(__('seo.seo_mereni.zobrazovat_cookie_listu'))->columnSpanFull()
                            ->helperText(__('seo.seo_mereni.vypinat_jen_tehdy_kdyz_na_webu')),
                        TextInput::make('ga4_id')->label(__('seo.seo_mereni.google_analytics_4_id_mereni'))->placeholder(__('seo.seo_mereni.g_xxxxxxxxxx'))
                            ->regex('/^G-[A-Z0-9]{4,20}$/')->validationMessages(['regex' => __('seo.seo_mereni.id_mereni_ma_tvar_g_xxxxxxxxxx')])
                            ->helperText(__('seo.seo_mereni.analyticke_po_souhlasu_se_statistikou')),
                        TextInput::make('seznam_id')->label(__('seo.seo_mereni.seznam_id_mereni'))
                            ->regex('/^[0-9]{1,12}$/')->validationMessages(['regex' => __('seo.seo_mereni.id_mereni_seznamu_je_cislo')])
                            ->helperText(__('seo.seo_mereni.analyticke_po_souhlasu_se_statistikou')),
                        TextInput::make('google_ads_id')->label(__('seo.seo_mereni.google_ads_id_konverzi'))->placeholder(__('seo.seo_mereni.aw_xxxxxxxxx'))
                            ->regex('/^AW-[0-9]{6,15}$/')->validationMessages(['regex' => __('seo.seo_mereni.id_konverzi_ma_tvar_aw_123456789')])
                            ->helperText(__('seo.seo_mereni.marketingove_po_souhlasu_s_reklamou')),
                        TextInput::make('sklik_id')->label(__('seo.seo_mereni.sklik_retargeting_id'))->integer()->minValue(1)
                            ->helperText(__('seo.seo_mereni.marketingove_po_souhlasu_s_reklamou')),
                    ]),
            ]);
    }

    public function uloz(): void
    {
        $data = $this->form->getState();

        NastaveniWebu::uloz($data);
        // Skrytá pole (místní firma u organizace) ve stavu formuláře nejsou – uložené hodnoty zůstanou.
        $seo = ['obrazek' => $data['seo_obrazek'] ?? null];

        foreach (['subjekt', 'kategorie', 'oteviraci_doba', 'geo_sirka', 'geo_delka', 'stranky'] as $pole) {
            if (array_key_exists($pole, $data)) {
                $seo[$pole] = $data[$pole];
            }
        }

        NastaveniSeo::uloz($seo);

        Notification::make()->title(__('seo.seo_mereni.ulozeno'))->success()->send();
    }
}
