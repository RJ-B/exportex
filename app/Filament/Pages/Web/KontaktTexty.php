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

    protected static ?string $navigationLabel = 'Kontakt – texty';

    protected static ?string $title = 'Kontakt – texty';

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
                Section::make('Kontakt vedle formuláře')
                    ->description('E-mail a telefon se berou z Hlavičky a patičky. WhatsApp a Telegram vedou na stejné telefonní číslo.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('osoba')->label('Kontaktní osoba')->maxLength(80)->columnSpanFull()
                            ->helperText('I v patičce a ve strukturovaných datech pro vyhledávače.'),
                        Toggle::make('whatsapp')->label('Tlačítko WhatsApp'),
                        Toggle::make('telegram')->label('Tlačítko Telegram'),
                    ]),
                Section::make('Formulář')
                    ->columns(2)
                    ->schema([
                        ...self::text('poznamka', 'Poznámka pod tlačítkem', 80),
                        ...self::text('odeslano', 'Text po odeslání', 200),
                    ]),
            ]);
    }
}
