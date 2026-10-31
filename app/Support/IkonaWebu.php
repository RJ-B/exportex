<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Ikona webu v záložce prohlížeče (favicon) a na ploše telefonu (apple-touch-icon).
 *
 * Vzniká z loga – nebo ze samostatné „Ikony v záložce“, když je logo moc široké
 * a v 32 px by se nedalo přečíst – při jejich uložení v Obsahu webu, ne při každém
 * požadavku (pevné logo ve vzhledu webu, config sablona.vzhled: když je novější): čtvercová PNG 32, 180 a 192 px na disku public (web/ikona/). Poměr
 * stran se zachová, jednobarevné okraje loga se oříznou a zbytek čtverce zůstane
 * průhledný; 180 px (iPhone) má bílé pozadí, protože průhlednost iOS vyplní černou.
 *
 * Jen GD v PHP – webové PHP pod Hestií nesmí spouštět programy (proc_open, exec).
 * Ikona je PNG, ne WebP (pravidlo o WebP platí pro fotky, prohlížeče favicon
 * ve WebP neberou všude).
 *
 * Bez loga, nebo když se z něj ikona vytvořit nedá, platí výchozí neutrální ikona
 * z public/ (ikona-32.png, ikona-180.png, ikona-192.png a favicon.ico pro ty, kdo
 * se ptají rovnou na /favicon.ico).
 */
final class IkonaWebu
{
    /** Velikost => okraj v procentech strany (iPhone si rohy zaobluje sám, logo by se uřízlo). */
    public const VELIKOSTI = [32 => 0, 180 => 10, 192 => 0];

    private const DISK = 'public';

    private const ADRESAR = 'web/ikona';

    /**
     * Vytvoří ikony znovu z ikony v záložce, jinak z loga (LogoWebu – pevné ve
     * vzhledu webu, jinak nahrané). Volá se po uložení Obsahu webu
     * (NastaveniWebu::uloz), u pevného loga sama, když je logo novější než ikona.
     * Selhání nesmí uložení zastavit – zůstane výchozí ikona a záznam v logu.
     */
    public static function obnov(): void
    {
        $disk = Storage::disk(self::DISK);
        $disk->deleteDirectory(self::ADRESAR);

        // SVG GD nepřečte – pak z dalšího zdroje (pevná ikona SVG → logo PNG).
        $rastr = fn (?string $d) => $d !== null && ! preg_match('/<svg\b/i', $d) ? $d : null;
        $ikona = $rastr(LogoWebu::data('ikona'));
        $data = $ikona ?? $rastr(LogoWebu::data('logo'));
        // Pevná ikona od grafika je hotový čtverec (i s pozadím) – neořezávat.
        $orezat = $ikona === null || LogoWebu::pevnySoubor('ikona') === null;

        try {
            if ($data === null) {
                if (! LogoWebu::pevne() && filled(NastaveniWebu::get('ikona') ?: NastaveniWebu::get('logo'))) {
                    throw new \RuntimeException(__('obecne.ikona_webu.soubor_loga_na_disku_neni'));
                }

                return;
            }

            foreach (self::vytvor($data, $orezat) as $velikost => $png) {
                $disk->put(self::cesta($velikost), $png);
            }
        } catch (Throwable $e) {
            $disk->deleteDirectory(self::ADRESAR);
            Log::warning('Ikona webu se z loga nevytvořila, platí výchozí', ['chyba' => $e->getMessage()]);
        }
    }

    /**
     * Pevné logo (ikona) ve vzhledu webu se v administraci neukládá – ikony z něj
     * vzniknou při prvním zobrazení a znovu, když je soubor novější (nasazení
     * nového loga). Levné: jen existence a čas souboru.
     */
    private static function zPevnehoLoga(): void
    {
        $soubor = LogoWebu::pevnySoubor('ikona') ?? LogoWebu::pevnySoubor('logo');

        if ($soubor === null) {
            return;
        }

        try {
            $disk = Storage::disk(self::DISK);
            $cesta = self::cesta(32);

            if (! $disk->exists($cesta) || $disk->lastModified($cesta) < (int) @filemtime(public_path($soubor))) {
                self::obnov();
            }
        } catch (Throwable) {
            // Výchozí ikona.
        }
    }

    /**
     * Obrázek (PNG, WebP, JPEG) → čtvercová PNG všech velikostí. $orezat = ořízne
     * jednobarevný okraj (logo); hotová čtvercová ikona se nechá, jak je.
     *
     * @return array<int, string> velikost => PNG
     */
    public static function vytvor(string $data, bool $orezat = true): array
    {
        $obrazek = @imagecreatefromstring($data);

        if ($obrazek === false) {
            throw new \RuntimeException(__('obecne.ikona_webu.neznamy_format_obrazku'));
        }

        imagepalettetotruecolor($obrazek);
        imagesavealpha($obrazek, true);

        // Jednobarevný okraj kolem loga (průhledný, bílý) by ikonu zbytečně zmenšil.
        // Barvu okraje GD bere z rohů; obrázek celý v jedné barvě se neořízne.
        $orez = $orezat ? @imagecropauto($obrazek, IMG_CROP_SIDES) : false;
        if ($orez instanceof \GdImage && imagesx($orez) > 0 && imagesy($orez) > 0) {
            $obrazek = $orez;
        }

        $sirka = imagesx($obrazek);
        $vyska = imagesy($obrazek);
        $vysledek = [];

        foreach (self::VELIKOSTI as $velikost => $okrajProcent) {
            $platno = imagecreatetruecolor($velikost, $velikost);
            imagealphablending($platno, false);
            imagefill($platno, 0, 0, $velikost === 180
                ? imagecolorallocate($platno, 255, 255, 255)
                : imagecolorallocatealpha($platno, 0, 0, 0, 127));
            imagealphablending($platno, true);

            $prostor = $velikost - 2 * (int) round($velikost * $okrajProcent / 100);
            $pomer = min($prostor / $sirka, $prostor / $vyska);
            $w = max(1, (int) round($sirka * $pomer));
            $h = max(1, (int) round($vyska * $pomer));

            imagecopyresampled($platno, $obrazek, intdiv($velikost - $w, 2), intdiv($velikost - $h, 2), 0, 0, $w, $h, $sirka, $vyska);
            imagesavealpha($platno, true);

            ob_start();
            $ok = imagepng($platno, null, 9);
            $png = (string) ob_get_clean();

            if (! $ok || $png === '') {
                throw new \RuntimeException(__('obecne.ikona_webu.ulozeni_png_selhalo'));
            }

            $vysledek[$velikost] = $png;
        }

        return $vysledek;
    }

    /** Adresa ikony dané velikosti – vytvořené z loga, jinak výchozí. */
    public static function url(int $velikost): string
    {
        self::zPevnehoLoga();
        $cesta = self::cesta($velikost);

        try {
            $disk = Storage::disk(self::DISK);

            if ($disk->exists($cesta)) {
                // Stejná adresa po výměně loga – verze podle času, jinak by prohlížeč držel starou.
                return $disk->url($cesta).'?v='.$disk->lastModified($cesta);
            }
        } catch (Throwable) {
            // Výchozí ikona.
        }

        return asset('ikona-'.$velikost.'.png');
    }

    /** Značky do <head> každé stránky webu i administrace. */
    public static function html(): HtmlString
    {
        return new HtmlString(implode("\n", [
            '<link rel="icon" type="image/png" sizes="32x32" href="'.e(self::url(32)).'">',
            '<link rel="icon" type="image/png" sizes="192x192" href="'.e(self::url(192)).'">',
            '<link rel="apple-touch-icon" sizes="180x180" href="'.e(self::url(180)).'">',
        ]));
    }

    private static function cesta(int $velikost): string
    {
        return self::ADRESAR.'/ikona-'.$velikost.'.png';
    }
}
