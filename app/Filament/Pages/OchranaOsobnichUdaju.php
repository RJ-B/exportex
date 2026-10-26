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
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
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

    public static function getNavigationLabel(): string
    {
        return __('pravni.ochrana_osobnich_udaju.ochrana_osobnich_udaju');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pravni.ochrana_osobnich_udaju.ochrana_osobnich_udaju');
    }

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
                Section::make(__('pravni.ochrana_osobnich_udaju.co_jeste_chybi'))
                    ->visible(fn () => OchranaUdaju::chybejici() !== [])
                    ->schema([
                        Text::make(fn () => new HtmlString(
                            __('pravni.ochrana_osobnich_udaju.text_ochrany_osobnich_udaju_neni_uplny', ['chybejici' => e(implode(', ', OchranaUdaju::chybejici()))])
                        ))->color('warning'),
                    ]),
                Section::make(__('pravni.ochrana_osobnich_udaju.platnost'))
                    ->description(__('pravni.ochrana_osobnich_udaju.spravce_firma_sidlo_ico_kontakty_se'))
                    ->columns(2)
                    ->schema([
                        DatePicker::make('ucinnost_od')->label(__('pravni.ochrana_osobnich_udaju.ucinne_od'))->native(false)->displayFormat(__('formaty.datum')),
                        TextInput::make('poverenec')->label(__('pravni.ochrana_osobnich_udaju.poverenec_pro_ochranu_osobnich_udaju'))
                            ->placeholder(__('pravni.ochrana_osobnich_udaju.nejmenovali_jsme'))
                            ->helperText(__('pravni.ochrana_osobnich_udaju.jen_kdyz_ho_firma_ma_povinne')),
                    ]),
                Section::make(__('pravni.ochrana_osobnich_udaju.kontaktni_formular'))
                    ->description(fn () => SekceWebu::zapnuta('formular') ? __('pravni.ochrana_osobnich_udaju.na_webu_je_do_textu_se') : __('pravni.ochrana_osobnich_udaju.formular_je_vypnuty_tahle_cast_se'))
                    ->columns(2)
                    ->schema([
                        Textarea::make('formular_udaje')->label(__('pravni.ochrana_osobnich_udaju.jake_udaje_formular_sbira'))->rows(2)->columnSpanFull()
                            ->helperText(__('pravni.ochrana_osobnich_udaju.ip_adresa_a_cas_odeslani_se')),
                        TextInput::make('formular_doba')->label(__('pravni.ochrana_osobnich_udaju.jak_dlouho_zpravy_uchovavate')),
                    ]),
                Section::make(__('pravni.ochrana_osobnich_udaju.smlouvy_a_zakaznici'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('smlouvy')->label(__('pravni.ochrana_osobnich_udaju.pres_web_nebo_po_nem_uzavirame'))->live()->columnSpanFull(),
                        TextInput::make('smlouvy_doba')->label(__('pravni.ochrana_osobnich_udaju.jak_dlouho_udaje_ze_smluv_uchovavate'))->columnSpanFull()
                            ->visible(fn (Get $get) => (bool) $get('smlouvy')),
                    ]),
                Section::make(__('pravni.ochrana_osobnich_udaju.dalsi_prijemci_a_doplnky'))
                    ->description(__('pravni.ochrana_osobnich_udaju.hosting_e_mail_it_a_mereni'))
                    ->schema([
                        Textarea::make('dalsi_prijemci')->label(__('pravni.ochrana_osobnich_udaju.dalsi_prijemci_kazdy_na_radek'))->rows(3)
                            ->placeholder(__('pravni.ochrana_osobnich_udaju.platebni_brana_comgate_a_s_rezervacni')),
                        RichEditor::make('doplnek')->label(__('pravni.ochrana_osobnich_udaju.vlastni_doplnek_textu'))
                            ->helperText(__('pravni.ochrana_osobnich_udaju.dalsi_zpracovani_ktere_web_dela_napr')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('zobrazit')
                ->label(__('pravni.ochrana_osobnich_udaju.zobrazit_na_webu'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => route('ochrana-udaju'), shouldOpenInNewTab: true),
        ];
    }

    public function uloz(): void
    {
        OchranaUdaju::uloz($this->form->getState());

        Notification::make()->title(__('pravni.ochrana_osobnich_udaju.ulozeno'))->success()->send();
    }
}
