/**
 * Passkey (WebAuthn) pro druhý krok přihlášení – App\Bezpecnost\DruhyKrok.
 *
 * Dialog (Touch ID, Face ID, Windows Hello, telefon přes QR kód) řeší
 * prohlížeč se systémem; tady se jen předávají data mezi serverem
 * (ZarizeniController, přihlašovací stránka) a prohlížečem.
 */
window.simrenPasskeys = (() => {
    // Texty z lang/<jazyk>/js.php (resources/views/jazyky/texty-js.blade.php), docs/jazyky.md.
    var T = function (klic) { return window.simrenText ? window.simrenText(klic) : klic; };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const supported = () => Boolean(
        window.PublicKeyCredential
        && navigator.credentials
        && PublicKeyCredential.parseCreationOptionsFromJSON
        && PublicKeyCredential.parseRequestOptionsFromJSON
    );

    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw Object.assign(new Error(body.message || ('HTTP ' + response.status)), { status: response.status });
        }
        return response.json();
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body),
        });
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw Object.assign(new Error(data.message || ('HTTP ' + response.status)), { status: response.status });
        }
        return response.json();
    }

    async function vytvor(optionsUrl, saveUrl, name) {
        if (!supported()) {
            throw new Error(T('passkeys.tenhle_prohlizec_passkey_neumi'));
        }
        const { options } = await getJson(optionsUrl);
        const credential = await navigator.credentials.create({ publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options) });
        return postJson(saveUrl, { name, credential: credential.toJSON() });
    }

    /** Lidská věta k chybě – zrušený dialog není chyba, jen „zkuste znovu“. */
    function zprava(e) {
        if (e?.name === 'NotAllowedError') return T('passkeys.potvrzeni_bylo_zruseno_nebo_vyprselo');
        if (e?.name === 'InvalidStateError') return T('passkeys.tohle_zarizeni_uz_je_pridane');
        if (e?.name === 'SecurityError') return T('passkeys.na_teto_adrese_passkey_nejde');
        return e?.message || 'Nepovedlo se.';
    }

    return {
        supported,
        zprava,

        /** Odpověď na výzvu k přihlášení (JSON pro server) – nepřihlašuje, ověří až server. */
        async prihlasit(options) {
            const credential = await navigator.credentials.get({ publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options) });
            return JSON.stringify(credential.toJSON());
        },

        /** Přidá passkey tohoto zařízení (Zabezpečení, po hesle). */
        register(name) {
            return vytvor('/ucet/passkey/moznosti', '/ucet/passkey', name);
        },

        /** Spáruje telefon z QR odkazu. */
        pair(token, name) {
            return vytvor('/ucet/parovani/' + encodeURIComponent(token) + '/moznosti', '/ucet/parovani/' + encodeURIComponent(token), name);
        },
    };
})();
