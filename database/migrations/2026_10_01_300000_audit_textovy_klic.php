<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aktivita musí unést i textový klíč záznamu.
 *
 * `nastaveni` má klíč `klic` (třeba „posta.uzivatel“) a číselný sloupec ho
 * odmítl – změny nastavení se do Aktivity vůbec nezapsaly, jen do logu chyb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('auditable_id', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Zpátky na číslo by textové klíče neprošly – nechává se text.
    }
};
