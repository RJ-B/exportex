{{-- Náhled, jak stránku uvidí Google a Facebook (SeoPole::nahled). Vlastní inline styly –
     Tailwind třídy ve vlastních šablonách Filamentu nefungují. Barvy jsou schválně
     „cizí“ (Google, Facebook), aby náhled vypadal jako tam. --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
    <div>
        <p style="font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--gray-500); margin: 0 0 6px;">{{ __('administrace.nahled.google') }}</p>
        <div style="background: #fff; border: 1px solid #dadce0; border-radius: 12px; padding: 14px 16px; font-family: Arial, sans-serif;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <span style="width: 26px; height: 26px; border-radius: 50%; background: #f1f3f4; border: 1px solid #e3e3e3; flex: none;"></span>
                <span style="min-width: 0;">
                    <span style="display: block; font-size: 14px; color: #202124; line-height: 1.3;">{{ $nazevWebu }}</span>
                    <span style="display: block; font-size: 12px; color: #4d5156; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ str_replace(['https://', 'http://'], '', $adresa) }}</span>
                </span>
            </div>
            <div style="font-size: 19px; line-height: 1.3; color: #1a0dab; margin-bottom: 4px;">{{ $titulek }}</div>
            <div style="font-size: 14px; line-height: 1.58; color: #4d5156;">{{ $popis ?: __('administrace.nahled.popis_chybi_google_si_vybere_kus') }}</div>
        </div>
    </div>
    <div>
        <p style="font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--gray-500); margin: 0 0 6px;">{{ __('administrace.nahled.facebook') }}</p>
        <div style="background: #fff; border: 1px solid #dddfe2; border-radius: 12px; overflow: hidden; font-family: Helvetica, Arial, sans-serif;">
            @if ($obrazek)
                <div style="aspect-ratio: 1.91 / 1; background: #f0f2f5 url('{{ $obrazek }}') center / cover no-repeat;"></div>
            @else
                <div style="aspect-ratio: 1.91 / 1; background: #f0f2f5; display: grid; place-items: center; color: #8a8d91; font-size: 13px; padding: 0 16px; text-align: center;">{{ __('administrace.nahled.bez_obrazku_nahraj_obrazek_pro_sdileni') }}</div>
            @endif
            <div style="background: #f0f2f5; padding: 10px 12px; border-top: 1px solid #dddfe2;">
                <div style="font-size: 12px; color: #65676b; text-transform: uppercase;">{{ $domena }}</div>
                <div style="font-size: 16px; font-weight: 600; color: #050505; line-height: 1.3; margin: 2px 0;">{{ $titulek }}</div>
                @if ($popis)
                    <div style="font-size: 14px; color: #65676b; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">{{ $popis }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
