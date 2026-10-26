<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `.simren/sablona.yml` – popis šablony pro katalog v CRM (KatalogSablon).
 * CRM neznámé hodnoty tiše zahodí, takže překlep by šablonu z karty
 * nebo z druhu zakázky nenápadně vyřadil. Tady se to chytí hned.
 */
class SablonaKatalogTest extends TestCase
{
    private const DRUHY = ['NA_MIRU', 'PRODUKT', 'INTERNI'];

    private const OBSAHUJE = ['web', 'administrace', 'firmy', 'api', 'mobil'];

    private function hodnota(string $text, string $klic): ?string
    {
        return preg_match('/^'.$klic.':\s*(.+?)\s*$/m', $text, $m) ? $m[1] : null;
    }

    /** @return list<string> položky seznamu [a, b] */
    private function seznam(string $text, string $klic): array
    {
        $h = $this->hodnota($text, $klic);
        $this->assertNotNull($h, "chybí {$klic}");
        $this->assertMatchesRegularExpression('/^\[.*\]$/', $h, "{$klic} má být seznam [a, b]");

        return array_values(array_filter(array_map('trim', explode(',', trim($h, '[]')))));
    }

    public function test_popis_sablony_je_platny(): void
    {
        $text = (string) file_get_contents(base_path('.simren/sablona.yml'));

        $this->assertSame('1', $this->hodnota($text, 'verze'));
        $this->assertNotEmpty($this->hodnota($text, 'nazev'));
        $this->assertNotEmpty($this->hodnota($text, 'popis'));
        $this->assertLessThanOrEqual(300, mb_strlen((string) $this->hodnota($text, 'popis')), 'popis je jedna věta (nejvýš 300 znaků)');
        $this->assertSame([], array_diff($this->seznam($text, 'druhy'), self::DRUHY));
        $this->assertSame([], array_diff($this->seznam($text, 'obsahuje'), self::OBSAHUJE));
        $this->assertContains($this->hodnota($text, 'test'), ['true', 'false']);
        $this->assertContains($this->hodnota($text, 'balicky'), ['true', 'false']);
        // Žádné tabulátory – YAML je nepřečte.
        $this->assertStringNotContainsString("\t", $text);
    }
}
