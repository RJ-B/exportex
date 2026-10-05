<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pošta (posta.simren.cz) místo vlastního SMTP:
 *  - posta_odchozi: zprávy, které Pošta zrovna nepřijala (předají se znovu
 *    se stejným Idempotency-Key, obsah šifrovaně, po předání se smažou),
 *  - mail_logs.posta_id: id zprávy v Poště (stav z webhooku / dotazu),
 *    posta_kontrola_at: kdy se naposledy zjišťoval stav.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posta_odchozi')) {
            Schema::create('posta_odchozi', function (Blueprint $table) {
                $table->id();
                $table->string('idempotency_klic', 64)->unique();
                $table->longText('zprava');
                $table->unsignedBigInteger('mail_log_id')->nullable()->index();
                $table->unsignedSmallInteger('pokusu')->default(0);
                $table->timestamp('dalsi_pokus_at')->nullable()->index();
                $table->text('chyba')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('mail_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_logs', 'posta_id')) {
                $table->string('posta_id', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('mail_logs', 'posta_kontrola_at')) {
                $table->timestamp('posta_kontrola_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posta_odchozi');
        Schema::table('mail_logs', fn (Blueprint $table) => $table->dropColumn(['posta_id', 'posta_kontrola_at']));
    }
};
