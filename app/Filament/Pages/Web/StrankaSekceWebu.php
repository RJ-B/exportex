<?php

namespace App\Filament\Pages\Web;

use App\Filament\Support\CastObsahuWebu;
use App\Support\Fotky;
use App\Support\ObsahWebu;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;

/**
 * Stránka jedné sekce webu v Obsahu webu (Úvod, Sortiment, Trasa…). Ukládá
 * jen svůj klíč `obsah.<sekce>` (App\Support\ObsahWebu) – nic jiného nepřepíše.
 * Sekce, které jde vypnout a seřadit, vrací klicSekce() a odkazSekce().
 *
 * Web je dvojjazyčný: každý text má pole česky a vedle něj anglicky (dvojice()).
 */
abstract class StrankaSekceWebu extends Page
{
    use CastObsahuWebu;

    protected string $view = 'filament.pages.formular';

    public ?array $data = [];

    /** Klíč obsahu v ObsahWebu::VYCHOZI. */
    abstract protected static function obsah(): string;

    public function mount(): void
    {
        $this->form->fill(ObsahWebu::sekce(static::obsah()));
    }

    public function uloz(): void
    {
        ObsahWebu::uloz(static::obsah(), $this->form->getState());

        Notification::make()->title('Uloženo')->body('Změny jsou hned na webu.')->success()->send();
    }

    /**
     * Pole česky a hned vedle anglicky (`<pole>` a `<pole>_en`). Do sekce se
     * dvěma sloupci – čeština vlevo, angličtina vpravo.
     *
     * @param  Closure(string): (TextInput|Textarea)  $pole
     * @return list<TextInput|Textarea>
     */
    public static function dvojice(string $nazev, string $popisek, Closure $pole): array
    {
        return [
            $pole($nazev)->label($popisek),
            $pole($nazev.'_en')->label($popisek.' – anglicky'),
        ];
    }

    /** Krátký text česky a anglicky. */
    public static function text(string $nazev, string $popisek, int $max = 160, bool $povinne = true): array
    {
        return self::dvojice($nazev, $popisek, fn (string $n) => TextInput::make($n)->maxLength($max)->required($povinne));
    }

    /** Odstavec česky a anglicky. */
    public static function odstavec(string $nazev, string $popisek, int $max = 600, bool $povinne = true): array
    {
        return self::dvojice($nazev, $popisek, fn (string $n) => Textarea::make($n)->rows(3)->maxLength($max)->required($povinne));
    }

    /** Štítek a nadpis sekce – stejné u všech sekcí webu (štítek je i v menu webu). */
    public static function nadpisSekce(bool $popis = false): Section
    {
        return Section::make('Nadpis sekce')
            ->description('Štítek je nad nadpisem s číslem sekce a zároveň v menu webu.')
            ->columns(2)
            ->schema([
                ...self::text('stitek', 'Štítek (v menu)', 40),
                ...self::text('nadpis', 'Nadpis', 160),
                ...($popis ? self::odstavec('popis', 'Text pod nadpisem', 400) : []),
            ]);
    }

    /**
     * Fotky webu: původní leží v public/assets/img (cesta začíná „/assets/“),
     * nově nahrané se uloží jako WebP na disk public (App\Support\Fotky).
     * Původní soubory na disku public nejsou – proto bez kontroly existence
     * a s vlastní adresou náhledu, jinak by je formulář při uložení zahodil.
     */
    protected static function fotka(FileUpload $pole, string $adresar): FileUpload
    {
        return Fotky::pole($pole
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(15 * 1024)
            ->disk('public')
            ->directory($adresar)
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (string $file) => [
                'name' => basename(strtok($file, '?')),
                'size' => 0,
                'type' => null,
                'url' => ObsahWebu::obrazek($file),
            ])
            ->helperText('JPEG, PNG nebo WebP. Fotka se sama zmenší a uloží jako WebP.'));
    }
}
