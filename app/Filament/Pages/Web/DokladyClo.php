<?php

namespace App\Filament\Pages\Web;

use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Obsah webu → Doklady a clo: karty GSP+, OEKO-TEX, REACH. */
class DokladyClo extends StrankaSekceWebu
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public static function getNavigationLabel(): string
    {
        return __('administrace.doklady_clo.doklady_a_clo');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('administrace.doklady_clo.doklady_a_clo');
    }

    protected static ?string $slug = 'web/doklady-a-clo';

    protected static ?int $navigationSort = 4;

    protected static function obsah(): string
    {
        return 'doklady';
    }

    public static function klicSekce(): ?string
    {
        return 'doklady';
    }

    public static function odkazSekce(): ?string
    {
        return '/#doklady';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                self::nadpisSekce(),
                Section::make(__('administrace.doklady_clo.karty'))
                    ->schema([
                        Repeater::make('polozky')->hiddenLabel()
                            ->columns(2)
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['stitek'] ?? null)
                            ->addActionLabel(__('administrace.doklady_clo.pridat_kartu'))
                            ->schema(JakToFunguje::kartaSeStitkem()),
                    ]),
            ]);
    }
}
