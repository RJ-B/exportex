<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → Trasa: mapa Uzbekistán → Evropa a text o přepravě. Mapa sama je pevná. */
class Trasa extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Trasa';

    protected static ?string $title = 'Trasa';

    protected static ?string $slug = 'web/trasa';

    protected static ?int $navigationSort = 3;

    protected static function obsah(): string
    {
        return 'trasa';
    }

    public static function klicSekce(): ?string
    {
        return 'trasa';
    }

    public static function odkazSekce(): ?string
    {
        return '/#trasa';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(popis: true),
                Section::make('Popisky v mapě')
                    ->description('Oblouk vede z Uzbekistánu do Evropy; šipka míří do Česka uprostřed kontinentu. Mění se jen texty u značek.')
                    ->columns(2)
                    ->schema([
                        ...self::text('odkud', 'Odkud', 30),
                        ...self::text('odkud_popis', 'Odkud – podtitulek', 40),
                        ...self::text('kam', 'Kam', 30),
                        ...self::text('kam_popis', 'Kam – podtitulek', 40),
                    ]),
            ]);
    }
}
