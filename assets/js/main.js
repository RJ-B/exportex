/* ==========================================================================
   Exportex — chování stránky
   Vanilla JS, žádné závislosti. Běží jako statický soubor (GitHub Pages).
   ========================================================================== */
(function () {
  'use strict';

  var DATA = window.EXPORTEX_DATA;
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var state = {
    lang: 'cs',
    filter: 0,
    corridor: 0,
    modalIdx: null
  };

  /* ---------- helpers ---------- */
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var t = function () { return DATA[state.lang]; };

  /* ======================================================================
     1. Jazyk
     ====================================================================== */
  function applyLang() {
    document.documentElement.lang = state.lang;

    $$('[data-cs]').forEach(function (el) {
      var v = el.getAttribute('data-' + state.lang);
      if (v !== null) el.innerHTML = v;
    });

    $$('.lang__btn').forEach(function (b) {
      b.classList.toggle('is-on', b.dataset.lang === state.lang);
    });

    renderAll();
    try { localStorage.setItem('exportex-lang', state.lang); } catch (e) {}
  }

  function initLang() {
    var saved = null;
    try { saved = localStorage.getItem('exportex-lang'); } catch (e) {}
    // Výchozí jazyk je čeština; angličtina jen když si ji návštěvník zvolí.
    if (saved === 'en' || saved === 'cs') state.lang = saved;
    $$('.lang__btn').forEach(function (b) {
      b.addEventListener('click', function () {
        if (state.lang === b.dataset.lang) return;
        state.lang = b.dataset.lang;
        applyLang();
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
     4. Render — trasa na zeměpisné mapě

     Mapa je vykreslená v souřadnicích, kde platí
        x = 1214.5 + 6.87 · zeměpisná délka
        y = 688.0  − 8.64 · zeměpisná šířka
     Ověřeno proti pobřežím (Bospor, Kypr, Sicílie, břehy Kaspiku),
     takže města stačí zadat zeměpisnými souřadnicemi v data.js.
     ====================================================================== */
  var MAP = { kx: 6.87, bx: 1214.5, ky: 8.64, by: 688.0 };
  var VB  = { x: 1274, y: 234, w: 462, h: 150 };

  function project(lat, lon) {
    return { x: MAP.bx + MAP.kx * lon, y: MAP.by - MAP.ky * lat };
  }

  /* Catmull-Rom → bezier: plynulá křivka procházející přesně všemi zastávkami */
  function smoothPath(pts) {
    if (pts.length < 2) return '';
    var d = 'M' + pts[0].x.toFixed(1) + ' ' + pts[0].y.toFixed(1);
    for (var i = 0; i < pts.length - 1; i++) {
      var p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || pts[i + 1];
      var c1x = p1.x + (p2.x - p0.x) / 6, c1y = p1.y + (p2.y - p0.y) / 6;
      var c2x = p2.x - (p3.x - p1.x) / 6, c2y = p2.y - (p3.y - p1.y) / 6;
      d += 'C' + c1x.toFixed(1) + ' ' + c1y.toFixed(1) + ' ' +
                 c2x.toFixed(1) + ' ' + c2y.toFixed(1) + ' ' +
                 p2.x.toFixed(1) + ' ' + p2.y.toFixed(1);
    }
    return d;
  }

  function renderCorridors() {
    var box = $('#corridors');
    box.innerHTML = t().corridors.map(function (label, i) {
      return '<button type="button" class="chip' + (i === state.corridor ? ' is-on' : '') +
             '" data-i="' + i + '" aria-pressed="' + (i === state.corridor) + '">' + esc(label) + '</button>';
    }).join('');
    $$('.chip', box).forEach(function (c) {
      c.addEventListener('click', function () {
        state.corridor = +c.dataset.i;
        renderCorridors();
        renderRoute();
      });
    });
  }

  var SVGNS = 'http://www.w3.org/2000/svg';

  function renderRoute() {
    var route = t().routes[state.corridor];
    var nodes = route.nodes;
    var n = nodes.length;
    var pts = nodes.map(function (nd) { return project(nd[2], nd[3]); });
    var d = smoothPath(pts);

    /* trasa */
    $('#routeLayer').innerHTML =
      '<path class="map__track" id="routeTrack" d="' + d + '"></path>' +
      '<path class="map__flow" d="' + d + '" marker-end="url(#routeArrow)"></path>';

    /* uzly */
    $('#nodeLayer').innerHTML = pts.map(function (p, i) {
      var end = (i === 0 || i === n - 1);
      return '<circle class="map__halo" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) +
             '" r="7" style="animation-delay:' + (i * 240) + 'ms"></circle>' +
             '<circle class="map__dot ' + (end ? 'map__dot--end' : 'map__dot--mid') +
             '" cx="' + p.x.toFixed(1) + '" cy="' + p.y.toFixed(1) + '" r="' + (end ? 2.6 : 1.9) + '"></circle>';
    }).join('');

    /* popisky — u okrajů se zarovnají dovnitř, u horní hrany spadnou pod uzel */
    $('#labelLayer').innerHTML = pts.map(function (p, i) {
      var anchor = 'middle', dx = 0;
      if (p.x < VB.x + 58) { anchor = 'start'; dx = 5; }
      else if (p.x > VB.x + VB.w - 58) { anchor = 'end'; dx = -5; }
      var below = p.y < VB.y + 21;
      var y1 = below ? p.y + 10.5 : p.y - 9.5;
      var y2 = below ? p.y + 15.5 : p.y - 4.5;
      return '<text class="map__city" x="' + (p.x + dx).toFixed(1) + '" y="' + y1.toFixed(1) +
             '" text-anchor="' + anchor + '">' + esc(nodes[i][0]) + '</text>' +
             '<text class="map__sub" x="' + (p.x + dx).toFixed(1) + '" y="' + y2.toFixed(1) +
             '" text-anchor="' + anchor + '">' + esc(nodes[i][1]) + '</text>';
    }).join('');

    /* Štítek se clem sedí uprostřed nejdelšího úseku a je odsazený kolmo od
       trasy — tam nikdy nepadne na uzel ani na jeho popisek. */
    var duty = route.facts[route.facts.length - 1];
    var track = $('#routeTrack');
    var seg = 0, best = -1;
    for (var k = 0; k < n - 1; k++) {
      var len2 = Math.pow(pts[k + 1].x - pts[k].x, 2) + Math.pow(pts[k + 1].y - pts[k].y, 2);
      if (len2 > best) { best = len2; seg = k; }
    }
    var a = pts[seg], b = pts[seg + 1];
    var vx = b.x - a.x, vy = b.y - a.y, vl = Math.hypot(vx, vy) || 1;
    var nx = -vy / vl, ny = vx / vl;              // kolmice k úseku
    if (ny > 0) { nx = -nx; ny = -ny; }           // vždy tou, co míří nahoru
    var mid = { x: (a.x + b.x) / 2 + nx * 13, y: (a.y + b.y) / 2 + ny * 13 };
    mid.y = Math.max(VB.y + 9, Math.min(VB.y + VB.h - 9, mid.y));

    var label = duty.k + ' ' + duty.v;
    var w = Math.max(34, label.length * 2.35 + 9);
    $('#chipLayer').innerHTML =
      '<g transform="translate(' + mid.x.toFixed(1) + ',' + mid.y.toFixed(1) + ')">' +
        '<rect x="' + (-w / 2).toFixed(1) + '" y="-5.6" width="' + w.toFixed(1) + '" height="11.2" rx="5.6"></rect>' +
        '<text x="0" y="1.6" text-anchor="middle">' + esc(label.toUpperCase()) + '</text>' +
      '</g>';

    /* seznam zastávek pod mapou (a jediná verze na mobilu) */
    $('#routeList').innerHTML = nodes.map(function (nd, i) {
      var end = (i === 0 || i === n - 1);
      return '<li><span class="dot' + (end ? ' dot--end' : '') + '"></span>' +
             '<span class="c">' + esc(nd[0]) + '</span>' +
             '<span class="s">' + esc(nd[1]) + '</span></li>';
    }).join('');

    $('#routeFacts').innerHTML = route.facts.map(function (f) {
      return '<div class="fact"><div class="fact__k">' + esc(f.k) + '</div>' +
             '<div class="fact__v">' + esc(f.v) + '</div></div>';
    }).join('');

    /* trasa se při přepnutí koridoru „nakreslí" */
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
    $('#modalLong').textContent = p.long;
    $('#modalCta').textContent = d.cta;
    $('#modalSpecs').innerHTML = p.specs.map(function (s) {
      return '<div class="spec"><div class="spec__k">' + esc(s.k) + '</div>' +
             '<div class="spec__v">' + esc(s.v) + '</div></div>';
    }).join('');

    $('#modal').classList.add('is-open');
    document.body.classList.add('is-locked');
    $('#modalBox').scrollTop = 0;
    setTimeout(function () { $('.modal__close').focus(); }, 60);
  }

  function closeModal() {
    state.modalIdx = null;
    $('#modal').classList.remove('is-open');
    document.body.classList.remove('is-locked');
    if (lastFocus) lastFocus.focus();
  }

  function initModal() {
    $$('#modal [data-close]').forEach(function (el) {
      el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if ($('#modal').classList.contains('is-open')) closeModal();
        else if ($('#drawer').classList.contains('is-open')) toggleDrawer(false);
      }
    });
  }

  /* ======================================================================
     6. Formulář — validace a UI stavy.
        Odesílací logika záměrně neřešena (viz README → Formulář).
     ====================================================================== */
  var form = { values: {}, errors: {}, touched: {}, sent: false };

  var LBL = {
    cs: { msg: 'Vaše poptávka', msgPh: 'Sortiment, množství, gramáž, termín…', submit: 'Odeslat poptávku',
          note: 'ODPOVÍDÁME DO 1 PRACOVNÍHO DNE', doneT: 'Poptávka odeslána',
          doneB: 'Ozveme se do jednoho pracovního dne. Pokud spěcháte, volejte +420 734 479 684.',
          again: 'Odeslat další' },
    en: { msg: 'Your enquiry', msgPh: 'Product, quantity, weight, deadline…', submit: 'Send enquiry',
          note: 'WE REPLY WITHIN ONE WORKING DAY', doneT: 'Enquiry sent',
          doneB: 'We will get back to you within one working day. If it is urgent, call +420 734 479 684.',
          again: 'Send another' }
  };

  function validate(id, v) {
    var d = t();
    if (id === 'phone') return '';
    if (!v || !v.trim()) return d.err.req;
    if (id === 'email' && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v.trim())) return d.err.email;
    if (id === 'message' && v.trim().length < 8) return d.err.short;
    return '';
  }

  function renderForm() {
    var d = t();
    var L = LBL[state.lang];
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
        form = { values: {}, errors: {}, touched: {}, sent: false };
        renderForm();
      });
      return;
    }

    var fields = d.fields.map(function (f) {
      return field(f.id, f.label, f.type, f.ph, false);
    }).join('');

    box.innerHTML = fields +
      field('message', L.msg, 'textarea', L.msgPh, true) +
      '<button type="submit" class="btn btn--primary btn--lg btn--block">' + esc(L.submit) + '</button>' +
      '<p class="form__note">' + esc(L.note) + '</p>';

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
    var ctrl = isArea
      ? '<textarea name="' + id + '" placeholder="' + esc(ph) + '" rows="4">' + esc(v) + '</textarea>'
      : '<input type="' + type + '" name="' + id + '" value="' + esc(v) + '" placeholder="' + esc(ph) + '">';
    return '<label class="field' + (err ? ' has-err' : '') + '" data-f="' + id + '">' +
             '<span class="field__top"><span>' + esc(label) + '</span>' +
             '<span class="field__err">' + esc(err) + '</span></span>' + ctrl +
           '</label>';
  }

  function setError(id, msg) {
    form.errors[id] = msg;
    var el = $('#formFields [data-f="' + id + '"]');
    if (!el) return;
    el.classList.toggle('has-err', !!msg);
    $('.field__err', el).textContent = msg;
  }

  function initForm() {
    $('#form').addEventListener('submit', function (e) {
      e.preventDefault();
      var ids = ['name', 'company', 'email', 'phone', 'message'];
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

      /* TODO — napojení na odesílací službu (Formspree / Web3Forms / vlastní endpoint).
         Zatím jen zobrazíme potvrzovací stav; nikam se nic neodesílá. */
      form.sent = true;
      renderForm();
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

      $('#hdr').classList.toggle('is-stuck', y > 40);
      $('#progress').style.width = (h > 0 ? Math.min(1, y / h) * 100 : 0).toFixed(2) + '%';

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
    $('#burger').addEventListener('click', function () { toggleDrawer(); });

    // klik do prázdna v mobilním menu ho zavře
    $('#drawer').addEventListener('click', function (e) {
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
      if ($('#drawer').classList.contains('is-open')) toggleDrawer(false);
      if ($('#modal').classList.contains('is-open')) closeModal();

      var top = target.getBoundingClientRect().top + (window.scrollY || 0) - 60;
      window.scrollTo({ top: top, behavior: reduced ? 'auto' : 'smooth' });
      history.replaceState(null, '', '#' + id);
    });
  }

  /* ======================================================================
     10. Bootstrap
     ====================================================================== */
  function renderAll() {
    renderFilters();
    renderProducts();
    renderSteps();
    renderCorridors();
    renderRoute();
    renderCompliance();
    renderCases();
    renderForm();
    if (state.modalIdx !== null) openModal(state.modalIdx);
  }

  function init() {
    initLang();
    applyLang();      // renderAll() uvnitř
    initObserver();
    initModal();
    initForm();
    initNav();

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () { observe(document); }, { passive: true });
    onScroll();
    document.body.classList.add('ready');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
