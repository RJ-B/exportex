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
            'jmeno.required' => __('web.odeslani_zpravy.vyplnte_jmeno'),
            'prijmeni.required' => __('web.odeslani_zpravy.vyplnte_prijmeni'),
            'firma.required' => __('web.odeslani_zpravy.vyplnte_firmu'),
            'email.required' => __('web.odeslani_zpravy.vyplnte_e_mail_at_vam_muzeme'),
            'email.email' => __('web.odeslani_zpravy.tohle_nevypada_jako_e_mailova_adresa'),
            'telefon.regex' => __('web.odeslani_zpravy.telefon_muze_obsahovat_jen_cislice'),
            'zprava.required' => __('web.odeslani_zpravy.napiste_s_cim_vam_muzeme_pomoct'),
            'zprava.min' => __('web.odeslani_zpravy.zprava_je_moc_kratka_napiste_aspon'),
            'zprava.max' => __('web.odeslani_zpravy.zprava_je_moc_dlouha_nejvys_5000'),
            '*.max' => __('web.odeslani_zpravy.text_je_moc_dlouhy'),
        ];
    }
}
