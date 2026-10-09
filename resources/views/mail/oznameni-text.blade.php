@if ($zkouska)[Zkouška – takhle oznámení uvidí příjemci. Nikomu jinému neodešlo.]

@endif
{{ $oznameni->titulek }}
{{ str_repeat('=', min(60, mb_strlen($oznameni->titulek))) }}
@if ($oznameni->udalost_od)

Kdy: {{ $oznameni->udalost_od->format('j. n. Y H:i') }}@if ($oznameni->udalost_do) – {{ $oznameni->udalost_do->format('j. n. Y H:i') }}@endif

@endif

{{ $oznameni->textProsty() }}
@if ($odkaz)

{{ $oznameni->odkaz_text ?: 'Zobrazit' }}: {{ $odkaz }}
@endif

---
@if ($odhlaseni)
Dostáváte to, protože jste souhlasili se zasíláním novinek od {{ $nazevWebu }}.
Odhlásit: {{ $odhlaseni }}
@else
Dostáváte to, protože máte účet na {{ $nazevWebu }}.
@endif
@if ($predvolby)
Předvolby oznámení: {{ $predvolby }}
@endif
