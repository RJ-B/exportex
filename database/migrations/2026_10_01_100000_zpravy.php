<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zprávy z kontaktního formuláře na webu (Obsah webu → Kontakt a formulář). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zpravy')) {
            return;
        }

        Schema::create('zpravy', function (Blueprint $table) {
            $table->id();
            $table->string('jmeno', 80);
            $table->string('prijmeni', 80);
            $table->string('email', 190);
            $table->string('telefon', 40)->nullable();
            $table->text('zprava');
            // Ochrana proti zneužití (Ochrana osobních údajů: IP a čas odeslání).
            $table->string('ip_adresa', 45)->nullable();
            $table->timestamp('precteno_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zpravy');
    }
};
