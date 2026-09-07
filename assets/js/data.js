/* ==========================================================================
   Exportex — obsahová data (CZ / EN)
   Vše, co se opakuje nebo se přepíná jazykem, je tady na jednom místě.

   OBRÁZKY: reálné fotky z provozů, zpracované do WebP (assets/img/).
   Karty 1200×900 (4:3), sekce O nás 800×1000 (4:5), hero 2400×1350 (16:9).
   Výměna fotky = nahradit soubor stejného jména nebo změnit hodnotu "img".
   ========================================================================== */

window.EXPORTEX_DATA = {

  cs: {
    filters: ['Vše', 'Frotté a domácí textil', 'Pletené úplety', 'Konfekce', 'Tkaniny'],
    detail: 'Detail',
    cta: 'Poptat tento sortiment',
    close: 'Zavřít',

    products: [
      {
        tag: 'Frotté', cat: 'Frotté a domácí textil', img: 'assets/img/product-frotte.webp',
        title: 'Frotté — ručníky, župany',
        desc: 'Ručníky, osušky a župany z uzbecké bavlny. Gramáž 380–650 g/m², bordura nebo žakár.',
        long: 'Klasické i prémiové frotté z dlouhovláknité uzbecké bavlny. Řešíme gramáž, typ smyčky, borduru, výšivku i balení pro retail. Vzorník barev podle Pantone TCX.',
        specs: [{k:'Gramáž',v:'380–650 g/m²'},{k:'Materiál',v:'100 % bavlna'},{k:'Barvení',v:'Pantone TCX'},{k:'Balení',v:'Retail / bulk'}]
      },
      {
        tag: 'Ložní', cat: 'Frotté a domácí textil', img: 'assets/img/product-lozni.webp',
        title: 'Ložní prádlo a povlečení',
        desc: 'Povlečení, prostěradla a přehozy. Popelín, saténové tkaniny, hotelové sady.',
        long: 'Kompletní ložní sady pro retail i HORECA. Popelín 120–145 g/m², satén do 300 TC. Šití na vaši rozměrovou tabulku, potisk i barvení v kusu.',
        specs: [{k:'Tkanina',v:'Popelín / satén'},{k:'Hustota',v:'do 300 TC'},{k:'Rozměry',v:'Na míru'},{k:'Certifikát',v:'OEKO-TEX 100'}]
      },
      {
        tag: 'Úplety', cat: 'Pletené úplety', img: 'assets/img/product-uplety.webp',
        title: 'Pletené úplety v metráži',
        desc: 'Single jersey, interlock, rib, French terry. Barvení v kusu i melanže.',
        long: 'Kruhové pletárny s vlastním barvením. Single jersey od 120 g/m², interlock, rib 1×1, French terry a fleece. Enzymatická úprava, silikonové zjemnění, peach finish.',
        specs: [{k:'Vazby',v:'Jersey / rib / terry'},{k:'Gramáž',v:'120–330 g/m²'},{k:'Šíře',v:'160–190 cm'},{k:'Barvení',v:'V kusu / melanž'}]
      },
      {
        tag: 'Konfekce', cat: 'Konfekce', img: 'assets/img/product-konfekce.webp',
        title: 'Konfekce a private label',
        desc: 'Trička, hoodie, tepláky, polo. Šití podle vašeho techpacku a etikety.',
        long: 'Cut-and-sew provozy pro basic i heavyweight konfekci. Pracujeme z vašeho techpacku — střihy, sizing, etikety, hangtagy, polybag. Kontrola AQL 2.5 před expedicí.',
        specs: [{k:'Produkty',v:'Tee / hoodie / polo'},{k:'Gramáž',v:'150–420 g/m²'},{k:'Kontrola',v:'AQL 2.5'},{k:'Značení',v:'Vaše etikety'}]
      },
      {
        tag: 'Tkaniny', cat: 'Tkaniny', img: 'assets/img/product-tkaniny.webp',
        title: 'Tkaniny v metráži',
        desc: 'Popelín, keprovina, kanvas, oxford. Tkalcovny s vlastní přípravnou.',
        long: 'Moderní vzduchové tkalcovny — popelín, kepr, kanvas, oxford a pracovní tkaniny. Šíře do 320 cm, směsi s polyesterem, nemačkavé a nešpinivé úpravy.',
        specs: [{k:'Vazby',v:'Popelín / kepr / kanvas'},{k:'Gramáž',v:'110–320 g/m²'},{k:'Šíře',v:'do 320 cm'},{k:'Úpravy',v:'Easy-care / WR'}]
      },
      {
        tag: 'HORECA', cat: 'Konfekce', img: 'assets/img/product-horeca.webp',
        title: 'Hotelový a pracovní textil',
        desc: 'Hotelové sady, kuchyňský a pracovní textil s vysokou životností v prádelně.',
        long: 'Textil dimenzovaný na průmyslové prádelny — vyšší gramáž, stabilita rozměrů, odolnost v chloru. Vhodné pro hotely, wellness, gastro a provozy s vlastní prádelnou.',
        specs: [{k:'Použití',v:'Hotel / gastro'},{k:'Prádelna',v:'Industrial 90 °C'},{k:'Srážlivost',v:'do 3 %'},{k:'Certifikát',v:'OEKO-TEX 100'}]
      }
    ],

    steps: [
      {n:'01', t:'Specifikace',      d:'Sepíšeme přesné zadání — materiál, gramáž, rozměry, barvy, standardy, balení.'},
      {n:'02', t:'Výběr výroby',     d:'Vybereme a prověříme provoz, který zadání skutečně umí — kapacitou i kvalitou.'},
      {n:'03', t:'Vzorek',           d:'Necháme vyrobit vzorek přesně podle specifikace a předložíme ke schválení.'},
      {n:'04', t:'Kontrola kvality', d:'Kvalitu hlídáme přímo v hale a znovu před expedicí, s fotodokumentací a AQL protokolem.'},
      {n:'05', t:'Původ a clo',      d:'Zajistíme doklady o původu, celní odbavení a nulové clo přes GSP+ i celní unii.'},
      {n:'06', t:'Doručení',         d:'Zboží doručíme až do vašeho skladu v EU — jeden partner, jedna faktura.'}
    ],

    corridors: ['Kamion přes Turecko', 'Železnice — Střední koridor'],
    routes: [
      {
        nodes: [['Taškent','UZ · ORIGIN'],['Alat','UZ · KONSOLIDACE'],['Aktau','KZ · KASPIK'],['Mersin','TR · CELNÍ UNIE'],['Praha','CZ · SKLAD']],
        facts: [{k:'Režim',v:'Kamion FTL / LTL'},{k:'Doklady',v:'Původ + A.TR'},{k:'Clo',v:'0 % (EU–TR)'}]
      },
      {
        nodes: [['Taškent','UZ · ORIGIN'],['Aktau','KZ · TRAJEKT'],['Baku','AZ · PŘEKLAD'],['Poti','GE · ČERNÉ MOŘE'],['Constanța','RO · VSTUP EU']],
        facts: [{k:'Režim',v:'Kontejner FCL'},{k:'Doklady',v:'Původ GSP+'},{k:'Clo',v:'0 % (GSP+)'}]
      }
    ],

    compliance: [
      {k:'GSP+',      t:'Nulové clo z Uzbekistánu',   d:'Uzbekistán je zařazen do režimu GSP+. Při správně vystaveném dokladu o původu je dovozní clo na textil 0 %.'},
      {k:'EU–TR',     t:'Celní unie s Türkiye',        d:'Zboží dokončené v Turecku vstupuje do EU v rámci celní unie — bez dovozního cla, s dokladem A.TR.'},
      {k:'OEKO-TEX',  t:'Standard 100',                d:'Materiály testované na zdravotně závadné látky. Certifikát dodáváme k dodávce, ne na vyžádání.'},
      {k:'REACH',     t:'Soulad s EU legislativou',    d:'Barviva a úpravy v souladu s REACH. U private label řešíme i etiketaci podle nařízení o textilních názvech.'}
    ],

    cases: [
      {badge:'Retail',        t:'Retailová síť',        d:'Sezónní kolekce frotté pro prodejní síť — od techpacku a vzorkování po paletizaci připravenou pro distribuční centrum.'},
      {badge:'HORECA',        t:'Hotelová skupina',     d:'Hotelové sady dimenzované na průmyslovou prádelnu, s rámcovou smlouvou a opakovanými dodávkami po celý rok.'},
      {badge:'Private label', t:'Private label značka', d:'Heavyweight konfekce šitá na vlastní techpack a etikety, s kontrolou AQL a fotodokumentací před každou expedicí.'}
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
    filters: ['All', 'Terry & home textiles', 'Knitted fabrics', 'Cut & sew', 'Woven fabrics'],
    detail: 'Details',
    cta: 'Enquire about this range',
    close: 'Close',

    products: [
      {
        tag: 'Terry', cat: 'Terry & home textiles', img: 'assets/img/product-frotte.webp',
        title: 'Terry — towels and robes',
        desc: 'Towels, bath sheets and robes in Uzbek cotton. 380–650 gsm, border or jacquard.',
        long: 'Classic and premium terry from long-staple Uzbek cotton. We handle weight, loop type, border, embroidery and retail packaging. Colour matched to Pantone TCX.',
        specs: [{k:'Weight',v:'380–650 gsm'},{k:'Material',v:'100% cotton'},{k:'Dyeing',v:'Pantone TCX'},{k:'Packing',v:'Retail / bulk'}]
      },
      {
        tag: 'Bedding', cat: 'Terry & home textiles', img: 'assets/img/product-lozni.webp',
        title: 'Bed linen and duvet sets',
        desc: 'Duvet covers, sheets and throws. Percale, sateen, hotel sets.',
        long: 'Complete bed sets for retail and HORECA. Percale 120–145 gsm, sateen up to 300 TC. Sewn to your size chart, printed or piece-dyed.',
        specs: [{k:'Fabric',v:'Percale / sateen'},{k:'Density',v:'up to 300 TC'},{k:'Sizes',v:'Made to spec'},{k:'Certificate',v:'OEKO-TEX 100'}]
      },
      {
        tag: 'Knits', cat: 'Knitted fabrics', img: 'assets/img/product-uplety.webp',
        title: 'Knitted fabric by the roll',
        desc: 'Single jersey, interlock, rib, French terry. Piece dyeing and melange.',
        long: 'Circular knitters with in-house dyeing. Single jersey from 120 gsm, interlock, 1×1 rib, French terry and fleece. Enzyme wash, silicone softening, peach finish.',
        specs: [{k:'Structures',v:'Jersey / rib / terry'},{k:'Weight',v:'120–330 gsm'},{k:'Width',v:'160–190 cm'},{k:'Dyeing',v:'Piece / melange'}]
      },
      {
        tag: 'Cut & sew', cat: 'Cut & sew', img: 'assets/img/product-konfekce.webp',
        title: 'Garments and private label',
        desc: 'Tees, hoodies, joggers, polos. Sewn to your techpack and labels.',
        long: 'Cut-and-sew plants for basic and heavyweight garments. We work from your techpack — patterns, sizing, labels, hangtags, polybags. AQL 2.5 inspection before dispatch.',
        specs: [{k:'Products',v:'Tee / hoodie / polo'},{k:'Weight',v:'150–420 gsm'},{k:'Inspection',v:'AQL 2.5'},{k:'Branding',v:'Your labels'}]
      },
      {
        tag: 'Wovens', cat: 'Woven fabrics', img: 'assets/img/product-tkaniny.webp',
        title: 'Woven fabric by the metre',
        desc: 'Percale, twill, canvas, oxford. Mills with their own preparation lines.',
        long: 'Modern air-jet weaving — percale, twill, canvas, oxford and workwear fabrics. Widths to 320 cm, polyester blends, easy-care and water-repellent finishes.',
        specs: [{k:'Structures',v:'Percale / twill / canvas'},{k:'Weight',v:'110–320 gsm'},{k:'Width',v:'to 320 cm'},{k:'Finishes',v:'Easy-care / WR'}]
      },
      {
        tag: 'HORECA', cat: 'Cut & sew', img: 'assets/img/product-horeca.webp',
        title: 'Hotel and workwear textiles',
        desc: 'Hotel sets, kitchen and workwear textiles built for industrial laundries.',
        long: 'Textiles specified for industrial laundering — higher weight, dimensional stability, chlorine resistance. Suited to hotels, wellness, gastro and in-house laundries.',
        specs: [{k:'Use',v:'Hotel / gastro'},{k:'Laundry',v:'Industrial 90 °C'},{k:'Shrinkage',v:'under 3%'},{k:'Certificate',v:'OEKO-TEX 100'}]
      }
    ],

    steps: [
      {n:'01', t:'Specification',      d:'We write the exact brief — material, weight, dimensions, colours, standards, packing.'},
      {n:'02', t:'Selecting the mill', d:'We select and vet the plant that can actually deliver the brief — on capacity and on quality.'},
      {n:'03', t:'Sample',             d:'A sample is produced exactly to spec and submitted for your approval.'},
      {n:'04', t:'Quality control',    d:'We control quality on the floor and again before dispatch, with photos and an AQL report.'},
      {n:'05', t:'Origin and duty',    d:'We arrange origin documents, customs clearance and zero duty via GSP+ and the customs union.'},
      {n:'06', t:'Delivery',           d:'Goods arrive at your EU warehouse — one partner, one invoice.'}
    ],

    corridors: ['Truck via Türkiye', 'Rail — Middle Corridor'],
    routes: [
      {
        nodes: [['Tashkent','UZ · ORIGIN'],['Alat','UZ · CONSOLIDATION'],['Aktau','KZ · CASPIAN'],['Mersin','TR · CUSTOMS UNION'],['Prague','CZ · WAREHOUSE']],
        facts: [{k:'Mode',v:'FTL / LTL truck'},{k:'Documents',v:'Origin + A.TR'},{k:'Duty',v:'0% (EU–TR)'}]
      },
      {
        nodes: [['Tashkent','UZ · ORIGIN'],['Aktau','KZ · FERRY'],['Baku','AZ · TRANSSHIP'],['Poti','GE · BLACK SEA'],['Constanța','RO · EU ENTRY']],
        facts: [{k:'Mode',v:'FCL container'},{k:'Documents',v:'Origin GSP+'},{k:'Duty',v:'0% (GSP+)'}]
      }
    ],

    compliance: [
      {k:'GSP+',     t:'Zero duty from Uzbekistan',  d:'Uzbekistan is included in the GSP+ scheme. With a correctly issued proof of origin, import duty on textiles is 0%.'},
      {k:'EU–TR',    t:'Customs union with Türkiye', d:'Goods finished in Türkiye enter the EU under the customs union — no import duty, with an A.TR certificate.'},
      {k:'OEKO-TEX', t:'Standard 100',               d:'Materials tested for harmful substances. The certificate ships with the goods, not on request.'},
      {k:'REACH',    t:'EU legislation compliance',  d:'Dyes and finishes compliant with REACH. For private label we also handle labelling under the textile names regulation.'}
    ],

    cases: [
      {badge:'Retail',        t:'Retail chain',         d:'Seasonal terry collection for a retail network — from techpack and sampling to palletisation ready for the distribution centre.'},
      {badge:'HORECA',        t:'Hotel group',          d:'Hotel sets specified for industrial laundering, on a framework contract with repeat deliveries through the year.'},
      {badge:'Private label', t:'Private label brand',  d:'Heavyweight garments sewn to their own techpack and labels, with AQL inspection and photo records before every dispatch.'}
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
