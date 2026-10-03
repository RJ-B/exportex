<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → Ukázky zakázek: typové zakázky napříč sortimentem. */
class UkazkyZakazek extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Ukázky zakázek';

    protected static ?string $title = 'Ukázky zakázek';

    protected static ?string $slug = 'web/ukazky-zakazek';

    protected static ?int $navigationSort = 6;

    protected static function obsah(): string
    {
        return 'reference';
    }

    public static function klicSekce(): ?string
    {
        return 'reference';
    }

    public static function odkazSekce(): ?string
    {
        return '/#reference';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(popis: true),
                Section::make('Zakázky')
                    ->schema([
                        Repeater::make('polozky')->hiddenLabel()
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['nazev'] ?? null)
                            ->addActionLabel('Přidat zakázku')
                            ->schema(JakToFunguje::kartaSeStitkem()),
                    ]),
            ]);
    }
}
