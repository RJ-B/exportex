{{-- Oznámení e-mailem (App\Mail\OznameniMail). Jednoduché HTML s vloženými styly –
     poštovní programy cizí CSS neberou. Žádné měřicí pixely. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $oznameni->titulek }}</title>
</head>
<body style="margin: 0; padding: 24px 12px; background: #f5f6f8; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #16181c;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px;">
                <tr><td style="padding: 28px 28px 8px;">
                    @if ($zkouska)
                        <p style="margin: 0 0 16px; padding: 8px 12px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 13px;">{!! __('maily.oznameni.zkouska_takhle_oznameni_uvidi_prijemci') !!}</p>
                    @endif
                    <p style="margin: 0 0 6px; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: #6b7280;">{{ $nazevWebu }} · {{ $oznameni->druh->nazev() }}</p>
                    <h1 style="margin: 0 0 16px; font-size: 22px; line-height: 1.3;">{{ $oznameni->titulek }}</h1>
                    @if ($oznameni->udalost_od)
                        <p style="margin: 0 0 16px; padding: 10px 12px; border-radius: 8px; background: #f3f4f6; font-size: 15px;">
                            <strong>{!! __('maily.oznameni.kdy') !!}</strong> {{ $oznameni->udalost_od->format(__('formaty.datum_cas')) }}@if ($oznameni->udalost_do) – {{ $oznameni->udalost_do->isSameDay($oznameni->udalost_od) ? $oznameni->udalost_do->format('H:i') : $oznameni->udalost_do->format(__('formaty.datum_cas')) }}@endif
                        </p>
                    @endif
                    <div style="font-size: 16px; line-height: 1.6;">{{ $oznameni->textHtml() }}</div>
                    @if ($odkaz)
                        <p style="margin: 22px 0 8px;">
                            <a href="{{ $odkaz }}" style="display: inline-block; padding: 11px 20px; border-radius: 8px; background: #2563eb; color: #ffffff; text-decoration: none; font-weight: 600;">{{ $oznameni->odkaz_text ?: 'Zobrazit' }}</a>
                        </p>
                    @endif
                </td></tr>
                <tr><td style="padding: 16px 28px 24px; border-top: 1px solid #e5e7eb; font-size: 12px; line-height: 1.5; color: #6b7280;">
                    @if ($odhlaseni)
                        {!! __('maily.oznameni.dostavate_to_protoze_jste_souhlasili_se', ['oznameni' => $oznameni->druh === \App\Enums\DruhOznameni::Marketing ? __('maily.oznameni.nabidek') : 'novinek', 'nazev_webu' => $nazevWebu]) !!}
                        <a href="{{ $odhlaseni }}" style="color: #6b7280;">{!! __('maily.oznameni.odhlasit_jednim_kliknutim') !!}</a>@if ($predvolby) · <a href="{{ $predvolby }}" style="color: #6b7280;">{!! __('maily.oznameni.predvolby_oznameni') !!}</a>@endif
                    @else
                        {!! __('maily.oznameni.dostavate_to_protoze_mate_ucet_na', ['nazev_webu' => $nazevWebu]) !!}@if ($predvolby) {!! __('maily.oznameni.co_vam_chodi_e_mailem_nastavite') !!} <a href="{{ $predvolby }}" style="color: #6b7280;">{!! __('maily.oznameni.predvolbach_oznameni') !!}</a>.@endif
                    @endif
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
