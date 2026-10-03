<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provozní logy (kanón Sim&Ren, skill `provozni-logy`): aktivita, e-maily,
 * chyby – a jednoduché nastavení klíč–hodnota, kde je retence logů.
 *
 * Bez cizích klíčů na `users`: v ekosystému bývá `users` VIEW do sdílené
 * identity a MariaDB na VIEW klíč nepověsí. Vazba je jen relací na modelu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_role', 32)->nullable();      // role v okamžiku akce
                $table->string('event', 64)->index();             // např. user.updated
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->string('summary')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index(['auditable_type', 'auditable_id']);
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('mail_logs')) {
            Schema::create('mail_logs', function (Blueprint $table) {
                $table->id();
                $table->string('status', 16)->default('sending')->index(); // sending | sent | failed
                $table->string('to_email')->index();
                $table->string('to_name')->nullable();
                $table->json('recipients')->nullable();            // všichni příjemci z obálky, i skryté kopie
                $table->string('subject')->nullable();
                $table->string('mailable')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('attempts')->default(1);
                $table->unsignedBigInteger('retried_by')->nullable(); // null = automatika
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                // Syrový MIME jen u selhaných (kvůli opakování); po úspěchu se maže.
                $table->longText('raw_mime')->nullable();
                $table->timestamps();

                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('error_logs')) {
            Schema::create('error_logs', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint', 64)->unique();       // agregace opakovaných chyb
                $table->string('level', 16)->default('error');
                $table->string('exception');
                $table->text('message');
                $table->string('file')->nullable();
                $table->unsignedInteger('line')->nullable();
                $table->text('trace')->nullable();
                $table->string('url')->nullable();
                $table->string('method', 10)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->unsignedInteger('occurrences')->default(1);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nastaveni')) {
            Schema::create('nastaveni', function (Blueprint $table) {
                $table->string('klic', 100)->primary();
                $table->text('hodnota')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nastaveni');
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('audit_logs');
    }
};
