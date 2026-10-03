{{-- Ochrana formuláře proti botům (App\Support\OchranaFormulare): skryté pole
     a podepsaný čas zobrazení. Vložit dovnitř <form>. --}}
<div style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;" aria-hidden="true">
    <label for="{{ \App\Support\OchranaFormulare::HONEYPOT }}">Nevyplňujte</label>
    <input type="text" id="{{ \App\Support\OchranaFormulare::HONEYPOT }}" name="{{ \App\Support\OchranaFormulare::HONEYPOT }}" tabindex="-1" autocomplete="off" value="">
</div>
<input type="hidden" name="{{ \App\Support\OchranaFormulare::CAS }}" value="{{ \App\Support\OchranaFormulare::podepsanyCas() }}">
