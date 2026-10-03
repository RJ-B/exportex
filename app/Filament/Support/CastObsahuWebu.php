<?php

namespace App\Filament\Support;

use App\Filament\Clusters\ObsahWebu;
use App\Support\SekceWebu;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;

/**
 * Stránka nebo resource Obsahu webu. Kam patří, určuje config
 * sablona.obsah_webu: 'sekce' = do clusteru ObsahWebu (lišta nahoře),
 * 'menu' = do skupiny „Obsah webu“ v menu, null = aplikace bez webu (skrytá).
 *
 * Projekt tím zařazuje i své sekce webu (Úvod, Služby, O nás…).
 *
 * Vypínatelná sekce (SekceWebu) vrací z klicSekce() svůj klíč – pak má
 * v menu štítek „vypnuto“ a v hlavičce stránky přepínač. Hlavička
 * a patička klíč nemá (vypnout nejde).
 */
trait CastObsahuWebu
{
    public static function obsahWebu(): ?string
    {
        return config('sablona.obsah_webu');
    }

    public static function getCluster(): ?string
    {
        return static::obsahWebu() === 'sekce' ? ObsahWebu::class : null;
    }

    /**
     * V liště Obsahu webu (podoba 'sekce') sloučí části jedné sekce do rozbalovací
     * nabídky – např. „Služby“: texty, položky, ceník. null = samostatná záložka.
     */
    public static function podskupinaObsahu(): ?string
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return match (static::obsahWebu()) {
            'menu' => 'Obsah webu',
            'sekce' => static::podskupinaObsahu(),
            default => null,
        };
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::obsahWebu() !== null && parent::shouldRegisterNavigation();
    }

    /** Správce (admin i superadmin); u aplikace bez webu nikdo. */
    public static function canAccess(): bool
    {
        return static::obsahWebu() !== null && (auth()->user()?->jeSpravce() ?? false);
    }

    /** Klíč vypínatelné sekce (SekceWebu); null = vypnout nejde. */
    public static function klicSekce(): ?string
    {
        return null;
    }

    /**
     * Jde sekci vypnout? Jádro webu (např. rozvrh a rezervace) vypnout nejde,
     * jen přesunout – v pořadí a v menu je, přepínač nemá.
     */
    public static function vypnoutJde(): bool
    {
        return true;
    }

    /**
     * Patří sekce do menu webu? Sekce jen na stránce (galerie) je v pořadí
     * a jde vypnout, ale v menu odkaz nemá.
     */
    public static function vMenuWebu(): bool
    {
        return true;
    }

    public static function nazevSekce(): string
    {
        return static::getNavigationLabel();
    }

    /**
     * Adresa sekce na webu (do menu webu). null = sekce není samostatná
     * stránka webu (např. měření) – do pořadí a menu webu nepatří.
     */
    public static function odkazSekce(): ?string
    {
        return null;
    }

    public static function maOdkaz(): bool
    {
        return filled(rescue(fn () => static::odkazSekce(), null, false));
    }

    public static function getNavigationSortVychozi(): ?int
    {
        return static::$navigationSort;
    }

    /**
     * Ke které sekci webu část patří (texty sekce = její klíč; seznam služeb,
     * ceník… vrací klíč sekce Služby). Podle toho se řadí za ní.
     */
    public static function sekceWebu(): ?string
    {
        return static::klicSekce();
    }

    /** Pořadí části uvnitř sekce (texty 0, další části 1, 2…). */
    public static function poradiVSekci(): int
    {
        return 0;
    }

    /**
     * Části sekcí webu v pořadí ze „Sekcí na webu“ (100 + 10 × pozice + pořadí
     * v sekci); ostatní stránky Obsahu webu mají vlastní pořadí (Hlavička
     * a patička před sekcemi, Ochrana osobních údajů a SEO za nimi).
     */
    public static function getNavigationSort(): ?int
    {
        $sekce = static::sekceWebu();
        $pozice = $sekce ? SekceWebu::pozice($sekce) : null;

        return $pozice === null ? static::$navigationSort : 100 + 10 * $pozice + static::poradiVSekci();
    }

    public static function getNavigationBadge(): ?string
    {
        $klic = static::klicSekce();

        return $klic && ! SekceWebu::zapnuta($klic) ? 'vypnuto' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    /** Přepínač „Na webu zapnuto / vypnuto“ do hlavičky stránky sekce. */
    protected function prepinacSekce(): ?Action
    {
        $klic = static::klicSekce();

        if (! $klic || ! static::vypnoutJde() || static::obsahWebu() === null) {
            return null;
        }

        return Action::make('prepnoutSekci')
            ->label(fn () => SekceWebu::zapnuta($klic) ? 'Na webu zapnuto' : 'Na webu vypnuto')
            ->icon(fn () => SekceWebu::zapnuta($klic) ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
            ->color(fn () => SekceWebu::zapnuta($klic) ? 'success' : 'gray')
            ->requiresConfirmation(fn () => SekceWebu::zapnuta($klic))
            ->modalHeading(fn () => 'Vypnout sekci „'.static::nazevSekce().'“?')
            ->modalDescription('Návštěvníci ji na webu přestanou vidět. Obsah zůstane a jde ji kdykoli zapnout.')
            ->modalSubmitActionLabel('Vypnout')
            ->modalSubmitAction(fn (Action $action) => $action->color("warning"))
            ->action(function () use ($klic): void {
                $zapnout = ! SekceWebu::zapnuta($klic);
                SekceWebu::nastav($klic, $zapnout);

                Notification::make()->title(static::nazevSekce().($zapnout ? ' je na webu zapnutá' : ' je na webu vypnutá'))->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return array_values(array_filter([$this->prepinacSekce()]));
    }
}
