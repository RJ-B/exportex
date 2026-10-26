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
 * VÝJIMKA: obrázek pro sdílení (og:image, 1200 × 630) se ukládá jako JPEG –
 * LinkedIn a další sítě WebP v náhledu odkazu nečtou (ulozSdileni, jpegSdileni).
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

    /** Obrázek pro sdílení na sociálních sítích (og:image). */
    public const SDILENI_SIRKA = 1200;

    public const SDILENI_VYSKA = 630;

    /** Kvalita JPEGu obrázku pro sdílení. */
    public const KVALITA_SDILENI = 85;

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

    /**
     * Obrázek pro sdílení (og:image) – oříznutý na přesně 1200 × 630 px (poměr
     * 1,91 : 1, jak ho chtějí Facebook, LinkedIn i X) a uložený jako JPEG
     * (kvalita 85, sRGB, bez metadat, průhlednost na bílém). Výjimka z pravidla
     * „fotky vždy WebP“: LinkedIn a další sítě WebP v náhledu odkazu nečtou.
     * Ořízne se střed, nic se nedeformuje. Když převod selže, uloží se originál.
     */
    public static function ulozSdileni(UploadedFile $soubor, string $adresar, string $disk = 'public'): string
    {
        try {
            $jpeg = self::naJpegSdileni((string) file_get_contents($soubor->getRealPath()));
            $cesta = trim($adresar, '/').'/'.Str::uuid().'.jpg';

            Storage::disk($disk)->put($cesta, $jpeg);

            return $cesta;
        } catch (Throwable $e) {
            Log::info('Obrázek pro sdílení se nepřevedl na JPEG, ukládá se originál', ['chyba' => $e->getMessage()]);

            return $soubor->store($adresar, $disk);
        }
    }

    /**
     * Cesta k obrázku pro sdílení jako JPEG. Starší WebP (obrázek pro sdílení
     * uložený dřív, logo jako náhradní obrázek) dostane vedle sebe JPEG se
     * stejným jménem (.jpg) – vznikne jednou, při prvním použití, data se
     * nemění. Jiný formát i adresa beze změny; když převod selže, původní cesta.
     */
    public static function jpegSdileni(?string $cesta, string $disk = 'public'): ?string
    {
        if (blank($cesta) || preg_match('#^https?://#i', $cesta) || ! preg_match('/\.webp$/i', $cesta)) {
            return $cesta;
        }

        $jpeg = (string) preg_replace('/\.webp$/i', '.jpg', $cesta);
        $uloziste = Storage::disk($disk);

        try {
            if ($uloziste->exists($jpeg)) {
                return $jpeg;
            }

            if (! $uloziste->exists($cesta)) {
                return $cesta;
            }

            $uloziste->put($jpeg, self::naJpegSdileni((string) $uloziste->get($cesta), orez: false));

            return $jpeg;
        } catch (Throwable $e) {
            Log::info('Obrázek pro sdílení se nepřevedl na JPEG, zůstává WebP', ['cesta' => $cesta, 'chyba' => $e->getMessage()]);

            return $cesta;
        }
    }

    /**
     * Surová data obrázku → JPEG pro sdílení: ořez středu na 1200 × 630
     * ($orez = false: rozměr zůstane), průhlednost na bílém, kvalita 85.
     * GD ukládá RGB bez barevného profilu (= sRGB); EXIF, komentář a jiná
     * metadata se z výsledku vyřadí. Vyhodí výjimku, když to nejde.
     */
    public static function naJpegSdileni(string $data, bool $orez = true): string
    {
        if (! function_exists('imagejpeg')) {
            throw new \RuntimeException(__('obecne.fotky.php_gd_neumi_jpeg'));
        }

        $obrazek = @imagecreatefromstring($data);

        if ($obrazek === false) {
            throw new \RuntimeException(__('obecne.fotky.neznamy_format_obrazku'));
        }

        $obrazek = self::otocPodleExif($obrazek, $data);
        $zdrojSirka = imagesx($obrazek);
        $zdrojVyska = imagesy($obrazek);
        [$sirka, $vyska] = $orez ? [self::SDILENI_SIRKA, self::SDILENI_VYSKA] : [$zdrojSirka, $zdrojVyska];
        [$x, $y, $vyrezSirka, $vyrezVyska] = $orez ? self::vyrez($zdrojSirka, $zdrojVyska, $sirka, $vyska) : [0, 0, $zdrojSirka, $zdrojVyska];

        // JPEG průhlednost nezná – bez bílého podkladu by průhledné části zčernaly.
        $platno = imagecreatetruecolor($sirka, $vyska);
        imagefill($platno, 0, 0, (int) imagecolorallocate($platno, 255, 255, 255));
        imagealphablending($platno, true);
        imagecopyresampled($platno, $obrazek, 0, 0, $x, $y, $sirka, $vyska, $vyrezSirka, $vyrezVyska);

        ob_start();
        $ok = imagejpeg($platno, null, self::KVALITA_SDILENI);
        $jpeg = (string) ob_get_clean();

        if (! $ok || $jpeg === '') {
            throw new \RuntimeException(__('obecne.fotky.prevod_na_jpeg_selhal'));
        }

        return self::bezMetadat($jpeg);
    }

    /**
     * JPEG bez metadat: vyřadí segmenty APP1–APP15 (EXIF, XMP, profily…)
     * a komentář (GD do něj píše „CREATOR: gd-jpeg…“). JFIF (APP0) zůstává.
     */
    public static function bezMetadat(string $jpeg): string
    {
        if (! str_starts_with($jpeg, "\xFF\xD8")) {
            return $jpeg;
        }

        $vystup = "\xFF\xD8";
        $i = 2;
        $delka = strlen($jpeg);

        while ($i + 4 <= $delka && $jpeg[$i] === "\xFF") {
            $znacka = ord($jpeg[$i + 1]);

            // Začátek obrazových dat (SOS) – dál už metadata nejsou.
            if ($znacka === 0xDA) {
                break;
            }

            $segment = 2 + unpack('n', substr($jpeg, $i + 2, 2))[1];

            if (! (($znacka >= 0xE1 && $znacka <= 0xEF) || $znacka === 0xFE)) {
                $vystup .= substr($jpeg, $i, $segment);
            }

            $i += $segment;
        }

        return $vystup.substr($jpeg, $i);
    }

    /** Filamentí pole pro obrázek ke sdílení (1200 × 630 px, JPEG) – ukládá přes ulozSdileni(). */
    public static function poleSdileni(FileUpload $pole): FileUpload
    {
        return $pole
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(8192)
            ->saveUploadedFileUsing(fn (FileUpload $component, UploadedFile $file) => self::ulozSdileni(
                $file,
                (string) $component->getDirectory(),
                $component->getDiskName(),
            ));
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
     *
     * @param  array{0: int, 1: int}|null  $orez  přesný rozměr výsledku (ořízne střed), jinak jen zmenšení
     */
    public static function naWebp(string $data, ?array $orez = null): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException(__('obecne.fotky.php_gd_neumi_webp'));
        }

        $obrazek = @imagecreatefromstring($data);

        if ($obrazek === false) {
            throw new \RuntimeException(__('obecne.fotky.neznamy_format_obrazku'));
        }

        $obrazek = self::otocPodleExif($obrazek, $data);

        $sirka = imagesx($obrazek);
        $vyska = imagesy($obrazek);
        $delsi = max($sirka, $vyska);

        if ($orez !== null) {
            $obrazek = self::orizni($obrazek, $orez[0], $orez[1]);
        } elseif ($delsi > self::MAX_STRANA) {
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
            throw new \RuntimeException(__('obecne.fotky.prevod_na_webp_selhal'));
        }

        return $webp;
    }

    /** Zmenší a ořízne obrázek na přesný rozměr („cover“ – vyplní celou plochu, střed zůstane). */
    private static function orizni(\GdImage $obrazek, int $sirka, int $vyska): \GdImage
    {
        [$x, $y, $vyrezSirka, $vyrezVyska] = self::vyrez(imagesx($obrazek), imagesy($obrazek), $sirka, $vyska);

        $vysledek = imagecreatetruecolor($sirka, $vyska);
        imagealphablending($vysledek, false);
        imagesavealpha($vysledek, true);
        imagefill($vysledek, 0, 0, imagecolorallocatealpha($vysledek, 255, 255, 255, 127));
        imagecopyresampled($vysledek, $obrazek, 0, 0, $x, $y, $sirka, $vyska, $vyrezSirka, $vyrezVyska);

        return $vysledek;
    }

    /**
     * Výřez středu zdroje s poměrem cíle („cover“).
     *
     * @return array{0: int, 1: int, 2: int, 3: int} x, y, šířka, výška výřezu
     */
    private static function vyrez(int $zdrojSirka, int $zdrojVyska, int $sirka, int $vyska): array
    {
        $pomer = max($sirka / $zdrojSirka, $vyska / $zdrojVyska);
        $vyrezSirka = min($zdrojSirka, (int) round($sirka / $pomer));
        $vyrezVyska = min($zdrojVyska, (int) round($vyska / $pomer));

        return [(int) floor(($zdrojSirka - $vyrezSirka) / 2), (int) floor(($zdrojVyska - $vyrezVyska) / 2), $vyrezSirka, $vyrezVyska];
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
