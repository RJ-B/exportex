<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Co musí projekt ze šablony umět hned po založení – na tom stojí
 * automatické nasazení na test.
 */
class ZakladTest extends TestCase
{
    use RefreshDatabase;

    public function test_kontrola_pro_nasazeni_odpovida(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_prihlaseni_do_administrace_se_zobrazi(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_manifest_pro_portal_existuje(): void
    {
        $manifest = file_get_contents(base_path('.simren/projekt.yml'));

        $this->assertStringContainsString('verze: 1', $manifest);
        $this->assertStringContainsString('kontrola: /zdravi', $manifest);
        // Název a domény vede CRM, ne repo.
        $this->assertStringNotContainsString('domena:', $manifest);
    }
}
