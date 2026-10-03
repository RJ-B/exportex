<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Údaje exportex s.r.o., se kterými web vznikl (dřív natvrdo v index.html
 * a soukromi.html). Texty sekcí mají výchozí obsah v App\Support\ObsahWebu,
 * takže po nasazení je web hned stejný jako statický. Doplní se jen klíče,
 * které ještě nejsou – co správce v administraci změnil, se nepřepíše.
 *
 * Pošta exportex.cz je u Forpsi: předvyplní se server, port, šifrování
 * a schránka (zároveň odesílatel). Heslo NE – zadá ho správce sám v Obsahu
 * webu → Kontakt a formulář; do té doby web poptávky jen ukládá a e-mail
 * neodchází (Pošta to hlásí štítkem).
 */
return new class extends Migration
{
    private const HODNOTY = [
        'zaklad.nazev' => 'Exportex',
        'zaklad.email' => 'mikyska@exportex.cz',
        'zaklad.telefon' => '+420 734 479 684',
        'zaklad.firma' => 'exportex s.r.o.',
        'zaklad.ico' => '17671833',
        'zaklad.adresa' => 'Na Poříčí 1070/19, Nové Město, 110 00 Praha 1',
        'zaklad.rejstrik' => 'Zapsaná u Městského soudu v Praze, oddíl C, vložka 374806',
        'web.tagline' => 'textil z Uzbekistánu a Střední Asie do EU',
        'posta.host' => 'smtp.forpsi.com',
        'posta.port' => '465',
        'posta.sifrovani' => 'smtps',
        'posta.uzivatel' => 'mikyska@exportex.cz',
        'gdpr.ucinnost_od' => '2026-10-03',
        'gdpr.formular_udaje' => 'jméno a příjmení, název firmy, e-mailová adresa, telefonní číslo, pokud jej uvedete, a obsah poptávky',
        'gdpr.formular_doba' => 'po dobu jednání o poptávce a následně nejdéle 3 roky',
        'gdpr.smlouvy' => '1',
        'gdpr.dalsi_prijemci' => "INTERNET CZ, a.s. (Forpsi) – provoz e-mailové schránky a SMTP serveru, přes který odchází upozornění na poptávku z formuláře; údaje jsou zpracovány na serverech v EU\nContabo GmbH – poskytovatel serveru, na kterém web běží; server je umístěn v Německu, tedy v rámci EU\nSimRen s.r.o. – technická správa serveru a webu",
        'gdpr.doplnek' => '<p><strong>Další komunikace:</strong> pokud nám napíšete e-mailem, zavoláte nebo nás kontaktujete přes WhatsApp či Telegram, zpracujeme údaje, které nám sami sdělíte, ke stejnému účelu a na stejném právním základě jako zprávu z formuláře.</p><p><strong>Marketing:</strong> údaje z poptávek nevyužíváme k marketingu, nepředáváme je třetím stranám k jejich vlastním účelům a neprodáváme je. Pokud dojde k obchodu, mohou k nim mít přístup také poskytovatelé účetních služeb v roli zpracovatelů.</p>',
    ];

    public function up(): void
    {
        $ted = now();

        foreach (self::HODNOTY as $klic => $hodnota) {
            DB::table('nastaveni')->insertOrIgnore(['klic' => $klic, 'hodnota' => $hodnota, 'created_at' => $ted, 'updated_at' => $ted]);
        }
    }

    public function down(): void
    {
        // Data, ne schéma – zpět se nemažou (mohl je mezitím upravit správce).
    }
};
