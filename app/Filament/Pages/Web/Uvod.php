<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → Úvod: velká fotka s nadpisem a čtyřmi dlaždicemi nahoře na webu. Jádro webu – vypnout nejde. */
class Uvod extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public static function getNavigationLabel(): string
    {
        return __('administrace.uvod.uvod');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('administrace.uvod.uvod');
    }

    protected static ?string $slug = 'web/uvod';

    /** Hned za Hlavičkou a patičkou, před sekcemi (ty mají 100+). */
    protected static ?int $navigationSort = 50;

    protected static function obsah(): string
    {
        return 'uvod';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('administrace.uvod.nadpis'))
                    ->description(__('administrace.uvod.druha_cast_nadpisu_je_zvyraznena_firemni'))
                    ->columns(2)
                    ->schema([
                        ...self::text('stitek', __('administrace.uvod.stitek_nad_nadpisem'), 80),
                        TextInput::make('trasa')->label(__('administrace.uvod.trasa_vpravo_nad_nadpisem'))->maxLength(40)->columnSpanFull()
                            ->helperText(__('administrace.uvod.stejna_v_obou_jazycich_pise_se')),
                        ...self::text('nadpis', 'Nadpis', 120),
                        ...self::text('nadpis_zvyrazneny', __('administrace.uvod.zvyraznene_pokracovani'), 120),
                        ...self::odstavec('text', 'Text pod nadpisem', 400),
                    ]),
                Section::make(__('administrace.uvod.tlacitka'))
                    ->columns(2)
                    ->schema([
                        ...self::text('tlacitko', __('administrace.uvod.hlavni_tlacitko_na_poptavku'), 40),
                        ...self::text('tlacitko_druhe', __('administrace.uvod.druhe_tlacitko_na_sortiment'), 40),
                    ]),
                Section::make(__('administrace.uvod.dlazdice'))
                    ->description(__('administrace.uvod.ctyri_cisla_pod_nadpisem_znacka_se'))
                    ->schema([
                        Repeater::make('cisla')->hiddenLabel()
                            ->table([
                                Repeater\TableColumn::make(__('administrace.uvod.popis')),
                                Repeater\TableColumn::make(__('administrace.uvod.hodnota')),
                                Repeater\TableColumn::make(__('administrace.uvod.pod_hodnotou')),
                                Repeater\TableColumn::make(__('administrace.uvod.popis_en')),
                                Repeater\TableColumn::make(__('administrace.uvod.hodnota_en')),
                                Repeater\TableColumn::make(__('administrace.uvod.pod_hodnotou_en')),
                            ])
                            ->schema([
                                TextInput::make('nazev')->hiddenLabel()->required()->maxLength(40),
                                TextInput::make('hodnota')->hiddenLabel()->required()->maxLength(40),
                                TextInput::make('podpis')->hiddenLabel()->maxLength(40),
                                TextInput::make('nazev_en')->hiddenLabel()->required()->maxLength(40),
                                TextInput::make('hodnota_en')->hiddenLabel()->required()->maxLength(40),
                                TextInput::make('podpis_en')->hiddenLabel()->maxLength(40),
                            ])
                            ->reorderableWithDragAndDrop()
                            ->maxItems(4)
                            ->addActionLabel(__('administrace.uvod.pridat_dlazdici')),
                    ]),
                Section::make(__('administrace.uvod.fotka'))
                    ->columns(2)
                    ->schema([
                        self::fotka(FileUpload::make('foto')->label(__('administrace.uvod.fotka_v_pozadi'))->required(), 'uvod')
                            ->helperText(__('administrace.uvod.na_sirku_nejlepe_16_9_fotka')),
                        TextInput::make('foto_popis')->label(__('administrace.uvod.popis_fotky_pro_nevidome_a_vyhledavace'))->maxLength(160),
                    ]),
            ]);
    }
}
