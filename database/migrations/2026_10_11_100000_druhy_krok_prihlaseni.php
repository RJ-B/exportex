<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Druhý krok přihlášení (README → Zabezpečení): passkey, nebo kód
 * z Authenticatoru (TOTP) s obnovovacími kódy.
 *
 *  - users.app_authentication_secret / _recovery_codes: Authenticator
 *    (Filament AppAuthentication, šifrované klíčem aplikace),
 *  - passkeys: passkeye účtu (laravel/passkeys) – Touch ID, Face ID,
 *    Windows Hello, telefon,
 *  - parovani_zarizeni: jednorázové odkazy z QR kódu na spárování telefonu
 *    (v databázi jen otisk tokenu).
 *
 * Opakovatelná (jen co chybí) a bez dat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'app_authentication_secret')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('app_authentication_secret')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'app_authentication_recovery_codes')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('app_authentication_recovery_codes')->nullable();
            });
        }

        if (! Schema::hasTable('passkeys')) {
            Schema::create('passkeys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->string('credential_id')->unique();
                $table->json('credential');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('parovani_zarizeni')) {
            Schema::create('parovani_zarizeni', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_otisk', 64)->unique();
                $table->timestamp('plati_do');
                $table->timestamp('pouzito_v')->nullable();
                $table->timestamp('zruseno_v')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parovani_zarizeni');
        Schema::dropIfExists('passkeys');

        foreach (['app_authentication_secret', 'app_authentication_recovery_codes'] as $sloupec) {
            if (Schema::hasColumn('users', $sloupec)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($sloupec));
            }
        }
    }
};
