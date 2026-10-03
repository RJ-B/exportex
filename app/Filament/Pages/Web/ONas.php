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

    protected static ?string $navigationLabel = 'O nás';

    protected static ?string $title = 'O nás';

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
                Section::make('Text')
                    ->columns(2)
                    ->schema([
                        ...self::odstavec('text', 'První odstavec', 800),
                        ...self::odstavec('text_druhy', 'Druhý odstavec', 800, povinne: false),
                    ]),
                Section::make('Fotka')
                    ->columns(2)
                    ->schema([
                        self::fotka(FileUpload::make('foto')->label('Fotka')->required(), 'o-nas')
                            ->helperText('Na šířku 3 : 2. Fotka se sama zmenší a uloží jako WebP.'),
                        TextInput::make('foto_popis')->label('Popis fotky (pro nevidomé a vyhledávače)')->maxLength(160),
                        ...self::text('popisek', 'Popisek na fotce', 60),
                        TextInput::make('popisek_misto')->label('Místo a rok na fotce')->maxLength(40)
                            ->helperText('Stejné v obou jazycích, velkými písmeny.'),
                    ]),
            ]);
    }
}
