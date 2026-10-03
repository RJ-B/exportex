<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poptávka z exportex.cz: firma odesílatele (B2B – bez ní poptávka nedává smysl)
 * a jazyk, ve kterém návštěvník web četl (cs / en) – ať se odpoví ve stejném.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zpravy', function (Blueprint $table) {
            if (! Schema::hasColumn('zpravy', 'firma')) {
                $table->string('firma', 120)->nullable()->after('prijmeni');
            }
            if (! Schema::hasColumn('zpravy', 'jazyk')) {
                $table->string('jazyk', 2)->nullable()->after('zprava');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zpravy', function (Blueprint $table) {
            $table->dropColumn(['firma', 'jazyk']);
        });
    }
};
