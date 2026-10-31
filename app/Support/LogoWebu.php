<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Logo webu – jedno pro web i administraci (levé menu, přihlášení) a z něj ikona
 * v záložce (IkonaWebu). Nic se nenastavuje zvlášť pro administraci.
 *
 * Odkud se bere:
 *  - pevné ve vzhledu webu (config sablona.vzhled.logo, soubor v public/) – web
 *    s vlastním designem, kde je logo součást vzhledu; v administraci se pak
 *    nenahrává a krok průvodce se neukazuje,
 *  - jinak nahrané v Obsahu webu → Hlavička a patička (disk public),
 *  - bez obojího nic: administrace ukáže výchozí ikonu a název webu.
 */
final class LogoWebu
{
    /** Je logo pevně ve vzhledu webu? Pak se v administraci nenahrává. */
    public static function pevne(): bool
    {
        return self::pevnySoubor('logo') !== null;
    }

    /** Adresa loga, nebo null, když web logo nemá. */
    public static function url(): ?string
    {
        if ($soubor = self::pevnySoubor('logo')) {
            return asset($soubor).'?v='.@filemtime(public_path($soubor));
        }

        $cesta = NastaveniWebu::get('logo');

        try {
            if (filled($cesta) && Storage::disk('public')->exists($cesta)) {
                return Storage::disk('public')->url($cesta);
            }
        } catch (Throwable) {
            // Bez loga.
        }

        return null;
    }

    /**
     * Široké logo (nápis) stojí v administraci samo – název webu v něm už je.
     * Užší značka (čtverec, symbol) dostane název vedle sebe.
     */
    public static function siroke(): bool
    {
        $data = self::data('logo');

        if ($data === null) {
            return false;
        }

        // SVG: rozměr z viewBox, jinak se bere jako nápis.
        if (preg_match('/<svg\b/i', $data)) {
            return ! preg_match('/viewBox="[\d.\-]+[ ,]+[\d.\-]+[ ,]+([\d.]+)[ ,]+([\d.]+)"/i', $data, $m)
                || (float) $m[2] <= 0 || (float) $m[1] / (float) $m[2] >= 2;
        }

        $rozmer = @getimagesizefromstring($data);

        return $rozmer !== false && $rozmer[1] > 0 && $rozmer[0] / $rozmer[1] >= 2;
    }

    /**
     * Surová data obrázku: 'logo', nebo 'ikona' (vlastní ikona v záložce –
     * když je logo moc široké). Pevné ze vzhledu webu má přednost před nahraným.
     */
    public static function data(string $co): ?string
    {
        if ($soubor = self::pevnySoubor($co)) {
            return (string) file_get_contents(public_path($soubor));
        }

        if (self::pevne()) {
            // Pevné logo bez pevné ikony: nahraná ikona už se nepoužije (pole není vidět).
            return null;
        }

        $cesta = NastaveniWebu::get($co);

        try {
            return filled($cesta) && Storage::disk('public')->exists($cesta) ? (string) Storage::disk('public')->get($cesta) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Pevný soubor ve vzhledu webu (cesta v public/), když je nastavený a existuje. */
    public static function pevnySoubor(string $co): ?string
    {
        $soubor = ltrim((string) config('sablona.vzhled.'.$co), '/');

        return $soubor !== '' && is_file(public_path($soubor)) ? $soubor : null;
    }
}
