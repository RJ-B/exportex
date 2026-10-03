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

    protected static ?string $navigationLabel = 'Úvod';

    protected static ?string $title = 'Úvod';

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
                Section::make('Nadpis')
                    ->description('Druhá část nadpisu je zvýrazněná firemní červenou.')
                    ->columns(2)
                    ->schema([
                        ...self::text('stitek', 'Štítek nad nadpisem', 80),
                        TextInput::make('trasa')->label('Trasa vpravo nad nadpisem')->maxLength(40)->columnSpanFull()
                            ->helperText('Stejná v obou jazycích, píše se velkými písmeny.'),
                        ...self::text('nadpis', 'Nadpis', 120),
                        ...self::text('nadpis_zvyrazneny', 'Zvýrazněné pokračování', 120),
                        ...self::odstavec('text', 'Text pod nadpisem', 400),
                    ]),
                Section::make('Tlačítka')
                    ->columns(2)
                    ->schema([
                        ...self::text('tlacitko', 'Hlavní tlačítko (na poptávku)', 40),
                        ...self::text('tlacitko_druhe', 'Druhé tlačítko (na sortiment)', 40),
                    ]),
                Section::make('Dlaždice')
                    ->description('Čtyři čísla pod nadpisem. Značka ® se na webu zobrazí jako horní index.')
                    ->schema([
                        Repeater::make('cisla')->hiddenLabel()
                            ->table([
                                Repeater\TableColumn::make('Popis'),
                                Repeater\TableColumn::make('Hodnota'),
                                Repeater\TableColumn::make('Pod hodnotou'),
                                Repeater\TableColumn::make('Popis – EN'),
                                Repeater\TableColumn::make('Hodnota – EN'),
                                Repeater\TableColumn::make('Pod hodnotou – EN'),
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
                            ->addActionLabel('Přidat dlaždici'),
                    ]),
                Section::make('Fotka')
                    ->columns(2)
                    ->schema([
                        self::fotka(FileUpload::make('foto')->label('Fotka v pozadí')->required(), 'uvod')
                            ->helperText('Na šířku, nejlépe 16 : 9. Fotka se sama zmenší a uloží jako WebP.'),
                        TextInput::make('foto_popis')->label('Popis fotky (pro nevidomé a vyhledávače)')->maxLength(160),
                    ]),
            ]);
    }
}
