<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Výchozí seeder (`db:seed`, i `--force` na serveru) nezakládá nic.
 *
 * Testovací data patří do vlastního seederu se zábranou `JenLokalne`
 * (vzor `UkazkovaDataSeeder`, lokálně `php artisan db:seed --class=UkazkovaDataSeeder`)
 * a odsud se nevolají – `db:seed --force` na serveru by je jinak založil
 * do provozu. Superadminy nezakládá seeder, ale CRM (`simren:spravce`).
 * Výchozí hodnoty nastavení jsou v `config/nastaveni.php`, ne v databázi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
