{{-- Nastavení hesla z CRM (NastaveniHeslaController). Stejná ve všech aplikacích
     Sim&Ren – přichází se na ni jen z CRM, proto vypadá jako CRM. Bez cizích
     písem a knihoven, styly inline (Tailwind se ve vlastních šablonách nebuildí). --}}
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="color-scheme" content="light dark" />
    <title>Nastavení hesla · {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f1f5f9; --bg2: #e2e8f0; --karta: #ffffff; --okraj: #e2e8f0; --text: #0f172a; --text2: #475569;
            --slabe: #94a3b8; --pole: #f8fafc; --akcent: #0f172a; --akcent-text: #ffffff; --fokus: #64748b;
            --ok: #16a34a; --varovani: #d97706; --chyba: #dc2626; --chyba-bg: #fef2f2;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1120; --bg2: #111827; --karta: #111827; --okraj: #1f2937; --text: #f1f5f9; --text2: #cbd5e1;
                --slabe: #64748b; --pole: #0f172a; --akcent: #e2e8f0; --akcent-text: #0f172a; --fokus: #94a3b8;
                --chyba-bg: rgba(220, 38, 38, .12);
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 32px 16px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text);
            background:
                radial-gradient(1200px 500px at 50% -10%, var(--bg2), transparent 70%),
                linear-gradient(var(--bg), var(--bg));
            -webkit-font-smoothing: antialiased;
        }
        main { width: 100%; max-width: 420px; }
        .znacka { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 22px; font-weight: 600; letter-spacing: -.01em; }
        .znacka b { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--akcent); color: var(--akcent-text); font-size: 17px; }
        .znacka span { font-size: 16px; }
        .znacka small { color: var(--slabe); font-weight: 500; }
        .karta { background: var(--karta); border: 1px solid var(--okraj); border-radius: 18px; padding: 28px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 12px 32px -12px rgba(15, 23, 42, .14); }
        h1 { margin: 0 0 6px; font-size: 22px; letter-spacing: -.02em; }
        .pod { margin: 0 0 22px; color: var(--text2); font-size: 14.5px; line-height: 1.5; }
        .osoba { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--okraj); border-radius: 12px; background: var(--pole); margin-bottom: 22px; }
        .osoba i { width: 38px; height: 38px; flex: none; border-radius: 50%; display: grid; place-items: center; background: var(--akcent); color: var(--akcent-text); font-style: normal; font-weight: 600; font-size: 14px; }
        .osoba div { min-width: 0; }
        .osoba strong { display: block; font-size: 14.5px; }
        .osoba span { display: block; color: var(--text2); font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        label { display: block; font-size: 13.5px; font-weight: 500; margin: 0 0 7px; }
        .pole { position: relative; margin-bottom: 16px; }
        input[type=password], input[type=text] {
            width: 100%; padding: 12px 44px 12px 14px; border-radius: 11px; border: 1px solid var(--okraj); background: var(--pole);
            color: var(--text); font: inherit; font-size: 16px; transition: border-color .15s, box-shadow .15s;
        }
        input:focus { outline: none; border-color: var(--fokus); box-shadow: 0 0 0 4px color-mix(in srgb, var(--fokus) 18%, transparent); }
        .oko { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border: 0; background: none; color: var(--slabe); cursor: pointer; border-radius: 8px; display: grid; place-items: center; }
        .oko:hover { color: var(--text); }
        .sila { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin: -6px 0 6px; }
        .sila span { height: 4px; border-radius: 4px; background: var(--okraj); transition: background .2s; }
        .napoveda { font-size: 12.5px; color: var(--slabe); margin: 0 0 16px; min-height: 16px; }
        button[type=submit] {
            width: 100%; margin-top: 6px; padding: 13px; border: 0; border-radius: 11px; background: var(--akcent); color: var(--akcent-text);
            font: inherit; font-weight: 600; font-size: 15.5px; cursor: pointer; transition: opacity .15s, transform .05s;
        }
        button[type=submit]:hover { opacity: .92; }
        button[type=submit]:active { transform: translateY(1px); }
        button[type=submit]:disabled { opacity: .5; cursor: default; }
        .chyba { background: var(--chyba-bg); color: var(--chyba); border-radius: 11px; padding: 11px 13px; font-size: 14px; margin-bottom: 16px; }
        .neplatny { text-align: center; }
        .neplatny .ikona { width: 52px; height: 52px; margin: 0 auto 14px; border-radius: 50%; display: grid; place-items: center; background: var(--chyba-bg); color: var(--chyba); }
        .pata { text-align: center; color: var(--slabe); font-size: 12.5px; margin-top: 18px; line-height: 1.5; }
    </style>
</head>
<body>
<main>
    <div class="znacka"><b>S</b><span>Simren CRM</span><small>· {{ config('app.name') }}</small></div>

    <div class="karta">
        @if (! $platny)
            <div class="neplatny">
                <div class="ikona">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                </div>
                <h1>Odkaz už neplatí</h1>
                <p class="pod" style="margin-bottom: 0;">Odkaz na nastavení hesla platí jen jednou a omezenou dobu. V CRM u zakázky klikni znovu na <strong>Můj účet správce</strong> – dostaneš nový.</p>
            </div>
        @else
            <h1>Nastav si heslo</h1>
            <p class="pod">Účet správce v aplikaci <strong>{{ config('app.name') }}</strong>. Po uložení tě rovnou přihlásíme.</p>

            <div class="osoba">
                <i>{{ mb_strtoupper(mb_substr($jmeno ?: $email, 0, 1)) }}</i>
                <div>
                    <strong>{{ $jmeno ?: 'Správce' }}</strong>
                    <span>{{ $email }}</span>
                </div>
            </div>

            @if ($errors->any())
                <div class="chyba" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('nastaveni-hesla.ulozit') }}" id="formular">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                <input type="hidden" name="email" value="{{ $email }}" />
                {{-- Pro správce hesel: ke kterému účtu heslo patří. --}}
                <input type="text" name="username" value="{{ $email }}" autocomplete="username" hidden />

                <label for="heslo">Nové heslo</label>
                <div class="pole">
                    <input id="heslo" type="password" name="password" autocomplete="new-password" minlength="10" required autofocus />
                    <button type="button" class="oko" data-pro="heslo" aria-label="Zobrazit heslo">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="sila" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                <p class="napoveda" id="sila-text">Aspoň 10 znaků. Delší a různorodější je bezpečnější.</p>

                <label for="heslo2">Heslo znovu</label>
                <div class="pole">
                    <input id="heslo2" type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required />
                    <button type="button" class="oko" data-pro="heslo2" aria-label="Zobrazit heslo">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <p class="napoveda" id="shoda"></p>

                <button type="submit" id="ulozit">Uložit a přihlásit</button>
            </form>
        @endif
    </div>

    <p class="pata">Odkaz přišel ze Simren CRM a platí jen jednou.<br />Heslo nikdo jiný nezná a nikam se neposílá.</p>
</main>

@if ($platny)
<script>
    (() => {
        const heslo = document.getElementById('heslo');
        const heslo2 = document.getElementById('heslo2');
        const pruhy = document.querySelectorAll('.sila span');
        const silaText = document.getElementById('sila-text');
        const shoda = document.getElementById('shoda');
        const tlacitko = document.getElementById('ulozit');
        const barvy = ['var(--chyba)', 'var(--varovani)', 'var(--varovani)', 'var(--ok)'];
        const popisy = ['Slabé heslo', 'Ujde', 'Dobré heslo', 'Silné heslo'];

        const sila = (h) => {
            if (h.length < 10) return 0;
            let body = 1;
            if (h.length >= 14) body++;
            if (/[a-z]/.test(h) && /[A-Z]/.test(h)) body++;
            if (/\d/.test(h) && /[^A-Za-z0-9]/.test(h)) body++;
            return Math.min(4, body);
        };

        const prekresli = () => {
            const s = sila(heslo.value);
            pruhy.forEach((p, i) => { p.style.background = i < s ? barvy[s - 1] : ''; });
            silaText.textContent = heslo.value.length === 0
                ? 'Aspoň 10 znaků. Delší a různorodější je bezpečnější.'
                : heslo.value.length < 10 ? `Ještě ${10 - heslo.value.length} ${10 - heslo.value.length === 1 ? 'znak' : (10 - heslo.value.length < 5 ? 'znaky' : 'znaků')}` : popisy[s - 1];
            const vyplneno = heslo2.value.length > 0;
            const sedi = heslo.value === heslo2.value;
            shoda.textContent = vyplneno ? (sedi ? 'Hesla se shodují ✓' : 'Hesla se zatím neshodují') : '';
            shoda.style.color = vyplneno ? (sedi ? 'var(--ok)' : 'var(--slabe)') : '';
            tlacitko.disabled = heslo.value.length < 10 || !sedi;
        };

        heslo.addEventListener('input', prekresli);
        heslo2.addEventListener('input', prekresli);
        prekresli();

        document.querySelectorAll('.oko').forEach((b) => b.addEventListener('click', () => {
            const pole = document.getElementById(b.dataset.pro);
            pole.type = pole.type === 'password' ? 'text' : 'password';
            b.setAttribute('aria-label', pole.type === 'password' ? 'Zobrazit heslo' : 'Skrýt heslo');
        }));

        document.getElementById('formular').addEventListener('submit', () => {
            tlacitko.disabled = true;
            tlacitko.textContent = 'Ukládám…';
        });
    })();
</script>
@endif
</body>
</html>
