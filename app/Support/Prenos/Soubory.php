<?php

namespace App\Support\Prenos;

use Illuminate\Support\Facades\Storage;

/**
 * Soubory, na které řádek odkazuje (fotky, logo, PDF…): hodnota sloupce,
 * která vypadá jako cesta k souboru a ten soubor na disku `public` nebo
 * `local` je. Projde i JSON (pole fotek z Filamentu). Cesty s „..“
 * nebo absolutní se nikdy neberou.
 */
class Soubory
{
    public const DISKY = ['public', 'local'];

    private const PRIPONY = 'webp|jpe?g|png|gif|svg|avif|ico|pdf|mp4|webm|mp3|docx?|xlsx?|pptx?|odt|ods|txt|csv|zip';

    /** @return list<array{disk: string, cesta: string}> */
    public static function zRadku(array $radek, bool $jenExistujici = true): array
    {
        $nalezene = [];

        foreach (self::retezce($radek) as $hodnota) {
            if (! self::jeCesta($hodnota)) {
                continue;
            }

            foreach (self::DISKY as $disk) {
                if (! $jenExistujici || Storage::disk($disk)->exists($hodnota)) {
                    $nalezene[$disk.':'.$hodnota] = ['disk' => $disk, 'cesta' => $hodnota];

                    if ($jenExistujici) {
                        break;
                    }
                }
            }
        }

        return array_values($nalezene);
    }

    public static function jeCesta(string $hodnota): bool
    {
        return strlen($hodnota) <= 500
            && preg_match('#^[\pL\pN_\-. /]+\.('.self::PRIPONY.')$#iu', $hodnota)
            && ! str_starts_with($hodnota, '/')
            && ! str_contains('/'.$hodnota.'/', '/../')
            && ! str_contains($hodnota, '//');
    }

    /** @return list<string> */
    private static function retezce(mixed $hodnota): array
    {
        if (is_array($hodnota)) {
            return array_merge([], ...array_map(self::retezce(...), array_values($hodnota)));
        }

        if (! is_string($hodnota) || $hodnota === '') {
            return [];
        }

        if (in_array($hodnota[0], ['[', '{'], true) && is_array($json = json_decode($hodnota, true))) {
            return self::retezce($json);
        }

        return [$hodnota];
    }
}
