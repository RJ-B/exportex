<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → Jak to funguje: kroky od poptávky po vykládku (čísluje je web sám). */
class JakToFunguje extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Jak to funguje';

    protected static ?string $title = 'Jak to funguje';

    protected static ?string $slug = 'web/jak-to-funguje';

    protected static ?int $navigationSort = 2;

    protected static function obsah(): string
    {
        return 'jak';
    }

    public static function klicSekce(): ?string
    {
        return 'jak';
    }

    public static function odkazSekce(): ?string
    {
        return '/#jak';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(),
                Section::make('Kroky')
                    ->description('Čísla 01, 02… doplní web podle pořadí.')
                    ->schema([
                        Repeater::make('kroky')->hiddenLabel()
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['nazev'] ?? null)
                            ->addActionLabel('Přidat krok')
                            ->schema(self::karta()),
                    ]),
            ]);
    }

    /** Název a popis česky a anglicky – karta kroku, dokladu i ukázky zakázky. */
    public static function karta(): array
    {
        return [
            ...self::text('nazev', 'Název', 80),
            ...self::dvojice('popis', 'Popis', fn (string $n) => Textarea::make($n)->rows(3)->maxLength(400)->required()),
        ];
    }

    /** Karta se štítkem (Doklady a clo, Ukázky zakázek) – štítek je v obou jazycích stejný. */
    public static function kartaSeStitkem(): array
    {
        return [
            TextInput::make('stitek')->label('Štítek')->required()->maxLength(30)->columnSpanFull()
                ->helperText('Stejný v obou jazycích (např. GSP+, HORECA).'),
            ...self::karta(),
        ];
    }
}
