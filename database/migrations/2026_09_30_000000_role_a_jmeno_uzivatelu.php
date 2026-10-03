<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role (superadmin > admin > klient) a jméno s příjmením zvlášť.
 *
 * Sloupec `name` z Laravelu zůstává kvůli kompatibilitě, ale nepoužívá se –
 * jméno a příjmení se zvlášť třídí i oslovuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            // Účet správce vzniká bez hesla; nastaví si ho sám odkazem.
            $table->string('password')->nullable()->change();
            $table->string('jmeno')->nullable()->after('name');
            $table->string('prijmeni')->nullable()->after('jmeno');
            $table->string('role', 20)->default('klient')->index()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['jmeno', 'prijmeni', 'role']);
        });
    }
};
