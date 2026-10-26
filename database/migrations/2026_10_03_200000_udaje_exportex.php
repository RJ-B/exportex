<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Dřív doplňovala výchozí údaje do `nastaveni`. Výchozí hodnoty jsou teď
 * v config/nastaveni.php (pravidlo šablony: čerstvá produkce nesmí „mít data“,
 * jinak portál odmítne přenos z testu). Migrace zůstává jen kvůli historii.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
