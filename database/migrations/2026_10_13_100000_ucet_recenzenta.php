<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Účet pro kontrolu obchodu (test@<doména>, App\Support\UcetRecenzenta) –
 * běžný uživatel, který se jako superadmin nepočítá do statistik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'recenzent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('recenzent')->default(false)->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'recenzent')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('recenzent'));
        }
    }
};
