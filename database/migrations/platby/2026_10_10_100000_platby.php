<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doplněk Platby (docs/platby.md). Migrace se načítá jen se zapnutým doplňkem
 * (PlatbyServiceProvider) – bez něj aplikace tabulky nemá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platby')) {
            Schema::create('platby', function (Blueprint $table) {
                $table->id();
                // Do adres (návrat, zaplatit) a jako ExternalTransactionID Mo.one – GUID, neuhodnutelné.
                $table->uuid('verejne_id')->unique();
                // Idempotence: jedna rozpracovaná/zaplacená platba na předmět (objednávka:15).
                $table->string('klic', 190)->nullable()->index();
                $table->nullableMorphs('predmet');
                $table->string('brana', 20);
                $table->string('rezim', 10);
                $table->string('stav', 20)->index();
                $table->unsignedBigInteger('castka');            // haléře
                $table->unsignedBigInteger('vraceno')->default(0);
                $table->char('mena', 3)->default('CZK');
                $table->string('popis', 190);
                $table->string('reference', 64)->nullable()->index();   // číslo objednávky pro člověka
                $table->string('email', 190)->nullable();
                $table->string('jmeno', 80)->nullable();
                $table->string('prijmeni', 80)->nullable();
                $table->string('externi_id', 100)->nullable();   // transId / PublicID u brány
                $table->text('presmerovani_url')->nullable();
                $table->text('navrat_url')->nullable();          // kam po platbě (stránka objednávky)
                $table->string('metoda', 60)->nullable();
                $table->string('chyba', 500)->nullable();
                $table->timestamp('zaplaceno_v')->nullable();
                $table->timestamp('overeno_v')->nullable();
                $table->timestamps();

                // Testovací a ostrá brána mají každá svoje čísla – unikátní jen v rámci režimu.
                $table->unique(['brana', 'rezim', 'externi_id']);
                $table->index(['stav', 'created_at']);
            });
        }

        if (! Schema::hasTable('platby_udalosti')) {
            Schema::create('platby_udalosti', function (Blueprint $table) {
                $table->id();
                $table->foreignId('platba_id')->constrained('platby')->cascadeOnDelete();
                $table->string('stav_z', 20)->nullable();
                $table->string('stav_na', 20)->nullable();
                $table->string('zdroj', 20);   // zalozeni, navrat, webhook, overeni, planovac, administrace, simulace
                $table->string('poznamka', 500)->nullable();
                $table->json('data')->nullable();   // odpověď brány bez tajemství
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_adresa', 45)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platby_udalosti');
        Schema::dropIfExists('platby');
    }
};
