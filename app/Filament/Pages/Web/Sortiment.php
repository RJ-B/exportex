<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Obsah webu → Sortiment: filtry (kategorie) a karty sortimentu s detailem –
 * body, parametry v dlaždicích a obchodní podmínky.
 */
class Sortiment extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getNavigationLabel(): string
    {
        return __('administrace.sortiment.sortiment');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('administrace.sortiment.sortiment');
    }

    protected static ?string $slug = 'web/sortiment';

    protected static ?int $navigationSort = 1;

    protected static function obsah(): string
    {
        return 'sortiment';
    }

    public static function klicSekce(): ?string
    {
        return 'sortiment';
    }

    public static function odkazSekce(): ?string
    {
        return '/#sortiment';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(popis: true),
                Section::make(__('administrace.sortiment.kategorie'))
                    ->description(__('administrace.sortiment.filtry_nad_sortimentem_prvni_filtr_vse'))
                    ->schema([
                        Repeater::make('kategorie')->hiddenLabel()
                            ->table([
                                Repeater\TableColumn::make(__('administrace.sortiment.kategorie')),
                                Repeater\TableColumn::make(__('administrace.sortiment.kategorie_anglicky')),
                            ])
                            ->schema([
                                TextInput::make('nazev')->hiddenLabel()->required()->maxLength(60)->distinct(),
                                TextInput::make('nazev_en')->hiddenLabel()->required()->maxLength(60),
                            ])
                            ->reorderableWithDragAndDrop()
                            ->addActionLabel(__('administrace.sortiment.pridat_kategorii')),
                    ]),
                Section::make(__('administrace.sortiment.karty_sortimentu'))
                    ->description(__('administrace.sortiment.karta_ukazuje_fotku_stitek_nazev_a'))
                    ->schema([
                        Repeater::make('produkty')->hiddenLabel()
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn (array $state) => $state['nazev'] ?? null)
                            ->addActionLabel(__('administrace.sortiment.pridat_kartu'))
                            ->schema([
                                ...self::text('nazev', __('administrace.sortiment.nazev'), 80),
                                ...self::text('stitek', __('administrace.sortiment.stitek_na_fotce'), 30),
                                ...self::odstavec('popis', __('administrace.sortiment.kratky_popis_na_karte'), 300),
                                Select::make('kategorie')->label(__('administrace.sortiment.kategorie_filtr'))
                                    ->options(fn ($get) => collect((array) $get('../../kategorie'))
                                        ->pluck('nazev', 'nazev')->filter()->all())
                                    ->native(false)
                                    ->helperText(__('administrace.sortiment.bez_kategorie_je_karta_jen_pod')),
                                self::fotka(FileUpload::make('foto')->label(__('administrace.sortiment.fotka'))->required(), 'sortiment')
                                    ->helperText(__('administrace.sortiment.na_sirku_3_2_karta_ji')),
                                Repeater::make('body')->label(__('administrace.sortiment.body_v_detailu'))
                                    ->table([
                                        Repeater\TableColumn::make(__('administrace.sortiment.cesky')),
                                        Repeater\TableColumn::make(__('administrace.sortiment.anglicky')),
                                    ])
                                    ->schema([
                                        TextInput::make('cs')->hiddenLabel()->required()->maxLength(200),
                                        TextInput::make('en')->hiddenLabel()->required()->maxLength(200),
                                    ])
                                    ->reorderableWithDragAndDrop()
                                    ->addActionLabel(__('administrace.sortiment.pridat_bod'))
                                    ->columnSpanFull(),
                                Repeater::make('parametry')->label(__('administrace.sortiment.parametry_v_dlazdicich'))
                                    ->table([
                                        Repeater\TableColumn::make(__('administrace.sortiment.parametr')),
                                        Repeater\TableColumn::make(__('administrace.sortiment.hodnota')),
                                        Repeater\TableColumn::make(__('administrace.sortiment.parametr_en')),
                                        Repeater\TableColumn::make(__('administrace.sortiment.hodnota_en')),
                                    ])
                                    ->schema([
                                        TextInput::make('nazev')->hiddenLabel()->required()->maxLength(30),
                                        TextInput::make('hodnota')->hiddenLabel()->required()->maxLength(40),
                                        TextInput::make('nazev_en')->hiddenLabel()->required()->maxLength(30),
                                        TextInput::make('hodnota_en')->hiddenLabel()->required()->maxLength(40),
                                    ])
                                    ->reorderableWithDragAndDrop()
                                    ->addActionLabel(__('administrace.sortiment.pridat_parametr'))
                                    ->columnSpanFull(),
                                ...self::text('podminky', __('administrace.sortiment.podminky_moq_vzorek_vyroba'), 200, povinne: false),
                            ]),
                    ]),
                Section::make(__('administrace.sortiment.poznamka_pod_podminkami'))
                    ->columns(2)
                    ->schema([
                        ...self::text('podminky_poznamka', __('administrace.sortiment.poznamka'), 120),
                    ]),
            ]);
    }
}
