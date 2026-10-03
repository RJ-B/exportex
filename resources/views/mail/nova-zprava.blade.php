Nová poptávka z formuláře na webu {{ \App\Support\ZakladniUdaje::get('nazev') }}.

Od: {{ $zprava->celeJmeno() }} <{{ $zprava->email }}>
@if ($zprava->firma)Firma: {{ $zprava->firma }}
@endif
@if ($zprava->telefon)Telefon: {{ $zprava->telefon }}
@endif
@if ($zprava->jazyk === 'en')Jazyk webu: angličtina – odpovězte prosím anglicky.
@endif
Odesláno: {{ $zprava->created_at->format('j. n. Y H:i') }}

{{ $zprava->zprava }}

---
Odpovědět můžete přímo na tento e-mail. Všechny poptávky jsou i v administraci (Zprávy z webu).
