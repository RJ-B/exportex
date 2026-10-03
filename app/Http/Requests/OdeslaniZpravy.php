<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validace kontaktního formuláře – jméno a příjmení zvlášť, rozumné délky.
 * Exportex navíc firma (poptávky jsou B2B, povinná jako dřív) a jazyk webu.
 */
class OdeslaniZpravy extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Mezery kolem a e-mail malými písmeny – ať se v administraci dobře hledá. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'jmeno' => trim((string) $this->input('jmeno')),
            'prijmeni' => trim((string) $this->input('prijmeni')),
            'firma' => trim((string) $this->input('firma')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'telefon' => trim((string) $this->input('telefon')) ?: null,
            'zprava' => trim((string) $this->input('zprava')),
            'jazyk' => $this->input('jazyk') === 'en' ? 'en' : 'cs',
        ]);
    }

    public function rules(): array
    {
        return [
            'jmeno' => ['required', 'string', 'max:80'],
            'prijmeni' => ['required', 'string', 'max:80'],
            'firma' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'telefon' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9 ()\/-]{6,}$/'],
            'zprava' => ['required', 'string', 'min:10', 'max:5000'],
            'jazyk' => ['required', 'in:cs,en'],
        ];
    }

    public function messages(): array
    {
        return [
            'jmeno.required' => 'Vyplňte jméno.',
            'prijmeni.required' => 'Vyplňte příjmení.',
            'firma.required' => 'Vyplňte firmu.',
            'email.required' => 'Vyplňte e-mail, ať vám můžeme odpovědět.',
            'email.email' => 'Tohle nevypadá jako e-mailová adresa.',
            'telefon.regex' => 'Telefon může obsahovat jen číslice, mezery a znaky + ( ) / -.',
            'zprava.required' => 'Napište, s čím vám můžeme pomoct.',
            'zprava.min' => 'Zpráva je moc krátká – napište aspoň pár slov.',
            'zprava.max' => 'Zpráva je moc dlouhá (nejvýš 5000 znaků).',
            '*.max' => 'Text je moc dlouhý.',
        ];
    }
}
