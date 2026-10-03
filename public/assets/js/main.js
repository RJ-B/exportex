/* ==========================================================================
   Exportex — chování stránky
   Vanilla JS, žádné závislosti. Statický soubor v public/, stránky skládá
   Laravel (šablona Sim&Ren). Obsah sortimentu, kroků, trasy, dokladů a ukázek
   je v <script type="application/json" id="exportex-data"> (dřív data.js) –
   upravuje se v administraci, Obsah webu. Podstránky (cookies, ochrana údajů)
   ho nemají a vykreslování se pro ně přeskočí.
   ========================================================================== */
(function () {
  'use strict';

  var DATA = (function () {
    var el = document.getElementById('exportex-data');
    try { return el ? JSON.parse(el.textContent) : {}; } catch (e) { return {}; }
  })();
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var state = {
    lang: 'cs',
    theme: 'dark',
    filter: 0,
    modalIdx: null
  };

  /* ---------- helpers ---------- */
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  /* Česká typografie: jednopísmenné předložky a spojky nesmí zůstat viset na
     konci řádku. Váže se pevnou mezerou; v angličtině se nedělá nic. */
  var CZ_PREP = /(^|[\s(„"–—])([kosuvzaiKOSUVZAI])[ \t]+/g;
  function czTypo(str) {
    return String(str).replace(CZ_PREP, '$1$2\u00A0');
  }

  var esc = function (s) {
    s = String(s);
    if (state.lang === 'cs') s = czTypo(s);
    return s.replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var t = function () { return DATA[state.lang]; };
  /* Kontakty do hlášek formuláře ({email}, {telefon}, {ochrana}) ze Základních údajů. */
  var fill = function (s) {
    var k = DATA.kontakt || {};
    return String(s).replace(/\{(email|telefon|ochrana)\}/g, function (m, key) { return k[key] || ''; });
  };

  /* ======================================================================
     0. Světlý / tmavý režim

     Výchozí je světlý; atribut data-theme="light" je rovnou v <html>, takže
     platí i bez JavaScriptu. Systémové nastavení se nepřebírá, aby první
     dojem byl vždy stejný.

     Ukládá se jen to, co návštěvník sám kliknul. Dřív zapisoval localStorage
     i applyTheme() při startu, takže se každému natrvalo uložil ten výchozí
     režim, jaký platil v den jeho první návštěvy — kdo přišel, když byl
     výchozí tmavý, měl ho zamčený i potom, co se výchozí změnil na světlý.
     ====================================================================== */
  var THEME_COLOR = { dark: '#070B16', light: '#F4F6FA' };

  /* Klíče mají -v2: hodnoty pod původními názvy si uložil web sám při startu,
     takže o skutečné volbě návštěvníka nic nevypovídají. Novým názvem se
     jednorázově zahodí a od teď se plní jen kliknutím. Stejný klíč čte
     theme-init.js v <head>. */
  var THEME_KEY = 'exportex-theme-v2';
  var LANG_KEY  = 'exportex-lang-v2';

  function applyTheme(mode, animate) {
    state.theme = (mode === 'light') ? 'light' : 'dark';
    var root = document.documentElement;

    if (animate && !reduced) root.classList.add('theme-ready');
    if (state.theme === 'light') root.setAttribute('data-theme', 'light');
    else root.removeAttribute('data-theme');

    var meta = $('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', THEME_COLOR[state.theme]);

    $$('.themebtn').forEach(function (b) {
      b.setAttribute('aria-pressed', String(state.theme === 'light'));
      var label = b.getAttribute('data-' + state.lang + '-label');
      if (label) { b.setAttribute('aria-label', label); b.setAttribute('title', label); }
    });

  }

  function saveTheme() {
    try { localStorage.setItem(THEME_KEY, state.theme); } catch (e) {}
  }

  function initTheme() {
    var saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) {}
    applyTheme(saved === 'dark' ? 'dark' : 'light', false);

    $$('.themebtn').forEach(function (b) {
      b.addEventListener('click', function () {
        applyTheme(state.theme === 'light' ? 'dark' : 'light', true);
        saveTheme();
      });
    });
  }

  /* ======================================================================
     1. Jazyk
     ====================================================================== */
  function applyLang() {
    document.documentElement.lang = state.lang;

    $$('[data-cs]').forEach(function (el) {
      var v = el.getAttribute('data-' + state.lang);
      if (v !== null) el.innerHTML = (state.lang === 'cs') ? czTypo(v) : v;
    });

    /* Delší texty (ochrana údajů, cookies) mají každý jazyk ve vlastním bloku. */
    $$('[data-jazyk]').forEach(function (el) {
      el.hidden = el.getAttribute('data-jazyk') !== state.lang;
    });

    $$('.lang__btn').forEach(function (b) {
      b.classList.toggle('is-on', b.dataset.lang === state.lang);
    });

    $$('.themebtn').forEach(function (b) {
      var label = b.getAttribute('data-' + state.lang + '-label');
      if (label) { b.setAttribute('aria-label', label); b.setAttribute('title', label); }
    });

    $$('.lang__btn').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.dataset.lang === state.lang));
    });

    renderAll();
  }

  function saveLang() {
    try { localStorage.setItem(LANG_KEY, state.lang); } catch (e) {}
  }

  /* Angličtina má vlastní adresu (?lang=en), aby na ni mohl mířit hreflang
     a vyhledávače ji indexovaly zvlášť. Čeština je výchozí, tedy bez parametru. */
  function syncLangUrl() {
    if (!history.replaceState) return;
    var u = new URL(location.href);
    if (state.lang === 'en') u.searchParams.set('lang', 'en');
    else u.searchParams.delete('lang');
    history.replaceState(null, '', u.pathname + u.search + u.hash);
  }

  function initLang() {
    var saved = null;
    try { saved = localStorage.getItem(LANG_KEY); } catch (e) {}
    /* Pořadí: adresa (kvůli sdíleným odkazům a vyhledávačům) > uložená volba
       > čeština. ?lang=en se záměrně neukládá — sdílený odkaz má ukázat
       angličtinu jednou, ne přepsat návštěvníkovi nastavení natrvalo. */
    var fromUrl = null;
    try { fromUrl = new URL(location.href).searchParams.get('lang'); } catch (e) {}
    if (fromUrl === 'en' || fromUrl === 'cs') state.lang = fromUrl;
    else if (saved === 'en' || saved === 'cs') state.lang = saved;
    $$('.lang__btn').forEach(function (b) {
      b.addEventListener('click', function () {
        if (state.lang === b.dataset.lang) return;
        state.lang = b.dataset.lang;
        applyLang();
        saveLang();
        syncLangUrl();
      });
    });
  }

  /* ======================================================================
     2. Render — sortiment
     ====================================================================== */
  function renderFilters() {
    var box = $('#filters');
    box.innerHTML = t().filters.map(function (label, i) {
      return '<button type="button" class="chip' + (i === state.filter ? ' is-on' : '') +
             '" data-i="' + i + '" aria-pressed="' + (i === state.filter) + '">' + esc(label) + '</button>';
    }).join('');
    $$('.chip', box).forEach(function (c) {
      c.addEventListener('click', function () {
        state.filter = +c.dataset.i;
        renderFilters();
        renderProducts();
      });
    });
  }

  function renderProducts() {
    var d = t();
    var box = $('#products');
    var list = d.products.filter(function (p) {
      return state.filter === 0 || p.cat === d.filters[state.filter];
    });

    box.innerHTML = list.map(function (p) {
      var idx = d.products.indexOf(p);
      return '' +
      '<button type="button" class="card card--link" data-i="' + idx + '">' +
        '<div class="pcard__media">' +
          '<img src="' + esc(p.img) + '" alt="' + esc(p.title) + '" loading="lazy">' +
          '<span class="pcard__tint"></span>' +
          '<span class="pcard__tag">' + esc(p.tag) + '</span>' +
        '</div>' +
        '<div class="pcard__body">' +
          '<h3>' + esc(p.title) + '</h3>' +
          '<p>' + esc(p.desc) + '</p>' +
          '<div class="pcard__more">' + esc(d.detail) + ' <span>&rarr;</span></div>' +
        '</div>' +
      '</button>';
    }).join('');

    $$('.card--link', box).forEach(function (c, i) {
      c.style.setProperty('--rv-d', (i * 60) + 'ms');
      c.setAttribute('data-rv', '');
      c.addEventListener('click', function () { openModal(+c.dataset.i); });
    });
    observe(box);
  }

  /* ======================================================================
     3. Render — kroky, doklady, reference
     ====================================================================== */
  function renderSteps() {
    $('#steps').innerHTML = t().steps.map(function (s, i) {
      return '<div class="step" data-rv style="--rv-d:' + (i * 55) + 'ms">' +
               '<span class="step__n">' + esc(s.n) + '</span>' +
               '<div><h3>' + esc(s.t) + '</h3><p>' + esc(s.d) + '</p></div>' +
             '</div>';
    }).join('');
    observe($('#steps'));
  }

  function renderCompliance() {
    $('#compliance').innerHTML = t().compliance.map(function (c, i) {
      return '<div class="card card--pad card--compliance" data-rv style="--rv-d:' + (i * 60) + 'ms">' +
               '<span class="badge">' + esc(c.k) + '</span>' +
               '<h3>' + esc(c.t) + '</h3><p>' + esc(c.d) + '</p>' +
             '</div>';
    }).join('');
    observe($('#compliance'));
  }

  function renderCases() {
    $('#cases').innerHTML = t().cases.map(function (c, i) {
      return '<div class="card card--pad card--case" data-rv style="--rv-d:' + (i * 70) + 'ms">' +
               '<span class="badge">' + esc(c.badge) + '</span>' +
               '<h3>' + esc(c.t) + '</h3><p>' + esc(c.d) + '</p>' +
             '</div>';
    }).join('');
    observe($('#cases'));
  }

  /* ======================================================================
     4. Render — mapa Uzbekistán → Evropa

     Podklad mapy je vygenerovaný z Natural Earth (world-atlas 50m) v projekci
        x = 100 + 7.0711 · zeměpisná délka      (válcová, standardní rovnoběžka 45°)
        y = 800 −     10 · zeměpisná šířka
     Mapa nemá mezizastávky — ukazuje původ (Uzbekistán) a cílovou oblast
     (celá Evropa, šipka míří do České republiky uprostřed kontinentu).
     ====================================================================== */
  var MAP = { kx: 7.0711, bx: 100, ky: 10, by: 800 };

  var FROM = { lat: 41.75, lon: 63.60 };   // Uzbekistán
  var TO   = { lat: 49.90, lon: 15.30 };   // Česká republika — střed Evropy

  function project(lat, lon) {
    return { x: MAP.bx + MAP.kx * lon, y: MAP.by - MAP.ky * lat };
  }

  /* Oblouk mezi dvěma body, vyklenutý kolmo k jihu o "bow" jednotek. */
  function arcPath(a, b, bow) {
    var dx = b.x - a.x, dy = b.y - a.y, L = Math.hypot(dx, dy) || 1;
    var nx = -dy / L, ny = dx / L;
    if (ny < 0) { nx = -nx; ny = -ny; }
    return 'M' + a.x.toFixed(1) + ' ' + a.y.toFixed(1) +
           'C' + (a.x + dx * 0.28 + nx * bow).toFixed(1) + ' ' + (a.y + dy * 0.28 + ny * bow).toFixed(1) +
           ' ' + (a.x + dx * 0.72 + nx * bow).toFixed(1) + ' ' + (a.y + dy * 0.72 + ny * bow).toFixed(1) +
           ' ' + b.x.toFixed(1) + ' ' + b.y.toFixed(1);
  }

  function renderRoute() {
    var d = t();
    var route = d.route;
    var a = project(FROM.lat, FROM.lon);
    var b = project(TO.lat, TO.lon);
    var path = arcPath(a, b, route.bow);

    $('#routeLayer').innerHTML =
      '<path class="map__track" id="routeTrack" d="' + path + '"></path>' +
      '<path class="map__flow" d="' + path + '" marker-end="url(#routeArrow)"></path>';

    $('#nodeLayer').innerHTML = [a, b].map(function (p, i) {
      return '<circle class="map__halo" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) +
             '" r="13" style="animation-delay:' + (i * 700) + 'ms"></circle>' +
             '<circle class="map__dot" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) + '" r="4.2"></circle>';
    }).join('');

    /* Popisky: původ pod značkou (nad ní i vlevo vede oblouk), cíl nad ní */
    $('#labelLayer').innerHTML =
      '<text class="map__city" x="' + a.x.toFixed(1) + '" y="' + (a.y + 18).toFixed(1) + '" text-anchor="middle">' + esc(d.mapFrom.city) + '</text>' +
      '<text class="map__sub"  x="' + a.x.toFixed(1) + '" y="' + (a.y + 26).toFixed(1) + '" text-anchor="middle">' + esc(d.mapFrom.sub) + '</text>' +
      '<text class="map__city" x="' + b.x.toFixed(1) + '" y="' + (b.y - 24).toFixed(1) + '" text-anchor="middle">' + esc(d.mapTo.city) + '</text>' +
      '<text class="map__sub"  x="' + b.x.toFixed(1) + '" y="' + (b.y - 16).toFixed(1) + '" text-anchor="middle">' + esc(d.mapTo.sub) + '</text>';

    /* oblouk se při prvním zobrazení nakreslí */
    var track = $('#routeTrack');
    if (!reduced && track.getTotalLength) {
      var len = track.getTotalLength();
      track.style.transition = 'none';
      track.style.strokeDasharray = len;
      track.style.strokeDashoffset = len;
      void track.getBoundingClientRect();
      track.style.transition = 'stroke-dashoffset 1.15s cubic-bezier(.22,1,.36,1)';
      track.style.strokeDashoffset = 0;
    }
  }

  /* ======================================================================
     5. Modal
     ====================================================================== */
  var lastFocus = null;

  function openModal(i) {
    var d = t();
    var p = d.products[i];
    if (!p) return;
    state.modalIdx = i;
    lastFocus = document.activeElement;

    $('#modalTitle').textContent = p.title;
    $('#modalImg').src = p.img;
    $('#modalImg').alt = p.title;
    $('#modalTag').textContent = p.tag;
    $('#modalPoints').innerHTML = p.points.map(function (s) {
      return '<li>' + esc(s) + '</li>';
    }).join('');
    $('#modalCta').textContent = d.cta;
    $('#modalSpecs').innerHTML = p.specs.map(function (s) {
      return '<div class="spec"><div class="spec__k">' + esc(s.k) + '</div>' +
             '<div class="spec__v">' + esc(s.v) + '</div></div>';
    }).join('');
    $('#modalTerms').innerHTML = p.terms
      ? '<div class="terms__v">' + esc(p.terms) + '</div>' +
        '<div class="terms__note">' + esc(d.termsNote) + '</div>'
      : '';

    $('#modal').classList.add('is-open');
    $('#modal').removeAttribute('aria-hidden');
    document.body.classList.add('is-locked');
    $('#modalBox').scrollTop = 0;
    setTimeout(function () { $('.modal__close').focus(); }, 60);
  }

  function closeModal() {
    state.modalIdx = null;
    $('#modal').classList.remove('is-open');
    $('#modal').setAttribute('aria-hidden', 'true');
    document.body.classList.remove('is-locked');
    if (lastFocus) lastFocus.focus();
  }

  /* Focus zůstává uvnitř otevřené vrstvy — jinak by tabulátor odešel na
     stránku pod ní, která je pro uživatele v tu chvíli nedostupná. */
  var FOCUSABLE = 'a[href],button:not([disabled]),input,textarea,select,[tabindex]:not([tabindex="-1"])';

  function trapFocus(e) {
    if (e.key !== 'Tab') return;
    var box = ($('#modal') && $('#modal').classList.contains('is-open')) ? $('#modalBox')
            : ($('#drawer') && $('#drawer').classList.contains('is-open')) ? $('#drawer') : null;
    if (!box) return;
    var items = $$(FOCUSABLE, box).filter(function (el) { return el.offsetParent !== null; });
    if ($('#drawer') && $('#drawer').classList.contains('is-open')) items = items.concat($$('.hdr ' + FOCUSABLE));
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  function initModal() {
    document.addEventListener('keydown', trapFocus);
    if (!$('#modal')) return;
    $$('#modal [data-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if ($('#modal') && $('#modal').classList.contains('is-open')) closeModal();
        else if ($('#drawer') && $('#drawer').classList.contains('is-open')) toggleDrawer(false);
      }
    });
  }

  /* ======================================================================
     6. Formulář — validace, UI stavy a odeslání na náš server (/kontakt,
        KontaktController → Zprávy z webu + e-mail). Dřív formsubmit.co.
     ====================================================================== */
  var form = { values: {}, errors: {}, touched: {}, sent: false, sending: false,
               error: '', hp: false, t0: Date.now(), human: false, lastSent: 0, lastBody: '' };

  /* ----------------------------------------------------------------------
     Ochrana proti robotům. Vrstvená, protože jedno síto vždycky někdo obejde.
     Všechna síta „projdou" naoko úspěšně — robot se tak nedozví, že ho web
     odhalil, a nezkouší to jinak. Zpráva se ale nikam neodešle.
     Žádné captcha: nezdržuje zákazníka a nevolá se kvůli němu cizí server.
     ---------------------------------------------------------------------- */
  var BOT = {
    MIN_FILL_MS: 3000,   // člověk nevyplní pět polí za tři vteřiny
    REPEAT_MS:   45000,  // stejná zpráva znovu = smyčka nebo dvojklik
    MAX_LINKS:   5       // pět a víc odkazů ve zprávě je vzkaz pro roboty
  };

  function botCheck(bodyKey) {
    if (form.hp) return 'past';                                   // vyplněné skryté pole
    if (!form.human) return 'bez interakce';                      // nikdo do formuláře neklikl ani nepsal
    if (Date.now() - form.t0 < BOT.MIN_FILL_MS) return 'příliš rychle';
    if (bodyKey === form.lastBody && Date.now() - form.lastSent < BOT.REPEAT_MS) return 'duplicita';
    var links = (form.values.message || '').match(/https?:\/\/|www\./gi);
    if (links && links.length >= BOT.MAX_LINKS) return 'odkazy';
    return null;
  }

  /* Popisky formuláře jsou v datech webu (t().form); kontakty v hláškách
     doplní fill() ze Základních údajů. Text po odeslání upravuje Obsah webu →
     Kontakt – texty, věta s telefonem se přidá jen s vyplněným telefonem. */
  function labels() {
    var f = t().form || {};
    var k = DATA.kontakt || {};
    return {
      msg: f.msg, msgPh: f.msgPh, submit: f.submit, sending: f.sending,
      failed: fill(f.failed), throttle: fill(f.throttle),
      note: f.note, doneT: f.doneT,
      doneB: f.done + (k.telefon ? ' ' + fill(f.urgent) : ''),
      again: f.again, gdpr: fill(f.gdpr)
    };
  }

  function validate(id, v) {
    var d = t();
    v = v || '';
    if (id === 'phone') return (v.trim() && !/^[+0-9 ()\/-]{6,}$/.test(v.trim())) ? d.err.phone : '';
    if (!v.trim()) return d.err.req;
    if (id === 'email' && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v.trim())) return d.err.email;
    if (id === 'message' && v.trim().length < 10) return d.err.short;
    return '';
  }

  function renderForm() {
    var d = t();
    var L = labels();
    var box = $('#formFields');

    if (form.sent) {
      box.innerHTML =
        '<div class="form__done">' +
          '<span class="tick"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E5544A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"></path></svg></span>' +
          '<b>' + esc(L.doneT) + '</b>' +
          '<p>' + esc(L.doneB) + '</p>' +
          '<button type="button" class="btn btn--glass btn--md" id="formReset">' + esc(L.again) + '</button>' +
        '</div>';
      $('#formReset').addEventListener('click', function () {
        form = { values: {}, errors: {}, touched: {}, sent: false, sending: false, error: '', hp: false,
                 t0: Date.now(), human: form.human, lastSent: form.lastSent, lastBody: form.lastBody };
        renderForm();
      });
      return;
    }

    /* Jméno a příjmení vedle sebe v jednom řádku (jako dřív jedno pole Jméno). */
    var fields = '', pair = [];
    d.fields.forEach(function (f) {
      var html = field(f.id, f.label, f.type, f.ph, false);
      if (f.id === 'name' || f.id === 'surname') {
        pair.push(html);
        if (pair.length === 2) { fields += '<div class="form__pair">' + pair.join('') + '</div>'; pair = []; }
      } else {
        fields += pair.join('') + html; pair = [];
      }
    });
    fields += pair.join('');

    box.innerHTML = fields +
      field('message', L.msg, 'textarea', L.msgPh, true) +
      /* Past na roboty: pole je schované mimo plochu stránky (ne display:none —
         tu část robotů přeskakuje), takže ho skutečný člověk nevidí ani na něj
         netabuje, a nevyplní ho. Popisek zůstává v jednom jazyce záměrně:
         nikdo ho nečte kromě robota. */
      '<div class="hp" aria-hidden="true">' +
        '<label>Nechte prázdné<input type="text" name="' + esc(trapName()) + '" tabindex="-1" autocomplete="off"></label>' +
      '</div>' +
      (form.error ? '<p class="form__error" role="alert">' + esc(form.error) + '</p>' : '') +
      '<button type="submit" class="btn btn--primary btn--lg btn--block"' + (form.sending ? ' disabled' : '') + '>' +
        esc(form.sending ? L.sending : L.submit) + '</button>' +
      '<p class="form__note">' + esc(L.note) + '</p>' +
      '<p class="form__gdpr">' + L.gdpr + '</p>';

    var hp = $('input[name="' + trapName() + '"]', box);
    if (hp) hp.addEventListener('input', function () { form.hp = true; });

    form.t0 = Date.now();

    $$('#formFields input, #formFields textarea').forEach(function (el) {
      el.addEventListener('input', function () {
        form.values[el.name] = el.value;
        if (form.touched[el.name]) setError(el.name, validate(el.name, el.value));
      });
      el.addEventListener('blur', function () {
        form.touched[el.name] = true;
        setError(el.name, validate(el.name, el.value));
      });
    });
  }

  function field(id, label, type, ph, isArea) {
    var v = form.values[id] || '';
    var err = form.errors[id] || '';
    var required = (id !== 'phone');
    var AC = { name:'given-name', surname:'family-name', company:'organization', email:'email', phone:'tel', message:'off' };
    var MAXLEN = { name: 80, surname: 80, company: 120, email: 160, phone: 40, message: 5000 };
    var attrs = ' name="' + id + '" autocomplete="' + AC[id] + '" maxlength="' + MAXLEN[id] + '"' +
                ' aria-describedby="err-' + id + '"' +
                ' aria-invalid="' + (err ? 'true' : 'false') + '"' +
                (required ? ' aria-required="true"' : '');
    var ctrl = isArea
      ? '<textarea' + attrs + ' placeholder="' + esc(ph) + '" rows="4">' + esc(v) + '</textarea>'
      : '<input type="' + type + '"' + attrs + ' value="' + esc(v) + '" placeholder="' + esc(ph) + '">';
    return '<label class="field' + (err ? ' has-err' : '') + '" data-f="' + id + '">' +
             '<span class="field__top"><span>' + esc(label) + '</span>' +
             '<span class="field__err" id="err-' + id + '">' + esc(err) + '</span></span>' + ctrl +
           '</label>';
  }

  function setError(id, msg) {
    form.errors[id] = msg;
    var el = $('#formFields [data-f="' + id + '"]');
    if (!el) return;
    el.classList.toggle('has-err', !!msg);
    $('.field__err', el).textContent = msg;
    var ctrl = $('input, textarea', el);
    if (ctrl) ctrl.setAttribute('aria-invalid', msg ? 'true' : 'false');
  }

  function initForm() {
    if (!$('#form')) return;

    /* Skutečný návštěvník do formuláře klikne nebo do něj píše. Roboti, kteří
       jen odešlou POST, tuhle stopu nezanechají. */
    ['pointerdown', 'keydown', 'input'].forEach(function (ev) {
      $('#form').addEventListener(ev, function () { form.human = true; }, { passive: true });
    });
    $('#form').addEventListener('submit', function (e) {
      e.preventDefault();
      var ids = ['name', 'surname', 'company', 'email', 'phone', 'message'];
      var bad = null;

      ids.forEach(function (id) {
        form.touched[id] = true;
        var msg = validate(id, form.values[id] || '');
        setError(id, msg);
        if (msg && !bad) bad = id;
      });

      if (bad) {
        var el = $('#formFields [data-f="' + bad + '"] input, #formFields [data-f="' + bad + '"] textarea');
        if (el) el.focus();
        return;
      }

      send();
    });
  }

  /* Název skrytého pole (past na roboty) určuje server – OchranaFormulare. */
  function trapName() {
    return ($('#form') && $('#form').getAttribute('data-past')) || 'web_adresa';
  }

  /* Pole formuláře → názvy, které čeká server (OdeslaniZpravy). */
  var SERVER = { name: 'jmeno', surname: 'prijmeni', company: 'firma', email: 'email', phone: 'telefon', message: 'zprava' };

  /* Odeslání na náš server (KontaktController) jako JSON. Ochranu proti
     robotům drží hlavně server (skrytá pole, podepsaný čas zobrazení, limit
     z jedné IP); botCheck() výše jen zbytečně neposílá zjevné pokusy. */
  function send() {
    var el = $('#form');
    var L = labels();
    var d = t();

    var bodyKey = [form.values.email, form.values.message].join('|');
    var caught = botCheck(bodyKey);
    if (caught) {
      form.sent = true; renderForm();
      if (window.console) console.debug('[Exportex] odesílání zastaveno:', caught);
      return;
    }

    form.sending = true; form.error = ''; renderForm();

    var body = {
      jmeno: form.values.name || '', prijmeni: form.values.surname || '',
      firma: form.values.company || '', email: form.values.email || '',
      telefon: form.values.phone || '', zprava: form.values.message || '',
      jazyk: state.lang
    };
    body[trapName()] = form.hp ? 'x' : '';
    body[el.getAttribute('data-cas-pole') || '_zobrazeno'] = el.getAttribute('data-cas') || '';

    fetch(el.getAttribute('action'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json', 'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': el.getAttribute('data-token') || ''
      },
      body: JSON.stringify(body)
    })
    .then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) { return { status: r.status, data: data }; });
    })
    .then(function (res) {
      form.sending = false;

      if (res.status === 200 && res.data && res.data.ok) {
        form.lastBody = bodyKey; form.lastSent = Date.now();
        form.sent = true; renderForm();
        return;
      }

      /* Chyby polí ze serveru – ukážou se u polí v jazyce webu. */
      if (res.status === 422 && res.data && res.data.errors) {
        renderForm();
        var first = null;
        Object.keys(SERVER).forEach(function (id) {
          var msgs = res.data.errors[SERVER[id]];
          if (!msgs) return;
          var msg = id === 'email' ? d.err.email
                  : id === 'phone' ? d.err.phone
                  : id === 'message' && (form.values.message || '').trim().length >= 10 ? d.err.long
                  : id === 'message' && (form.values.message || '').trim() ? d.err.short
                  : (form.values[id] || '').trim() ? d.err.long : d.err.req;
          form.touched[id] = true;
          setError(id, msg);
          if (!first) first = id;
        });
        if (first) {
          var ctrl = $('#formFields [data-f="' + first + '"] input, #formFields [data-f="' + first + '"] textarea');
          if (ctrl) ctrl.focus();
          return;
        }
      }

      form.error = res.status === 429 ? L.throttle : L.failed;
      renderForm();
    })
    .catch(function () {
      form.sending = false; form.error = L.failed; renderForm();
    });
  }

  /* ======================================================================
     7. Scroll reveal
     ====================================================================== */
  var io = null;

  function initObserver() {
    if (reduced || !('IntersectionObserver' in window)) {
      $$('[data-rv]').forEach(function (el) { el.classList.add('in'); });
      return;
    }
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('in');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    observe(document);
  }

  function observe(root) {
    if (reduced || !io) {
      $$('[data-rv]:not(.in)', root === document ? document : root).forEach(function (el) { el.classList.add('in'); });
      return;
    }
    $$('[data-rv]:not(.in)', root).forEach(function (el) {
      var r = el.getBoundingClientRect();
      if (r.top < window.innerHeight * 0.94 && r.bottom > 0) el.classList.add('in');
      else io.observe(el);
    });
  }

  /* ======================================================================
     8. Header, progress, aktivní odkaz
     ====================================================================== */
  var ticking = false;

  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var doc = document.documentElement;
      var y = window.scrollY || doc.scrollTop || 0;
      var h = doc.scrollHeight - window.innerHeight;

      if ($('#hdr')) $('#hdr').classList.toggle('is-stuck', y > 40);
      if ($('#progress')) $('#progress').style.width = (h > 0 ? Math.min(1, y / h) * 100 : 0).toFixed(2) + '%';

      var current = '';
      $$('main section[id]').forEach(function (s) {
        if (s.getBoundingClientRect().top <= 140) current = s.id;
      });
      $$('.nav__link').forEach(function (a) {
        a.classList.toggle('is-active', a.getAttribute('href') === '#' + current);
      });

      ticking = false;
    });
  }

  /* ======================================================================
     9. Mobilní menu + plynulý scroll s odsazením headeru
     ====================================================================== */
  function toggleDrawer(open) {
    var dr = $('#drawer'), bg = $('#burger');
    if (!dr || !bg) return;
    var willOpen = (open === undefined) ? !dr.classList.contains('is-open') : open;
    dr.classList.toggle('is-open', willOpen);
    dr.setAttribute('aria-hidden', String(!willOpen));
    bg.classList.toggle('is-open', willOpen);
    bg.setAttribute('aria-expanded', String(willOpen));
    document.body.classList.toggle('is-locked', willOpen);
    if (willOpen) {
      $$('.drawer__link', dr).forEach(function (a, i) {
        a.style.setProperty('--dr-d', (60 + i * 45) + 'ms');
      });
    }
  }

  function initNav() {
    if ($('#burger')) $('#burger').addEventListener('click', function () { toggleDrawer(); });

    // klik do prázdna v mobilním menu ho zavře
    if ($('#drawer')) $('#drawer').addEventListener('click', function (e) {
      if (e.target === this || e.target.classList.contains('drawer__list')) toggleDrawer(false);
    });

    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href^="#"]');
      if (!a) return;
      var id = a.getAttribute('href').slice(1);
      if (!id) return;
      var target = document.getElementById(id);
      if (!target) return;

      e.preventDefault();
      if ($('#drawer') && $('#drawer').classList.contains('is-open')) toggleDrawer(false);
      if ($('#modal') && $('#modal').classList.contains('is-open')) closeModal();

      var top = target.getBoundingClientRect().top + (window.scrollY || 0) - 60;
      window.scrollTo({ top: top, behavior: reduced ? 'auto' : 'smooth' });
      history.replaceState(null, '', '#' + id);
    });
  }

  /* ======================================================================
     10. Bootstrap
     ====================================================================== */
  /* Podstránky (cookies, ochrana údajů) sdílejí hlavičku, patičku, jazyk
     i motiv, ale nemají data webu — vykreslování se pro ně přeskočí. */
  function renderAll() {
    /* Sekce jde v administraci vypnout – kreslí se jen ty, které na stránce jsou. */
    if (!t()) return;
    if ($('#products')) { renderFilters(); renderProducts(); }
    if ($('#steps')) renderSteps();
    if ($('#routeLayer')) renderRoute();
    if ($('#compliance')) renderCompliance();
    if ($('#cases')) renderCases();
    if ($('#formFields')) renderForm();
    if (state.modalIdx !== null) openModal(state.modalIdx);
  }

  /* Pozice po refreshi.

     Sortiment, kroky i mapa vznikají až z JS, takže ve chvíli, kdy prohlížeč
     obnovuje vlastní scroll, je dokument ještě krátký a pozici ořízne na
     tehdejší konec stránky. Držíme si ji proto sami a vracíme až po vykreslení.
     Adresa s kotvou má přednost — tam návštěvník míří záměrně. */
  var SCROLL_KEY = 'exportex-scroll';

  function saveScroll() {
    try { sessionStorage.setItem(SCROLL_KEY, String(Math.round(window.scrollY || 0))); } catch (e) {}
  }

  function restoreScroll() {
    var y;
    try { y = parseInt(sessionStorage.getItem(SCROLL_KEY), 10); } catch (e) { return; }
    if (!(y > 0)) return;

    /* Písma a fotky dorovnávají výšku ještě chvíli po vykreslení, tak na
       pozici došlapujeme, dokud nesedne — nejdéle vteřinu. Jakmile návštěvník
       sám scrolluje, ustoupíme mu. */
    var stop = false;
    var deadline = Date.now() + 1000;
    var events = ['wheel', 'touchstart', 'keydown'];
    function cancel() { stop = true; }
    events.forEach(function (ev) { window.addEventListener(ev, cancel, { passive: true }); });

    (function step() {
      if (!stop) {
        window.scrollTo(0, y);
        if (Date.now() < deadline && Math.abs((window.scrollY || 0) - y) > 1) {
          requestAnimationFrame(step);
          return;
        }
      }
      events.forEach(function (ev) { window.removeEventListener(ev, cancel); });
    })();
  }

  function initScrollMemory() {
    if (!('sessionStorage' in window)) return;
    try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (e) {}
    window.addEventListener('pagehide', saveScroll);
    window.addEventListener('beforeunload', saveScroll);
    if (!location.hash) restoreScroll();
  }

  function init() {
    initTheme();
    initLang();
    applyLang();      // renderAll() uvnitř
    initObserver();
    initModal();
    initForm();
    initNav();
    initScrollMemory();

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () { observe(document); }, { passive: true });
    onScroll();
    document.body.classList.add('ready');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
