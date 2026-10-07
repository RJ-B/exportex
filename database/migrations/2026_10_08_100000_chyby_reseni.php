<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chyby aplikace a portál Sim&Ren (simren:chyby, App\Support\ChybyAplikace):
 * poznámka k vyřešení („co se udělalo“) a jméno toho, kdo chybu vyřešil
 * mimo aplikaci (v portálu, Claude) – účet v aplikaci mít nemusí.
 * Opakovatelné (sloupce jen když chybí). Nevkládá data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('error_logs', 'resolution_note')) {
                $table->text('resolution_note')->nullable();
            }

            if (! Schema::hasColumn('error_logs', 'resolved_by_name')) {
                $table->string('resolved_by_name', 160)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('error_logs', function (Blueprint $table) {
            $table->dropColumn(['resolution_note', 'resolved_by_name']);
        });
    }
};
