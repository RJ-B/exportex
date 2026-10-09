<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Testovací web (mimo produkci) se neindexuje, produkce se chová jako dřív. */
class TestovaciWebNeindexujeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mimo_produkci_se_web_neindexuje(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_produkce_web_indexuje(): void
    {
        $this->app['env'] = 'production';

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }
}
