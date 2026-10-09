<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oznámení (docs/oznameni.md): zpráva žije jednou na serveru a dostane se
 * k lidem kanály – centrum (zvoneček), pruh přes web, e-mail přes Poštu,
 * později web push a mobil.
 *
 *  - oznameni: samotná zpráva (druh, text, kanály, cílení, stav, plán),
 *  - oznameni_prijemci: komu přišla – přečteno, prokliknuto, archiv (centrum),
 *  - oznameni_doruceni: doručení po příjemci a kanálu (e-mail, push) se stavem,
 *  - oznameni_predvolby: co chce uživatel dostávat (druh × kanál),
 *  - oznameni_souhlasy: záznam udělení a odvolání souhlasu s novinkami (GDPR),
 *  - oznameni_skupiny (+ _clenove): pojmenované skupiny příjemců.
 *
 * Opakovatelná (každá tabulka jen když chybí) a bez dat – čerstvá instalace
 * nesmí „mít data“ (CistaInstalaceTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oznameni')) {
            Schema::create('oznameni', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                // Odkud: administrace (napsal člověk), aplikace (z kódu), portal (Sim&Ren).
                $table->string('zdroj', 20)->default('administrace');
                // Vlastní klíč zdroje (id oznámení v portálu, „objednavka-104-odeslana“) – idempotence.
                $table->string('zdroj_klic', 120)->nullable();
                $table->string('druh', 20);
                $table->string('zavaznost', 20)->default('info');
                $table->string('titulek', 160);
                $table->text('text')->nullable();
                $table->string('odkaz', 500)->nullable();
                $table->string('odkaz_text', 60)->nullable();
                $table->json('kanaly');
                $table->json('cileni');
                $table->string('stav', 20)->default('koncept')->index();
                $table->timestamp('naplanovano_na')->nullable()->index();
                $table->timestamp('odeslano_at')->nullable()->index();
                // Pruh: kdy se ukazuje. Událost: odstávka od–do (odpočet v pruhu).
                $table->timestamp('pruh_od')->nullable();
                $table->timestamp('pruh_do')->nullable();
                $table->timestamp('udalost_od')->nullable();
                $table->timestamp('udalost_do')->nullable();
                $table->unsignedInteger('pocet_prijemcu')->nullable();
                $table->foreignId('vytvoril_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('odeslal_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['zdroj', 'zdroj_klic']);
            });
        }

        if (! Schema::hasTable('oznameni_prijemci')) {
            Schema::create('oznameni_prijemci', function (Blueprint $table) {
                $table->id();
                $table->foreignId('oznameni_id')->constrained('oznameni')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                // Je v centru (zvonečku)? Ne, když si uživatel druh v centru vypnul.
                $table->boolean('v_centru')->default(true);
                $table->timestamp('precteno_at')->nullable();
                $table->timestamp('prokliknuto_at')->nullable();
                $table->timestamp('archivovano_at')->nullable();
                $table->timestamps();

                $table->unique(['oznameni_id', 'user_id']);
                $table->index(['user_id', 'v_centru', 'archivovano_at', 'precteno_at'], 'oznameni_prijemci_centrum');
            });
        }

        if (! Schema::hasTable('oznameni_doruceni')) {
            Schema::create('oznameni_doruceni', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prijemce_id')->constrained('oznameni_prijemci')->cascadeOnDelete();
                $table->string('kanal', 20);
                // ceka, odeslano, preskoceno (důvod), chyba (důvod)
                $table->string('stav', 20)->default('ceka');
                $table->string('duvod', 255)->nullable();
                // E-mail: záznam v Logy → E-maily (stav doručení doplní Pošta).
                $table->unsignedBigInteger('mail_log_id')->nullable()->index();
                $table->timestamp('odeslano_at')->nullable();
                $table->timestamps();

                $table->unique(['prijemce_id', 'kanal']);
                $table->index(['kanal', 'stav']);
            });
        }

        if (! Schema::hasTable('oznameni_predvolby')) {
            Schema::create('oznameni_predvolby', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                // {"novinky": {"email": true, "centrum": false}, …} – co tu není, platí výchozí.
                $table->json('kanaly')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oznameni_souhlasy')) {
            Schema::create('oznameni_souhlasy', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('druh', 20);
                $table->string('kanal', 20);
                $table->boolean('udelen');
                // predvolby, odhlaseni (odkaz z e-mailu), jedno-kliknuti (List-Unsubscribe-Post), administrace
                $table->string('zdroj', 30);
                // Přesné znění, se kterým člověk souhlasil – důkaz souhlasu.
                $table->string('text', 500)->nullable();
                $table->string('ip_adresa', 45)->nullable();
                $table->string('prohlizec', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['user_id', 'druh', 'kanal']);
            });
        }

        if (! Schema::hasTable('oznameni_skupiny')) {
            Schema::create('oznameni_skupiny', function (Blueprint $table) {
                $table->id();
                $table->string('nazev', 120)->unique();
                $table->string('popis', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oznameni_skupiny_clenove')) {
            Schema::create('oznameni_skupiny_clenove', function (Blueprint $table) {
                $table->foreignId('skupina_id')->constrained('oznameni_skupiny')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->primary(['skupina_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oznameni_skupiny_clenove');
        Schema::dropIfExists('oznameni_skupiny');
        Schema::dropIfExists('oznameni_souhlasy');
        Schema::dropIfExists('oznameni_predvolby');
        Schema::dropIfExists('oznameni_doruceni');
        Schema::dropIfExists('oznameni_prijemci');
        Schema::dropIfExists('oznameni');
    }
};
