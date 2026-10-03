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

    protected static ?string $navigationLabel = 'Sortiment';

    protected static ?string $title = 'Sortiment';

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
                Section::make('Kategorie')
                    ->description('Filtry nad sortimentem. První filtr „Vše“ je na webu vždy. Kartu do kategorie zařadíš u karty níž – po přejmenování kategorie ji tam vyber znovu.')
                    ->schema([
                        Repeater::make('kategorie')->hiddenLabel()
                            ->table([
                                Repeater\TableColumn::make('Kategorie'),
                                Repeater\TableColumn::make('Kategorie – anglicky'),
                            ])
                            ->schema([
                                TextInput::make('nazev')->hiddenLabel()->required()->maxLength(60)->distinct(),
                                TextInput::make('nazev_en')->hiddenLabel()->required()->maxLength(60),
                            ])
                            ->reorderableWithDragAndDrop()
                            ->addActionLabel('Přidat kategorii'),
                    ]),
                Section::make('Karty sortimentu')
                    ->description('Karta ukazuje fotku, štítek, název a krátký popis; po kliknutí se otevře detail s body, parametry a podmínkami. Body a parametry se nemají opakovat – co je v dlaždici parametrů, do bodů nepatří.')
                    ->schema([
                        Repeater::make('produkty')->hiddenLabel()
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn (array $state) => $state['nazev'] ?? null)
                            ->addActionLabel('Přidat kartu')
                            ->schema([
                                ...self::text('nazev', 'Název', 80),
                                ...self::text('stitek', 'Štítek na fotce', 30),
                                ...self::odstavec('popis', 'Krátký popis na kartě', 300),
                                Select::make('kategorie')->label('Kategorie (filtr)')
                                    ->options(fn ($get) => collect((array) $get('../../kategorie'))
                                        ->pluck('nazev', 'nazev')->filter()->all())
                                    ->native(false)
                                    ->helperText('Bez kategorie je karta jen pod filtrem „Vše“.'),
                                self::fotka(FileUpload::make('foto')->label('Fotka')->required(), 'sortiment')
                                    ->helperText('Na šířku 3 : 2 – karta ji ořízne na 4 : 3, detail na 16 : 9.'),
                                Repeater::make('body')->label('Body v detailu')
                                    ->table([
                                        Repeater\TableColumn::make('Česky'),
                                        Repeater\TableColumn::make('Anglicky'),
                                    ])
                                    ->schema([
                                        TextInput::make('cs')->hiddenLabel()->required()->maxLength(200),
                                        TextInput::make('en')->hiddenLabel()->required()->maxLength(200),
                                    ])
                                    ->reorderableWithDragAndDrop()
                                    ->addActionLabel('Přidat bod')
                                    ->columnSpanFull(),
                                Repeater::make('parametry')->label('Parametry v dlaždicích')
                                    ->table([
                                        Repeater\TableColumn::make('Parametr'),
                                        Repeater\TableColumn::make('Hodnota'),
                                        Repeater\TableColumn::make('Parametr – EN'),
                                        Repeater\TableColumn::make('Hodnota – EN'),
                                    ])
                                    ->schema([
                                        TextInput::make('nazev')->hiddenLabel()->required()->maxLength(30),
                                        TextInput::make('hodnota')->hiddenLabel()->required()->maxLength(40),
                                        TextInput::make('nazev_en')->hiddenLabel()->required()->maxLength(30),
                                        TextInput::make('hodnota_en')->hiddenLabel()->required()->maxLength(40),
                                    ])
                                    ->reorderableWithDragAndDrop()
                                    ->addActionLabel('Přidat parametr')
                                    ->columnSpanFull(),
                                ...self::text('podminky', 'Podmínky (MOQ, vzorek, výroba)', 200, povinne: false),
                            ]),
                    ]),
                Section::make('Poznámka pod podmínkami')
                    ->columns(2)
                    ->schema([
                        ...self::text('podminky_poznamka', 'Poznámka', 120),
                    ]),
            ]);
    }
}
