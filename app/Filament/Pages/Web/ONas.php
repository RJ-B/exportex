<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → O nás: dva odstavce a fotka s popiskem. */
class ONas extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getNavigationLabel(): string
    {
        return __('administrace.o_nas.o_nas');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('administrace.o_nas.o_nas');
    }

    protected static ?string $slug = 'web/o-nas';

    protected static ?int $navigationSort = 5;

    protected static function obsah(): string
    {
        return 'onas';
    }

    public static function klicSekce(): ?string
    {
        return 'onas';
    }

    public static function odkazSekce(): ?string
    {
        return '/#onas';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(),
                Section::make(__('administrace.o_nas.text'))
                    ->columns(2)
                    ->schema([
                        ...self::odstavec('text', __('administrace.o_nas.prvni_odstavec'), 800),
                        ...self::odstavec('text_druhy', __('administrace.o_nas.druhy_odstavec'), 800, povinne: false),
                    ]),
                Section::make(__('administrace.o_nas.fotka'))
                    ->columns(2)
                    ->schema([
                        self::fotka(FileUpload::make('foto')->label(__('administrace.o_nas.fotka'))->required(), 'o-nas')
                            ->helperText(__('administrace.o_nas.na_sirku_3_2_fotka_se')),
                        TextInput::make('foto_popis')->label(__('administrace.o_nas.popis_fotky_pro_nevidome_a_vyhledavace'))->maxLength(160),
                        ...self::text('popisek', 'Popisek na fotce', 60),
                        TextInput::make('popisek_misto')->label(__('administrace.o_nas.misto_a_rok_na_fotce'))->maxLength(40)
                            ->helperText(__('administrace.o_nas.stejne_v_obou_jazycich_velkymi_pismeny')),
                    ]),
            ]);
    }
}
