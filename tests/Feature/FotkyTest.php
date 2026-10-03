<?php

namespace Tests\Feature;

use App\Support\Fotky;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fotky se ukládají jako zmenšený WebP; co převést nejde, zůstane jako originál. */
class FotkyTest extends TestCase
{
    public function test_velka_fotka_se_zmensi_na_webp(): void
    {
        Storage::fake('public');

        $cesta = Fotky::uloz(UploadedFile::fake()->image('dovolena.jpg', 4000, 3000), 'fotky');

        $this->assertStringEndsWith('.webp', $cesta);
        [$sirka, $vyska] = getimagesizefromstring(Storage::disk('public')->get($cesta));
        $this->assertSame([1600, 1200], [$sirka, $vyska]);
    }

    public function test_neznamy_format_se_ulozi_jako_original(): void
    {
        Storage::fake('public');

        $cesta = Fotky::uloz(UploadedFile::fake()->createWithContent('iphone.heic', 'neni to obrazek'), 'fotky');

        $this->assertStringEndsWith('.heic', $cesta);
        $this->assertSame('neni to obrazek', Storage::disk('public')->get($cesta));
    }
}
