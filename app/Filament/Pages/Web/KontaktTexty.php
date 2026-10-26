<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Obsah webu → Kontakt – texty: nadpis sekce Kontakt, kontaktní osoba, rychlý
 * kontakt a texty u formuláře. Sekci samotnou (zapnutí, kam chodí poptávky,
 * schránka) má šablona v Kontakt a formulář (App\Filament\Pages\Posta) – tahle
 * stránka je její část, v menu hned za ní. E-mail a telefon jsou v Hlavičce
 * a patičce.
 */
class KontaktTexty extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function getNavigationLabel(): string
    {
        return __('administrace.kontakt_texty.kontakt_texty');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('administrace.kontakt_texty.kontakt_texty');
    }

    protected static ?string $slug = 'web/kontakt-texty';

    protected static ?int $navigationSort = 801;

    protected static function obsah(): string
    {
        return 'kontakt';
    }

    public static function sekceWebu(): ?string
    {
        return 'formular';
    }

    public static function poradiVSekci(): int
    {
        return 1;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(),
                Section::make(__('administrace.kontakt_texty.kontakt_vedle_formulare'))
                    ->description(__('administrace.kontakt_texty.e_mail_a_telefon_se_berou'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('osoba')->label(__('administrace.kontakt_texty.kontaktni_osoba'))->maxLength(80)->columnSpanFull()
                            ->helperText(__('administrace.kontakt_texty.i_v_paticce_a_ve_strukturovanych')),
                        Toggle::make('whatsapp')->label(__('administrace.kontakt_texty.tlacitko_whatsapp')),
                        Toggle::make('telegram')->label(__('administrace.kontakt_texty.tlacitko_telegram')),
                    ]),
                Section::make(__('administrace.kontakt_texty.formular'))
                    ->columns(2)
                    ->schema([
                        ...self::text('poznamka', __('administrace.kontakt_texty.poznamka_pod_tlacitkem'), 80),
                        ...self::text('odeslano', __('administrace.kontakt_texty.text_po_odeslani'), 200),
                    ]),
            ]);
    }
}
