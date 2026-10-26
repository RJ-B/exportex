<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Hlášky Laravelu česky – bez lang/cs se mimo Filament ukazovalo „validation.required“. */
class CestinaTest extends TestCase
{
    public function test_hlasky_validace_jsou_cesky(): void
    {
        $chyby = Validator::make(['email' => 'x', 'jmeno' => ''], ['email' => 'email', 'jmeno' => 'required'])->errors();

        $this->assertSame('Pole jméno musí být vyplněno.', $chyby->first('jmeno'));
        $this->assertSame('E-mail není platný formát.', $chyby->first('email'));
        $this->assertSame('Tyto přihlašovací údaje neodpovídají žádnému záznamu.', __('auth.failed'));
        $this->assertSame('Klíč pro obnovu hesla je nesprávný.', __('passwords.token'));
    }

    public function test_shrnuti_vice_chyb_je_cesky(): void
    {
        // Hláška 422 v API a nad formulářem: první chyba + „(and 2 more errors)“
        // by v aplikaci zůstalo anglicky (klíče jsou v lang/cs.json, ne v validation.php).
        $pravidla = ['jmeno' => 'required', 'prijmeni' => 'required', 'email' => 'required', 'telefon' => 'required'];

        $jedna = Validator::make(['prijmeni' => 'x', 'email' => 'x', 'telefon' => 'x'] + ['jmeno' => ''], $pravidla);
        $dve = Validator::make(['email' => 'x', 'telefon' => 'x'], $pravidla);
        $pet = Validator::make([], $pravidla + ['a' => 'required', 'b' => 'required']);

        $this->assertSame('Pole jméno musí být vyplněno.', (new ValidationException($jedna))->getMessage());
        $this->assertSame('Pole jméno musí být vyplněno. (a ještě 1 chyba)', (new ValidationException($dve))->getMessage());
        $this->assertSame('Pole jméno musí být vyplněno. (a ještě 5 chyb)', (new ValidationException($pet))->getMessage());
    }
}
