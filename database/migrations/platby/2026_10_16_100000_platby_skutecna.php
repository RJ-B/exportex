<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doplněk Platby: příznak `skutecna` – platba šla přes OSTROU testovací bránu Sim&Ren
 * (api.znpay.tech), takže se peníze skutečně strhly a vrací se ručně v aplikaci Mo.one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platby') && ! Schema::hasColumn('platby', 'skutecna')) {
            Schema::table('platby', function (Blueprint $table) {
                $table->boolean('skutecna')->default(false)->after('rezim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('platby', 'skutecna')) {
            Schema::table('platby', fn (Blueprint $table) => $table->dropColumn('skutecna'));
        }
    }
};
