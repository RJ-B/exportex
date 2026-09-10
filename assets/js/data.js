/* ==========================================================================
   Exportex — obsahová data (CZ / EN)
   Vše, co se opakuje nebo se přepíná jazykem, je tady na jednom místě.

   OBRÁZKY: reálné fotky z provozů, zpracované do WebP (assets/img/).
   Karty 1200×900 (4:3), sekce O nás 1200×800 (3:2), hero 2400×1350 (16:9).
   Výměna fotky = nahradit soubor stejného jména nebo změnit hodnotu "img".

   MAPA: jeden oblouk Uzbekistán → Evropa, bez zastávek a bez přepínání.
   "bow" je vyklenutí oblouku k jihu ve vnitřních jednotkách mapy.
   ========================================================================== */

window.EXPORTEX_DATA = {

  cs: {
    filters: ['Vše', 'Froté a domácí textil', 'Pletené úplety', 'Konfekce', 'Tkaniny a příze'],
    detail: 'Detail',
    cta: 'Poptat tento sortiment',
    close: 'Zavřít',

    products: [
      {
        tag: 'Froté', cat: 'Froté a domácí textil', img: 'assets/img/product-frotte.webp',
        title: 'Froté — ručníky, župany',
        desc: 'Ručníky, osušky a župany z uzbecké bavlny. Gramáž 400–650 g/m², bordura nebo žakár.',
        points: [
          'Ručníky a osušky 400–600 g/m² — hotelový standard 500–550, 5* a spa 600–650, ekonomy a bazén 400–450',
          'Župany 320–450 g/m²',
          'Provedení: bordura, žakár, velur, proužek',
          'Výšivka a logo podle vaší předlohy',
          'Froté pro veřejný sektor dle ČSN EN 14697 na vyžádání'
        ],
        specs: [{k:'Gramáž',v:'400–650 g/m²'},{k:'Materiál',v:'100% bavlna'},{k:'Barvy',v:'Dle PANTONE'},{k:'Standard',v:'OEKO-TEX 100'}]
      },
      {
        tag: 'Ložní', cat: 'Froté a domácí textil', img: 'assets/img/product-lozni.webp',
        title: 'Ložní prádlo a povlečení',
        desc: 'Povlečení, prostěradla a přehozy. Popelín, saténové tkaniny, hotelové sady.',
        points: [
          '100% bavlna, nebo bavlna/polyester v easy-care úpravě',
          'Povlečení, prostěradla klasická i napínací',
          'Přehozy a ochranné potahy',
          'Zapínání hotelovým přesahem, zipem nebo knoflíky'
        ],
        specs: [{k:'Tkanina',v:'Popelín / satén'},{k:'Gramáž',v:'110–145 g/m²'},{k:'Rozměry',v:'EU i na míru'},{k:'Standard',v:'OEKO-TEX 100'}]
      },
      {
        tag: 'Úplety', cat: 'Pletené úplety', img: 'assets/img/product-uplety.webp',
        title: 'Pletené úplety v metráži',
        desc: 'Single jersey, interlock, rib, French terry. Barvení v kuse i melanže.',
        points: [
          'Single jersey, interlock, rib 1×1 a 2×2, French terry, fleece',
          'Tubulární i otevřená šíře',
          'Sanforizace proti srážení',
          'OEKO-TEX® Standard 100'
        ],
        specs: [{k:'Vazby',v:'Jersey / rib / terry'},{k:'Gramáž',v:'120–360 g/m²'},{k:'Materiál',v:'Bavlna / CVC / elastan'},{k:'Barvení',v:'V kuse / melanž'}]
      },
      {
        tag: 'Konfekce', cat: 'Konfekce', img: 'assets/img/product-konfekce.webp',
        title: 'Konfekce a private label',
        desc: 'Trička, hoodie, tepláky, polo. Šití podle vašeho techpacku a etikety.',
        points: [
          'Trička, polokošile, mikiny hoodie i crew, tepláky a joggery',
          'Od lehkých triček po heavyweight',
          'Šití přesně podle vašeho techpacku',
          'Potisk, výšivka a balení dle zadání',
          'OEKO-TEX® Standard 100'
        ],
        specs: [{k:'Produkty',v:'Tee / hoodie / polo'},{k:'Gramáž',v:'140–360 g/m²'},{k:'Kontrola',v:'AQL + fotodokumentace'},{k:'Značení',v:'Vaše etikety'}]
      },
      {
        tag: 'Tkaniny', cat: 'Tkaniny a příze', img: 'assets/img/product-tkaniny.webp',
        title: 'Tkaniny v metráži',
        desc: 'Popelín, keprovina, kanvas, oxford. Tkalcovny s vlastní přípravnou.',
        points: [
          'Popelín 100–140 g/m², keprovina 180–320 g/m², plátno a kanvas 240–360 g/m², oxford a satén',
          '100% bavlna, bavlna/polyester, strečové kepry s elastanem',
          'Šíře 150, 220, 240 a 280 cm',
          'Režná i barvená v kuse',
          'OEKO-TEX® Standard 100'
        ],
        specs: [{k:'Vazby',v:'Popelín / kepr / kanvas'},{k:'Gramáž',v:'100–360 g/m²'},{k:'Šíře',v:'150–280 cm'},{k:'Úpravy',v:'Sanforizace / EasyCare'}]
      },
      {
        tag: 'Příze', cat: 'Tkaniny a příze', img: 'assets/img/product-prize.webp',
        title: 'Bavlněná příze',
        desc: 'Ring-spun příze z uzbecké bavlny, česaná i mykaná. Režná i barvená.',
        points: [
          'Z uzbecké bavlny, česaná i mykaná, jednoduchá i skaná',
          'Froté a ložní Ne 12–20, úplety a trika Ne 20–40',
          'Barvení v přízi i melanže',
          'Laboratorní protokol: jemnost, zákrut, pevnost, CV %, IPI',
          'Dodávka v kontejnerech, CIF/DAP EU, dovozní clo 0 % (GSP+)'
        ],
        specs: [{k:'Jemnost',v:'Ne 10–40'},{k:'Technologie',v:'Ring-spun'},{k:'Úprava',v:'Režná / bělená / barvená'},{k:'Protokol',v:'Ke každé dávce'}]
      },
      {
        tag: 'HORECA', cat: 'Konfekce', img: 'assets/img/product-horeca.webp',
        title: 'Hotelový a pracovní textil',
        desc: 'Hotelové sady, kuchyňský a pracovní textil s vysokou životností v prádelně.',
        points: [
          'Hotelové froté i ložní sady',
          'Kuchyňský textil — utěrky a zástěry',
          'Pracovní oděvy',
          'Gramáž froté podle třídy hotelu',
          'Rámcové a opakované dodávky'
        ],
        specs: [{k:'Použití',v:'Hotel / gastro'},{k:'Prádelna',v:'60–95 °C'},{k:'Froté',v:'400–650 g/m²'},{k:'Normy',v:'OEKO-TEX / ČSN EN 14697'}]
      }
    ],

    steps: [
      {n:'01', t:'Specifikace',      d:'Sepíšeme přesné zadání — materiál, gramáž, rozměry, barvy, standardy, balení.'},
      {n:'02', t:'Výběr výroby',     d:'Vybereme a prověříme provoz, který zadání skutečně umí — kapacitou i kvalitou.'},
      {n:'03', t:'Vzorek',           d:'Necháme vyrobit vzorek přesně podle specifikace a předložíme ke schválení.'},
      {n:'04', t:'Kontrola kvality', d:'Kvalitu hlídáme přímo ve výrobě a znovu před expedicí, s fotodokumentací a AQL protokolem.'},
      {n:'05', t:'Původ a clo',      d:'Zajistíme doklady o původu, celní odbavení a nulové clo přes GSP+ i celní unii.'},
      {n:'06', t:'Doručení',         d:'Zboží doručíme až do vašeho skladu v EU — jeden partner, jedna faktura.'}
    ],

    // Mapa: jen výchozí a cílová oblast, žádné mezizastávky
    mapFrom: { city: 'Uzbekistán', sub: 'STŘEDNÍ ASIE · VÝROBA' },
    mapTo:   { city: 'Evropa',     sub: 'ROZVOZ KAMKOLIV V EU' },

    route: { bow: 78 },

    compliance: [
      {k:'GSP+',     t:'Nulové clo z Uzbekistánu', d:'Uzbekistán je zařazen do režimu GSP+. Při správně vystaveném dokladu o původu je dovozní clo na textil 0 %.'},
      {k:'EU–TR',    t:'Celní unie s Tureckem',      d:'Zboží dokončené v Turecku vstupuje do EU v rámci celní unie — bez dovozního cla, s dokladem A.TR.'},
      {k:'OEKO-TEX', t:'Standard 100',              d:'Materiály testované na zdravotně závadné látky. OEKO-TEX® Standard 100 u relevantních materiálů; certifikát dodáváme k dodávce.'},
      {k:'REACH',    t:'Soulad s EU legislativou',  d:'Barviva a úpravy v souladu s REACH. U private label řešíme i etiketaci podle nařízení o textilních názvech.'}
    ],

    cases: [
      {badge:'Retail',        t:'Sezónní froté kolekce',    d:'Froté kolekce pro prodejní síť — od techpacku a vzorkování po paletizaci připravenou k distribuci.'},
      {badge:'HORECA',        t:'Hotelové sady na míru',     d:'Froté a ložní sady dimenzované na průmyslovou prádelnu, s rámcovou smlouvou a opakovanými dodávkami.'},
      {badge:'Private label', t:'Konfekce na vlastní značku', d:'Heavyweight konfekce šitá na váš techpack a etikety, s kontrolou AQL a fotodokumentací před expedicí.'}
    ],

    fields: [
      {id:'name',    label:'Jméno',   type:'text',  ph:'Jan Novák'},
      {id:'company', label:'Firma',   type:'text',  ph:'Vaše s.r.o.'},
      {id:'email',   label:'E-mail',  type:'email', ph:'jan@firma.cz'},
      {id:'phone',   label:'Telefon', type:'tel',   ph:'+420 — nepovinné'}
    ],
    err: {req:'Povinné pole', email:'Neplatný e-mail', short:'Napište pár slov'}
  },

  en: {
    filters: ['All', 'Terry & home textiles', 'Knitted fabrics', 'Cut & sew', 'Wovens & yarn'],
    detail: 'Details',
    cta: 'Enquire about this range',
    close: 'Close',

    products: [
      {
        tag: 'Terry', cat: 'Terry & home textiles', img: 'assets/img/product-frotte.webp',
        title: 'Terry — towels and robes',
        desc: 'Towels, bath sheets and robes in Uzbek cotton. 400–650 gsm, border or jacquard.',
        points: [
          'Towels and bath sheets 400–600 gsm — hotel standard 500–550, 5-star and spa 600–650, economy and pool 400–450',
          'Robes 320–450 gsm',
          'Finishes: border, jacquard, velour, stripe',
          'Embroidery and logo to your artwork',
          'Terry to ČSN EN 14697 for the public sector on request'
        ],
        specs: [{k:'Weight',v:'400–650 gsm'},{k:'Material',v:'100% cotton'},{k:'Colours',v:'PANTONE matched'},{k:'Standard',v:'OEKO-TEX 100'}]
      },
      {
        tag: 'Bedding', cat: 'Terry & home textiles', img: 'assets/img/product-lozni.webp',
        title: 'Bed linen and duvet sets',
        desc: 'Duvet covers, sheets and throws. Percale, sateen, hotel sets.',
        points: [
          '100% cotton, or cotton/polyester with an easy-care finish',
          'Duvet covers, flat and fitted sheets',
          'Throws and protectors',
          'Hotel flap, zip or button closures'
        ],
        specs: [{k:'Fabric',v:'Percale / sateen'},{k:'Weight',v:'110–145 gsm'},{k:'Sizes',v:'EU and made to spec'},{k:'Standard',v:'OEKO-TEX 100'}]
      },
      {
        tag: 'Knits', cat: 'Knitted fabrics', img: 'assets/img/product-uplety.webp',
        title: 'Knitted fabric by the roll',
        desc: 'Single jersey, interlock, rib, French terry. Piece dyeing and melange.',
        points: [
          'Single jersey, interlock, 1×1 and 2×2 rib, French terry, fleece',
          'Tubular and open width',
          'Sanforised against shrinkage',
          'OEKO-TEX® Standard 100'
        ],
        specs: [{k:'Structures',v:'Jersey / rib / terry'},{k:'Weight',v:'120–360 gsm'},{k:'Material',v:'Cotton / CVC / elastane'},{k:'Dyeing',v:'Piece / melange'}]
      },
      {
        tag: 'Cut & sew', cat: 'Cut & sew', img: 'assets/img/product-konfekce.webp',
        title: 'Garments and private label',
        desc: 'Tees, hoodies, joggers, polos. Sewn to your techpack and labels.',
        points: [
          'T-shirts, polo shirts, hoodies and crewnecks, sweatpants and joggers',
          'From light tees to heavyweight',
          'Sewn exactly to your techpack',
          'Print, embroidery and packing to your brief',
          'OEKO-TEX® Standard 100'
        ],
        specs: [{k:'Products',v:'Tee / hoodie / polo'},{k:'Weight',v:'140–360 gsm'},{k:'Inspection',v:'AQL + photo records'},{k:'Branding',v:'Your labels'}]
      },
      {
        tag: 'Wovens', cat: 'Wovens & yarn', img: 'assets/img/product-tkaniny.webp',
        title: 'Woven fabric by the metre',
        desc: 'Percale, twill, canvas, oxford. Mills with their own preparation lines.',
        points: [
          'Percale 100–140 gsm, twill 180–320 gsm, plain weave and canvas 240–360 gsm, oxford and sateen',
          '100% cotton, cotton/polyester, stretch twills with elastane',
          'Widths 150, 220, 240 and 280 cm',
          'Greige and piece-dyed',
          'OEKO-TEX® Standard 100'
        ],
        specs: [{k:'Structures',v:'Percale / twill / canvas'},{k:'Weight',v:'100–360 gsm'},{k:'Width',v:'150–280 cm'},{k:'Finishes',v:'Sanforised / EasyCare'}]
      },
      {
        tag: 'Yarn', cat: 'Wovens & yarn', img: 'assets/img/product-prize.webp',
        title: 'Cotton yarn',
        desc: 'Ring-spun yarn from Uzbek cotton, combed and carded. Greige or dyed.',
        points: [
          'From Uzbek cotton, combed and carded, single and plied',
          'Terry and bedding Ne 12–20, knits and tees Ne 20–40',
          'Yarn dyeing and melange',
          'Laboratory report: count, twist, strength, CV%, IPI',
          'Delivered in containers, CIF/DAP EU, 0% import duty (GSP+)'
        ],
        specs: [{k:'Count',v:'Ne 10–40'},{k:'Technology',v:'Ring-spun'},{k:'Finish',v:'Greige / bleached / dyed'},{k:'Report',v:'With every lot'}]
      },
      {
        tag: 'HORECA', cat: 'Cut & sew', img: 'assets/img/product-horeca.webp',
        title: 'Hotel and workwear textiles',
        desc: 'Hotel sets, kitchen and workwear textiles built for industrial laundries.',
        points: [
          'Hotel terry and bed sets',
          'Kitchen textiles — cloths and aprons',
          'Workwear',
          'Terry weight by hotel class',
          'Framework contracts and repeat deliveries'
        ],
        specs: [{k:'Use',v:'Hotel / gastro'},{k:'Laundry',v:'60–95 °C'},{k:'Terry',v:'400–650 gsm'},{k:'Standards',v:'OEKO-TEX / ČSN EN 14697'}]
      }
    ],

    steps: [
      {n:'01', t:'Specification',      d:'We write the exact brief — material, weight, dimensions, colours, standards, packing.'},
      {n:'02', t:'Selecting the mill', d:'We select and vet the plant that can actually deliver the brief — on capacity and on quality.'},
      {n:'03', t:'Sample',             d:'A sample is produced exactly to spec and submitted for your approval.'},
      {n:'04', t:'Quality control',    d:'We control quality in the mill and again before dispatch, with photos and an AQL report.'},
      {n:'05', t:'Origin and duty',    d:'We arrange origin documents, customs clearance and zero duty via GSP+ and the customs union.'},
      {n:'06', t:'Delivery',           d:'Goods arrive at your EU warehouse — one partner, one invoice.'}
    ],

    mapFrom: { city: 'Uzbekistan', sub: 'CENTRAL ASIA · PRODUCTION' },
    mapTo:   { city: 'Europe',     sub: 'DELIVERED ANYWHERE IN THE EU' },

    route: { bow: 78 },

    compliance: [
      {k:'GSP+',     t:'Zero duty from Uzbekistan',  d:'Uzbekistan is included in the GSP+ scheme. With a correctly issued proof of origin, import duty on textiles is 0%.'},
      {k:'EU–TR',    t:'Customs union with Turkey', d:'Goods finished in Turkey enter the EU under the customs union — no import duty, with an A.TR certificate.'},
      {k:'OEKO-TEX', t:'Standard 100',               d:'Materials tested for harmful substances. OEKO-TEX® Standard 100 on the relevant materials; the certificate ships with the goods.'},
      {k:'REACH',    t:'EU legislation compliance',  d:'Dyes and finishes compliant with REACH. For private label we also handle labelling under the textile names regulation.'}
    ],

    cases: [
      {badge:'Retail',        t:'Seasonal terry collection', d:'A terry collection for a retail network — from techpack and sampling to palletisation ready for distribution.'},
      {badge:'HORECA',        t:'Made-to-measure hotel sets', d:'Terry and bed sets specified for industrial laundering, on a framework contract with repeat deliveries.'},
      {badge:'Private label', t:'Garments under your brand',  d:'Heavyweight garments sewn to your techpack and labels, with AQL inspection and photo records before dispatch.'}
    ],

    fields: [
      {id:'name',    label:'Name',    type:'text',  ph:'John Smith'},
      {id:'company', label:'Company', type:'text',  ph:'Your Ltd'},
      {id:'email',   label:'E-mail',  type:'email', ph:'john@company.com'},
      {id:'phone',   label:'Phone',   type:'tel',   ph:'+44 — optional'}
    ],
    err: {req:'Required', email:'Invalid e-mail', short:'Add a few words'}
  }
};
