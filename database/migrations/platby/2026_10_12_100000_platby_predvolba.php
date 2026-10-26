<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doplněk Platby: předvolená metoda (Apple Pay / Google Pay – MetodaPlatby).
 * `metoda` je metoda, kterou zákazník podle brány opravdu zaplatil.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platby') && ! Schema::hasColumn('platby', 'predvolba')) {
            Schema::table('platby', function (Blueprint $table) {
                $table->string('predvolba', 20)->nullable()->after('metoda');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platby', 'predvolba')) {
            Schema::table('platby', fn (Blueprint $table) => $table->dropColumn('predvolba'));
        }
    }
};
