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

    public static function getNavigationLabel(): string
    {
        return __('administrace.trasa.trasa');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('administrace.trasa.trasa');
    }

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
                Section::make(__('administrace.trasa.popisky_v_mape'))
                    ->description(__('administrace.trasa.oblouk_vede_z_uzbekistanu_do_evropy'))
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
