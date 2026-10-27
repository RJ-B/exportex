<?php

namespace App\Support\Posta;

/**
 * Poskytovatelé schránek pro stránku E-mailová schránka: předvyplněný server,
 * port, šifrování a krátký návod, kde klient údaje najde (config/posta.php →
 * poskytovatele, texty lang/…/pruvodce.php → poskytovatele).
 *
 * Seznam platí i bez spojení s Poštou. Z předvoleb Pošty (`GET /predvolby`)
 * se bere jen, jestli jde přihlášení přes Google / Microsoft (`oauth_dostupne`).
 */
final class Poskytovatele
{
    /**
     * @param  list<array<string, mixed>>  $zPosty  předvolby Pošty (prázdné = bez spojení)
     * @return list<array{klic: string, nazev: string, navod: string, smtp_host: ?string, smtp_port: ?int, smtp_sifrovani: ?string, oauth: ?string, oauth_dostupne: bool}>
     */
    public static function vse(array $zPosty = []): array
    {
        $posta = collect($zPosty)->keyBy('klic');
        // Texty skupiny najednou – projekt, který přidá poskytovatele, doplní `<klic>_nazev` a `<klic>_navod`.
        $texty = (array) __('pruvodce.poskytovatele');

        return collect((array) config('posta.poskytovatele', []))->map(fn (array $p, string $klic) => [
            'klic' => $klic,
            'nazev' => $texty[$klic.'_nazev'] ?? ucfirst($klic),
            'navod' => $texty[$klic.'_navod'] ?? $texty['jiny_navod'] ?? '',
            'smtp_host' => $p['smtp_host'] ?? null,
            'smtp_port' => isset($p['smtp_port']) ? (int) $p['smtp_port'] : null,
            'smtp_sifrovani' => $p['smtp_sifrovani'] ?? null,
            'oauth' => $p['oauth'] ?? null,
            'oauth_dostupne' => filled($p['oauth'] ?? null) && (bool) ($posta->get($klic)['oauth_dostupne'] ?? false),
        ])->values()->all();
    }

    /** @return array<string, mixed>|null */
    public static function predvolba(?string $klic, array $zPosty = []): ?array
    {
        return collect(self::vse($zPosty))->firstWhere('klic', $klic);
    }

    /** Výběr do roletky: klíč → název. @return array<string, string> */
    public static function moznosti(): array
    {
        return collect(self::vse())->pluck('nazev', 'klic')->all();
    }

    /** Šifrování pro roletku. @return array<string, string> */
    public static function sifrovani(): array
    {
        return [
            'smtps' => __('pruvodce.poskytovatele.sifrovani_ssl'),
            'starttls' => __('pruvodce.poskytovatele.sifrovani_starttls'),
            'zadne' => __('pruvodce.poskytovatele.sifrovani_zadne'),
        ];
    }
}
