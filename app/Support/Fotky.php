<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nahrané fotky se ukládají jako zmenšený WebP (pravidlo Sim&Ren pro všechny
 * projekty): otočit podle EXIF, zmenšit na 1600 px po delší straně, WebP
 * v kvalitě 80. Fotka z telefonu má 5–10 MB a 4000+ px – k zobrazení stačí
 * zlomek a plné rozlišení jen nafukuje úložiště a zálohy.
 *
 * Když převod selže (HEIC z iPhonu, poškozený soubor, GD bez WebP), uloží se
 * ORIGINÁL. Optimalizace nesmí fotku uživatele nikdy ztratit.
 *
 * Napojit do VŠECH cest, kudy fotka přichází (Filament formulář, API pro
 * mobil…) – na druhou cestu se snadno zapomene. Ve Filamentu:
 *
 *     Fotky::pole(FileUpload::make('foto')->image()->directory('fotky'))
 */
final class Fotky
{
    public const MAX_STRANA = 1600;

    public const KVALITA = 80;

    /** Uloží fotku do adresáře na disku a vrátí cestu k ní (relativní k disku). */
    public static function uloz(UploadedFile $soubor, string $adresar, string $disk = 'public'): string
    {
        try {
            $webp = self::naWebp((string) file_get_contents($soubor->getRealPath()));
            $cesta = trim($adresar, '/').'/'.Str::uuid().'.webp';

            Storage::disk($disk)->put($cesta, $webp);

            return $cesta;
        } catch (Throwable $e) {
            Log::info('Fotka se nepřevedla na WebP, ukládá se originál', ['chyba' => $e->getMessage()]);

            return $soubor->store($adresar, $disk);
        }
    }

    /** Filamentí pole pro nahrání fotky, které ukládá přes uloz(). */
    public static function pole(FileUpload $pole): FileUpload
    {
        return $pole->saveUploadedFileUsing(
            fn (FileUpload $component, UploadedFile $file) => self::uloz(
                $file,
                (string) $component->getDirectory(),
                $component->getDiskName(),
            ),
        );
    }

    /**
     * Surová data obrázku → WebP. Vyhodí výjimku, když to nejde –
     * o náhradním uložení originálu rozhoduje volající.
     */
    public static function naWebp(string $data): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('PHP GD neumí WebP.');
        }

        $obrazek = @imagecreatefromstring($data);

        if ($obrazek === false) {
            throw new \RuntimeException('Neznámý formát obrázku.');
        }

        $obrazek = self::otocPodleExif($obrazek, $data);

        $sirka = imagesx($obrazek);
        $vyska = imagesy($obrazek);
        $delsi = max($sirka, $vyska);

        if ($delsi > self::MAX_STRANA) {
            $pomer = self::MAX_STRANA / $delsi;
            $zmenseny = imagescale($obrazek, max(1, (int) round($sirka * $pomer)), max(1, (int) round($vyska * $pomer)));
            $obrazek = $zmenseny;
        }

        // Průhlednost z PNG zachovat.
        imagepalettetotruecolor($obrazek);
        imagealphablending($obrazek, true);
        imagesavealpha($obrazek, true);

        ob_start();
        $ok = imagewebp($obrazek, null, self::KVALITA);
        $webp = (string) ob_get_clean();

        if (! $ok || $webp === '') {
            throw new \RuntimeException('Převod na WebP selhal.');
        }

        return $webp;
    }

    /**
     * Telefon fotku často jen „označí“ orientací v EXIF a GD ji ignoruje –
     * bez otočení by fotka ležela na boku. EXIF mají jen JPEGy.
     */
    private static function otocPodleExif(\GdImage $obrazek, string $data): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($data, "\xFF\xD8")) {
            return $obrazek;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($data));
        $uhel = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($uhel === 0) {
            return $obrazek;
        }

        $otoceny = imagerotate($obrazek, $uhel, 0);

        return $otoceny;
    }
}
