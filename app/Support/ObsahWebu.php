<?php

namespace App\Support;

use App\Models\Nastaveni;

/**
 * Texty a seznamy sekcí webu Exportex (Obsah webu → Úvod, Sortiment, Jak to
 * funguje, Trasa, Doklady a clo, O nás, Ukázky zakázek, Kontakt – texty
 * a Hlavička a patička). Výchozí obsah je ten, se kterým web vznikl (dřív
 * index.html a assets/js/data.js) – dokud ho správce nezmění, web vypadá
 * přesně jako dřív.
 *
 * Web je dvojjazyčný (CZ / EN, přepínač v hlavičce): každý text má českou
 * podobu v poli `<pole>` a anglickou v `<pole>_en`; u seznamů nese obě každá
 * položka. Každá sekce je v nastavení jeden klíč `obsah.<sekce>` (JSON).
 * Neuložené pole se bere z VYCHOZI, takže nové pole ve výchozím obsahu se na
 * webu objeví i u sekce, kterou už někdo upravil.
 */
class ObsahWebu
{
    /** Verze fotek sortimentu v adrese – při výměně souboru pod stejným jménem se mění (cache prohlížeče). */
    private const V = '?v=20260924';

    public const VYCHOZI = [
        'uvod' => [
            'stitek' => 'Český sourcingový a importní partner',
            'stitek_en' => 'Czech sourcing & import partner',
            'trasa' => 'TAŠKENT — PRAHA',
            'nadpis' => 'Textil z Uzbekistánu a Střední Asie',
            'nadpis_en' => 'Textiles from Uzbekistan and Central Asia',
            'nadpis_zvyrazneny' => 'od výroby až do Vašeho skladu.',
            'nadpis_zvyrazneny_en' => 'from the mill to your warehouse.',
            'text' => 'Vybereme prověřený provoz, ohlídáme kvalitu přímo ve výrobě a zajistíme původ, clo i dopravu. Jeden odpovědný partner v Praze — od specifikace po vykládku.',
            'text_en' => 'We select a vetted plant, control quality in the mill and handle origin, duty and freight. One accountable partner in Prague — from spec to unloading.',
            'tlacitko' => 'Poslat poptávku',
            'tlacitko_en' => 'Send an enquiry',
            'tlacitko_druhe' => 'Prohlédnout sortiment',
            'tlacitko_druhe_en' => 'See what we source',
            'foto' => '/assets/img/hero.webp',
            'foto_popis' => 'Přádelna partnerského provozu',
            'cisla' => [
                ['nazev' => 'Dovozní clo', 'nazev_en' => 'Import duty', 'hodnota' => '0 %', 'hodnota_en' => '0 %', 'podpis' => 'GSP+', 'podpis_en' => 'GSP+'],
                ['nazev' => 'Dodání', 'nazev_en' => 'Delivery', 'hodnota' => 'Do skladu', 'hodnota_en' => 'To your warehouse', 'podpis' => 'jedna faktura', 'podpis_en' => 'one invoice'],
                ['nazev' => 'Certifikace', 'nazev_en' => 'Certification', 'hodnota' => 'OEKO-TEX®', 'hodnota_en' => 'OEKO-TEX®', 'podpis' => 'Standard 100', 'podpis_en' => 'Standard 100'],
                ['nazev' => 'Kontrola kvality', 'nazev_en' => 'Quality control', 'hodnota' => 'Na místě', 'hodnota_en' => 'On site', 'podpis' => 'před expedicí', 'podpis_en' => 'pre-shipment'],
            ],
        ],

        'sortiment' => [
            'stitek' => 'Sortiment',
            'stitek_en' => 'Products',
            'nadpis' => 'Co pro Vás zajistíme a dovezeme.',
            'nadpis_en' => 'What we source and import for you.',
            'popis' => 'Pracujeme s tkalcovnami, pletárnami a šicími provozy v Uzbekistánu a Střední Asii. Sortiment vždy stavíme na Vaši specifikaci, ne na katalog.',
            'popis_en' => 'We work with weaving mills, knitters and cut-and-sew plants across Uzbekistan and Central Asia. The range is always built to your specification, not to a catalogue.',
            'podminky_poznamka' => 'Orientační, upřesníme dle poptávky.',
            'podminky_poznamka_en' => 'Indicative; confirmed per enquiry.',
            'kategorie' => [
                ['nazev' => 'Froté a domácí textil', 'nazev_en' => 'Terry & home textiles'],
                ['nazev' => 'Pletené úplety', 'nazev_en' => 'Knitted fabrics'],
                ['nazev' => 'Konfekce', 'nazev_en' => 'Cut & sew'],
                ['nazev' => 'Tkaniny a příze', 'nazev_en' => 'Wovens & yarn'],
            ],
            'produkty' => [
                [
                    'stitek' => 'Froté', 'stitek_en' => 'Terry', 'kategorie' => 'Froté a domácí textil',
                    'foto' => '/assets/img/product-frotte.webp'.self::V,
                    'nazev' => 'Froté — ručníky, župany', 'nazev_en' => 'Terry — towels and robes',
                    'popis' => 'Ručníky, osušky a župany z uzbecké bavlny. Gramáž 400–650 g/m², bordura nebo žakár.',
                    'popis_en' => 'Towels, bath sheets and robes in Uzbek cotton. 400–650 gsm, border or jacquard.',
                    'body' => [
                        ['cs' => 'Ručníky a osušky 400–600 g/m² — hotelový standard 500–550, 5* a spa 600–650, ekonomy a bazén 400–450', 'en' => 'Towels and bath sheets 400–600 gsm — hotel standard 500–550, 5-star and spa 600–650, economy and pool 400–450'],
                        ['cs' => 'Župany 320–450 g/m²', 'en' => 'Robes 320–450 gsm'],
                        ['cs' => 'Provedení: bordura, žakár, velur, proužek', 'en' => 'Finishes: border, jacquard, velour, stripe'],
                        ['cs' => 'Výšivka a logo podle Vaší předlohy', 'en' => 'Embroidery and logo to your artwork'],
                        ['cs' => 'Froté pro veřejný sektor dle ČSN EN 14697 na vyžádání', 'en' => 'Terry to ČSN EN 14697 for the public sector on request'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Gramáž', 'hodnota' => '400–650 g/m²', 'nazev_en' => 'Weight', 'hodnota_en' => '400–650 gsm'],
                        ['nazev' => 'Materiál', 'hodnota' => '100% bavlna', 'nazev_en' => 'Material', 'hodnota_en' => '100% cotton'],
                        ['nazev' => 'Barvy', 'hodnota' => 'Dle PANTONE', 'nazev_en' => 'Colours', 'hodnota_en' => 'PANTONE matched'],
                        ['nazev' => 'Standard', 'hodnota' => 'OEKO-TEX 100', 'nazev_en' => 'Standard', 'hodnota_en' => 'OEKO-TEX 100'],
                    ],
                    'podminky' => 'MOQ od 500 ks / barva · vzorek 10–14 dní · výroba 4–6 týdnů · FCA/DAP',
                    'podminky_en' => 'MOQ from 500 pcs / colour · sample 10–14 days · production 4–6 weeks · FCA/DAP',
                ],
                [
                    'stitek' => 'Ložní', 'stitek_en' => 'Bedding', 'kategorie' => 'Froté a domácí textil',
                    'foto' => '/assets/img/product-lozni.webp'.self::V,
                    'nazev' => 'Ložní prádlo a povlečení', 'nazev_en' => 'Bed linen and duvet sets',
                    'popis' => 'Povlečení, prostěradla a přehozy. Popelín, saténové tkaniny, hotelové sady.',
                    'popis_en' => 'Duvet covers, sheets and throws. Percale, sateen, hotel sets.',
                    'body' => [
                        ['cs' => '100% bavlna, nebo bavlna/polyester v easy-care úpravě', 'en' => '100% cotton, or cotton/polyester with an easy-care finish'],
                        ['cs' => 'Povlečení, prostěradla klasická i napínací', 'en' => 'Duvet covers, flat and fitted sheets'],
                        ['cs' => 'Přehozy a ochranné potahy', 'en' => 'Throws and protectors'],
                        ['cs' => 'Zapínání hotelovým přesahem, zipem nebo knoflíky', 'en' => 'Hotel flap, zip or button closures'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Tkanina', 'hodnota' => 'Popelín / satén', 'nazev_en' => 'Fabric', 'hodnota_en' => 'Percale / sateen'],
                        ['nazev' => 'Gramáž', 'hodnota' => '110–145 g/m²', 'nazev_en' => 'Weight', 'hodnota_en' => '110–145 gsm'],
                        ['nazev' => 'Rozměry', 'hodnota' => 'EU i na míru', 'nazev_en' => 'Sizes', 'hodnota_en' => 'EU and made to spec'],
                        ['nazev' => 'Standard', 'hodnota' => 'OEKO-TEX 100', 'nazev_en' => 'Standard', 'hodnota_en' => 'OEKO-TEX 100'],
                    ],
                    'podminky' => 'MOQ od 500 sad / design · vzorek 10–14 dní · výroba 4–6 týdnů · FCA/DAP',
                    'podminky_en' => 'MOQ from 500 sets / design · sample 10–14 days · production 4–6 weeks · FCA/DAP',
                ],
                [
                    'stitek' => 'Úplety', 'stitek_en' => 'Knits', 'kategorie' => 'Pletené úplety',
                    'foto' => '/assets/img/product-uplety.webp'.self::V,
                    'nazev' => 'Pletené úplety v metráži', 'nazev_en' => 'Knitted fabric by the roll',
                    'popis' => 'Single jersey, interlock, rib, French terry. Barvení v kuse i melanže.',
                    'popis_en' => 'Single jersey, interlock, rib, French terry. Piece dyeing and melange.',
                    'body' => [
                        ['cs' => 'Single jersey, interlock, rib 1×1 a 2×2, French terry, fleece', 'en' => 'Single jersey, interlock, 1×1 and 2×2 rib, French terry, fleece'],
                        ['cs' => 'Tubulární i otevřená šíře', 'en' => 'Tubular and open width'],
                        ['cs' => 'Sanforizace proti srážení', 'en' => 'Sanforised against shrinkage'],
                        ['cs' => 'OEKO-TEX® Standard 100', 'en' => 'OEKO-TEX® Standard 100'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Vazby', 'hodnota' => 'Jersey / rib / terry', 'nazev_en' => 'Structures', 'hodnota_en' => 'Jersey / rib / terry'],
                        ['nazev' => 'Gramáž', 'hodnota' => '120–360 g/m²', 'nazev_en' => 'Weight', 'hodnota_en' => '120–360 gsm'],
                        ['nazev' => 'Materiál', 'hodnota' => 'Bavlna / CVC / elastan', 'nazev_en' => 'Material', 'hodnota_en' => 'Cotton / CVC / elastane'],
                        ['nazev' => 'Barvení', 'hodnota' => 'V kuse / melanž', 'nazev_en' => 'Dyeing', 'hodnota_en' => 'Piece / melange'],
                    ],
                    'podminky' => 'MOQ od 500 kg / barva · výroba 3–5 týdnů · FCA/DAP',
                    'podminky_en' => 'MOQ from 500 kg / colour · production 3–5 weeks · FCA/DAP',
                ],
                [
                    'stitek' => 'Konfekce', 'stitek_en' => 'Cut & sew', 'kategorie' => 'Konfekce',
                    'foto' => '/assets/img/product-konfekce.webp'.self::V,
                    'nazev' => 'Konfekce a private label', 'nazev_en' => 'Garments and private label',
                    'popis' => 'Trička, hoodie, tepláky, polo. Šití podle Vašeho techpacku a etikety.',
                    'popis_en' => 'Tees, hoodies, joggers, polos. Sewn to your techpack and labels.',
                    'body' => [
                        ['cs' => 'Trička, polokošile, mikiny hoodie i crew, tepláky a joggery', 'en' => 'T-shirts, polo shirts, hoodies and crewnecks, sweatpants and joggers'],
                        ['cs' => 'Od lehkých triček po heavyweight', 'en' => 'From light tees to heavyweight'],
                        ['cs' => 'Šití přesně podle Vašeho techpacku', 'en' => 'Sewn exactly to your techpack'],
                        ['cs' => 'Potisk, výšivka a balení dle zadání', 'en' => 'Print, embroidery and packing to your brief'],
                        ['cs' => 'OEKO-TEX® Standard 100', 'en' => 'OEKO-TEX® Standard 100'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Produkty', 'hodnota' => 'Tee / hoodie / polo', 'nazev_en' => 'Products', 'hodnota_en' => 'Tee / hoodie / polo'],
                        ['nazev' => 'Gramáž', 'hodnota' => '140–360 g/m²', 'nazev_en' => 'Weight', 'hodnota_en' => '140–360 gsm'],
                        ['nazev' => 'Kontrola', 'hodnota' => 'AQL + fotodokumentace', 'nazev_en' => 'Inspection', 'hodnota_en' => 'AQL + photo records'],
                        ['nazev' => 'Značení', 'hodnota' => 'Vaše etikety', 'nazev_en' => 'Branding', 'hodnota_en' => 'Your labels'],
                    ],
                    'podminky' => 'MOQ od 300 ks / střih / barva · vzorek 2–3 týdny · výroba 5–8 týdnů · FCA/DAP',
                    'podminky_en' => 'MOQ from 300 pcs / style / colour · sample 2–3 weeks · production 5–8 weeks · FCA/DAP',
                ],
                [
                    'stitek' => 'Tkaniny', 'stitek_en' => 'Wovens', 'kategorie' => 'Tkaniny a příze',
                    'foto' => '/assets/img/product-tkaniny.webp'.self::V,
                    'nazev' => 'Tkaniny v metráži', 'nazev_en' => 'Woven fabric by the metre',
                    'popis' => 'Popelín, keprovina, kanvas, oxford. Tkalcovny s vlastní přípravnou.',
                    'popis_en' => 'Percale, twill, canvas, oxford. Mills with their own preparation lines.',
                    'body' => [
                        ['cs' => 'Popelín 100–140 g/m², keprovina 180–320 g/m², plátno a kanvas 240–360 g/m², oxford a satén', 'en' => 'Percale 100–140 gsm, twill 180–320 gsm, plain weave and canvas 240–360 gsm, oxford and sateen'],
                        ['cs' => '100% bavlna, bavlna/polyester, strečové kepry s elastanem', 'en' => '100% cotton, cotton/polyester, stretch twills with elastane'],
                        ['cs' => 'Šíře 150, 220, 240 a 280 cm', 'en' => 'Widths 150, 220, 240 and 280 cm'],
                        ['cs' => 'Režná i barvená v kuse', 'en' => 'Greige and piece-dyed'],
                        ['cs' => 'OEKO-TEX® Standard 100', 'en' => 'OEKO-TEX® Standard 100'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Vazby', 'hodnota' => 'Popelín / kepr / kanvas', 'nazev_en' => 'Structures', 'hodnota_en' => 'Percale / twill / canvas'],
                        ['nazev' => 'Gramáž', 'hodnota' => '100–360 g/m²', 'nazev_en' => 'Weight', 'hodnota_en' => '100–360 gsm'],
                        ['nazev' => 'Šíře', 'hodnota' => '150–280 cm', 'nazev_en' => 'Width', 'hodnota_en' => '150–280 cm'],
                        ['nazev' => 'Úpravy', 'hodnota' => 'Sanforizace / EasyCare', 'nazev_en' => 'Finishes', 'hodnota_en' => 'Sanforised / EasyCare'],
                    ],
                    'podminky' => 'MOQ od 3 000 m / design · výroba 4–6 týdnů · FCA/DAP',
                    'podminky_en' => 'MOQ from 3,000 m / design · production 4–6 weeks · FCA/DAP',
                ],
                [
                    'stitek' => 'Příze', 'stitek_en' => 'Yarn', 'kategorie' => 'Tkaniny a příze',
                    'foto' => '/assets/img/product-prize.webp'.self::V,
                    'nazev' => 'Bavlněná příze', 'nazev_en' => 'Cotton yarn',
                    'popis' => 'Ring-spun příze z uzbecké bavlny, česaná i mykaná. Režná i barvená.',
                    'popis_en' => 'Ring-spun yarn from Uzbek cotton, combed and carded. Greige or dyed.',
                    'body' => [
                        ['cs' => 'Z uzbecké bavlny, česaná i mykaná, jednoduchá i skaná', 'en' => 'From Uzbek cotton, combed and carded, single and plied'],
                        ['cs' => 'Froté a ložní Ne 12–20, úplety a trika Ne 20–40', 'en' => 'Terry and bedding Ne 12–20, knits and tees Ne 20–40'],
                        ['cs' => 'Barvení v přízi i melanže', 'en' => 'Yarn dyeing and melange'],
                        ['cs' => 'Laboratorní protokol: jemnost, zákrut, pevnost, CV %, IPI', 'en' => 'Laboratory report: count, twist, strength, CV%, IPI'],
                        ['cs' => 'Dodání CIF/DAP do EU, dovozní clo 0 % (GSP+)', 'en' => 'Delivered CIF/DAP into the EU, 0% import duty (GSP+)'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Jemnost', 'hodnota' => 'Ne 10–40', 'nazev_en' => 'Count', 'hodnota_en' => 'Ne 10–40'],
                        ['nazev' => 'Technologie', 'hodnota' => 'Ring-spun', 'nazev_en' => 'Technology', 'hodnota_en' => 'Ring-spun'],
                        ['nazev' => 'Úprava', 'hodnota' => 'Režná / bělená / barvená', 'nazev_en' => 'Finish', 'hodnota_en' => 'Greige / bleached / dyed'],
                        ['nazev' => 'Protokol', 'hodnota' => 'Ke každé dávce', 'nazev_en' => 'Report', 'hodnota_en' => 'With every lot'],
                    ],
                    'podminky' => 'MOQ od 500 kg / typ · dodání 2–4 týdny · FCA/DAP',
                    'podminky_en' => 'MOQ from 500 kg / type · delivery 2–4 weeks · FCA/DAP',
                ],
                [
                    'stitek' => 'HORECA', 'stitek_en' => 'HORECA', 'kategorie' => 'Konfekce',
                    'foto' => '/assets/img/product-horeca.webp'.self::V,
                    'nazev' => 'Hotelový a pracovní textil', 'nazev_en' => 'Hotel and workwear textiles',
                    'popis' => 'Hotelové sady, kuchyňský a pracovní textil s vysokou životností v prádelně.',
                    'popis_en' => 'Hotel sets, kitchen and workwear textiles built for industrial laundries.',
                    'body' => [
                        ['cs' => 'Hotelové froté i ložní sady', 'en' => 'Hotel terry and bed sets'],
                        ['cs' => 'Kuchyňský textil — utěrky a zástěry', 'en' => 'Kitchen textiles — cloths and aprons'],
                        ['cs' => 'Pracovní oděvy', 'en' => 'Workwear'],
                        ['cs' => 'Gramáž froté podle třídy hotelu', 'en' => 'Terry weight by hotel class'],
                        ['cs' => 'Rámcové a opakované dodávky', 'en' => 'Framework contracts and repeat deliveries'],
                    ],
                    'parametry' => [
                        ['nazev' => 'Použití', 'hodnota' => 'Hotel / gastro', 'nazev_en' => 'Use', 'hodnota_en' => 'Hotel / gastro'],
                        ['nazev' => 'Prádelna', 'hodnota' => '60–95 °C', 'nazev_en' => 'Laundry', 'hodnota_en' => '60–95 °C'],
                        ['nazev' => 'Froté', 'hodnota' => '400–650 g/m²', 'nazev_en' => 'Terry', 'hodnota_en' => '400–650 gsm'],
                        ['nazev' => 'Normy', 'hodnota' => 'OEKO-TEX / ČSN EN 14697', 'nazev_en' => 'Standards', 'hodnota_en' => 'OEKO-TEX / ČSN EN 14697'],
                    ],
                    'podminky' => 'MOQ od 500 ks / položka · výroba 4–6 týdnů · FCA/DAP',
                    'podminky_en' => 'MOQ from 500 pcs / item · production 4–6 weeks · FCA/DAP',
                ],
            ],
        ],

        'jak' => [
            'stitek' => 'Jak to funguje',
            'stitek_en' => 'How it works',
            'nadpis' => 'Šest kroků od poptávky po vykládku.',
            'nadpis_en' => 'Six steps from enquiry to unloading.',
            'kroky' => [
                ['nazev' => 'Specifikace', 'nazev_en' => 'Specification', 'popis' => 'Sepíšeme přesné zadání — materiál, gramáž, rozměry, barvy, standardy, balení.', 'popis_en' => 'We write the exact brief — material, weight, dimensions, colours, standards, packing.'],
                ['nazev' => 'Výběr výroby', 'nazev_en' => 'Selecting the mill', 'popis' => 'Vybereme a prověříme provoz, který zadání skutečně umí — kapacitou i kvalitou.', 'popis_en' => 'We select and vet the plant that can actually deliver the brief — on capacity and on quality.'],
                ['nazev' => 'Vzorek', 'nazev_en' => 'Sample', 'popis' => 'Necháme vyrobit vzorek přesně podle specifikace a předložíme ke schválení (obvykle 10–14 dní).', 'popis_en' => 'A sample is produced exactly to spec and submitted for your approval (typically 10–14 days).'],
                ['nazev' => 'Kontrola kvality', 'nazev_en' => 'Quality control', 'popis' => 'Kvalitu hlídáme přímo ve výrobě a znovu před expedicí, s fotodokumentací a AQL protokolem; výroba obvykle 4–6 týdnů.', 'popis_en' => 'We control quality in the mill and again before dispatch, with photos and an AQL report; production typically 4–6 weeks.'],
                ['nazev' => 'Původ a clo', 'nazev_en' => 'Origin and duty', 'popis' => 'Zajistíme doklady o původu, celní odbavení a nulové clo přes GSP+.', 'popis_en' => 'We arrange origin documents, customs clearance and zero duty via GSP+.'],
                ['nazev' => 'Doručení', 'nazev_en' => 'Delivery', 'popis' => 'Zboží doručíme až do Vašeho skladu v EU.', 'popis_en' => 'Goods arrive at your EU warehouse.'],
            ],
        ],

        'trasa' => [
            'stitek' => 'Trasa',
            'stitek_en' => 'Route',
            'nadpis' => 'Ze Střední Asie do celé Evropy.',
            'nadpis_en' => 'From Central Asia to anywhere in Europe.',
            'popis' => 'Celokamionová přeprava, tranzit obvykle 15–20 dní. Doklady o původu vyřizujeme dopředu, aby zboží nestálo na hranici. Dodáváme od FCA z výroby až po DAP do Vašeho skladu kdekoli v EU.',
            'popis_en' => 'Full-truck transport, transit typically 15–20 days. Proof-of-origin documents are arranged up front so nothing waits at the border. We deliver from FCA at the mill through to DAP at your warehouse anywhere in the EU.',
            'odkud' => 'Uzbekistán',
            'odkud_en' => 'Uzbekistan',
            'odkud_popis' => 'STŘEDNÍ ASIE · VÝROBA',
            'odkud_popis_en' => 'CENTRAL ASIA · PRODUCTION',
            'kam' => 'Evropa',
            'kam_en' => 'Europe',
            'kam_popis' => 'ROZVOZ KAMKOLIV V EU',
            'kam_popis_en' => 'DELIVERED ANYWHERE IN THE EU',
        ],

        'doklady' => [
            'stitek' => 'Doklady a clo',
            'stitek_en' => 'Compliance',
            'nadpis' => 'Nulové clo stojí na správných dokladech o původu.',
            'nadpis_en' => 'Zero duty rests on the right proof of origin.',
            'polozky' => [
                ['stitek' => 'GSP+', 'nazev' => 'Nulové clo z Uzbekistánu', 'nazev_en' => 'Zero duty from Uzbekistan', 'popis' => 'Uzbekistán je zařazen do režimu GSP+. Při správně vystaveném dokladu o původu je dovozní clo na textil 0 %.', 'popis_en' => 'Uzbekistan is included in the GSP+ scheme. With a correctly issued proof of origin, import duty on textiles is 0%.'],
                ['stitek' => 'OEKO-TEX', 'nazev' => 'Standard 100', 'nazev_en' => 'Standard 100', 'popis' => 'Materiály testované na zdravotně závadné látky. OEKO-TEX® Standard 100 u relevantních materiálů; certifikát dodáváme k dodávce.', 'popis_en' => 'Materials tested for harmful substances. OEKO-TEX® Standard 100 on the relevant materials; the certificate ships with the goods.'],
                ['stitek' => 'REACH', 'nazev' => 'Soulad s EU legislativou', 'nazev_en' => 'EU legislation compliance', 'popis' => 'Barviva a úpravy v souladu s REACH. U private label řešíme i etiketaci podle nařízení o textilních názvech.', 'popis_en' => 'Dyes and finishes compliant with REACH. For private label we also handle labelling under the textile names regulation.'],
            ],
        ],

        'onas' => [
            'stitek' => 'O nás',
            'stitek_en' => 'About',
            'nadpis' => 'Most mezi středoasijskou výrobou a Evropou.',
            'nadpis_en' => 'A bridge between Central Asian production and Europe.',
            'text' => 'Exportex je český obchodní partner, který spojuje výrobu ve Střední Asii s odběrateli v EU. V místě výroby spolupracujeme se sítí prověřených partnerských provozů, které vybíráme a u kterých hlídáme kvalitu ve výrobě i přejímku vzorků. Nejsme přeprodejce z e-mailu, ale partner s kontrolou přímo na lince.',
            'text_en' => 'Exportex is a Czech trading partner connecting Central Asian production with buyers across the EU. On the ground we work with a network of vetted partner mills that we select and where we control quality in production and sample approval. We are a partner with control on the line, not an email reseller.',
            'text_druhy' => 'Pracujeme přímo v místě výroby: vybíráme a prověřujeme tkalcovny a pletárny, hlídáme kvalitu i specifikace a zajišťujeme původ, celní odbavení a dopravu. Pro Vás zůstává jeden odpovědný kontakt v Praze — od první poptávky až po zboží ve Vašem skladu.',
            'text_druhy_en' => 'We work on the ground: we select and vet mills, control quality and specifications, and arrange origin, customs and logistics. For you it stays one accountable contact in Prague — from the first enquiry to goods in your warehouse.',
            'foto' => '/assets/img/about.webp',
            'foto_popis' => 'Přejímka vzorků přímo v pletárně',
            'popisek' => 'Výběr výroby na místě',
            'popisek_en' => 'Vetting production on site',
            'popisek_misto' => 'TAŠKENT · 2026',
        ],

        'reference' => [
            'stitek' => 'Ukázky zakázek',
            'stitek_en' => 'Examples',
            'nadpis' => 'Příklady toho, co pro Vás zvládneme.',
            'nadpis_en' => 'Examples of what we can deliver for you.',
            'popis' => 'Ukázky typových zakázek napříč sortimentem.',
            'popis_en' => 'Typical orders across the range.',
            'polozky' => [
                ['stitek' => 'Retail', 'nazev' => 'Sezónní froté kolekce', 'nazev_en' => 'Seasonal terry collection', 'popis' => 'Froté kolekce pro prodejní síť — od techpacku a vzorkování po paletizaci připravenou k distribuci.', 'popis_en' => 'A terry collection for a retail network — from techpack and sampling to palletisation ready for distribution.'],
                ['stitek' => 'HORECA', 'nazev' => 'Hotelové sady na míru', 'nazev_en' => 'Made-to-measure hotel sets', 'popis' => 'Froté a ložní sady dimenzované na průmyslovou prádelnu.', 'popis_en' => 'Terry and bed sets specified for industrial laundering.'],
                ['stitek' => 'Private label', 'nazev' => 'Konfekce na vlastní značku', 'nazev_en' => 'Garments under your brand', 'popis' => 'Heavyweight konfekce šitá na Váš techpack a etikety, s kontrolou AQL a fotodokumentací před expedicí.', 'popis_en' => 'Heavyweight garments sewn to your techpack and labels, with AQL inspection and photo records before dispatch.'],
            ],
        ],

        'kontakt' => [
            'stitek' => 'Kontakt',
            'stitek_en' => 'Contact',
            'nadpis' => 'Řekněte nám, co potřebujete.',
            'nadpis_en' => 'Tell us what you need.',
            'osoba' => 'Tomáš Mikyska',
            'whatsapp' => true,
            'telegram' => true,
            'poznamka' => 'ODPOVÍDÁME DO 1 PRACOVNÍHO DNE',
            'poznamka_en' => 'WE REPLY WITHIN ONE WORKING DAY',
            'odeslano' => 'Ozveme se do jednoho pracovního dne.',
            'odeslano_en' => 'We will get back to you within one working day.',
        ],

        // Hlavička a patička – co šablona (Základní údaje) nemá: anglické podoby a texty patičky.
        'paticka' => [
            'text' => 'Český sourcingový a importní partner pro textil z Uzbekistánu a Střední Asie. Výběr výroby, kontrola kvality, doklady o původu, clo i doprava.',
            'text_en' => 'Czech sourcing and import partner for textiles from Uzbekistan and Central Asia. Mill selection, quality control, origin documents, duty and freight.',
            'adresa_en' => 'Na Poříčí 1070/19, Nové Město, 110 00 Prague 1, Czech Republic',
            'rejstrik_en' => 'Registered at the Municipal Court in Prague, section C, entry 374806',
            'pruh' => 'Textil z Uzbekistánu a Střední Asie do EU.',
            'pruh_en' => 'Textiles from Uzbekistan & Central Asia to the EU.',
        ],
    ];

    /** @return array<string, mixed> */
    public static function sekce(string $sekce): array
    {
        $vychozi = self::VYCHOZI[$sekce] ?? [];
        $ulozene = json_decode((string) rescue(fn () => Nastaveni::hodnota('obsah.'.$sekce), null, false), true);

        return is_array($ulozene) ? array_replace($vychozi, $ulozene) : $vychozi;
    }

    /** Uloží jen pole, která sekce zná. */
    public static function uloz(string $sekce, array $data): void
    {
        $pole = array_intersect_key($data, self::VYCHOZI[$sekce] ?? []);

        Nastaveni::nastav('obsah.'.$sekce, json_encode($pole, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sekce úvodní stránky: klíč v Obsahu webu (SekceWebu) => kotva na stránce
     * a sekce obsahu, ze které se bere štítek do menu. Pořadí a zapnutí určuje
     * Obsah webu → Hlavička a patička → Sekce na webu.
     */
    public const SEKCE = [
        'sortiment' => ['kotva' => 'sortiment', 'obsah' => 'sortiment'],
        'jak' => ['kotva' => 'jak', 'obsah' => 'jak'],
        'trasa' => ['kotva' => 'trasa', 'obsah' => 'trasa'],
        'doklady' => ['kotva' => 'doklady', 'obsah' => 'doklady'],
        'onas' => ['kotva' => 'onas', 'obsah' => 'onas'],
        'reference' => ['kotva' => 'reference', 'obsah' => 'reference'],
        'formular' => ['kotva' => 'kontakt', 'obsah' => 'kontakt'],
    ];

    /**
     * Zapnuté sekce v pořadí s číslem (01, 02…), kotvou a štítkem v obou
     * jazycích – pro sekce úvodní stránky, menu, mobilní menu i patičku.
     *
     * @return list<array{klic: string, kotva: string, cislo: string, cs: string, en: string, vMenu: bool}>
     */
    public static function sekceNaWebu(): array
    {
        $vMenu = array_column(SekceWebu::menu(), 'klic');
        $sekce = [];

        foreach (SekceWebu::naWebu() as $klic) {
            if (! isset(self::SEKCE[$klic])) {
                continue;
            }

            $obsah = self::sekce(self::SEKCE[$klic]['obsah']);
            $sekce[] = [
                'klic' => $klic,
                'kotva' => self::SEKCE[$klic]['kotva'],
                'cislo' => str_pad((string) (count($sekce) + 1), 2, '0', STR_PAD_LEFT),
                'cs' => (string) $obsah['stitek'],
                'en' => (string) ($obsah['stitek_en'] ?: $obsah['stitek']),
                'vMenu' => in_array($klic, $vMenu, true),
            ];
        }

        return $sekce;
    }

    /**
     * Adresa fotky: původní fotky leží v public/assets/img (cesta začíná „/“),
     * nahrané v administraci na disku public (storage).
     */
    public static function obrazek(?string $cesta): string
    {
        $cesta = (string) $cesta;

        return str_starts_with($cesta, '/') || str_starts_with($cesta, 'assets/')
            ? '/'.ltrim($cesta, '/')
            : '/storage/'.$cesta;
    }

    /**
     * Data pro skript webu (dřív assets/js/data.js): sortiment, kroky, trasa,
     * doklady, ukázky zakázek a popisky formuláře v obou jazycích. Na stránce
     * jako <script type="application/json"> – nespouští se, CSP zůstává přísná.
     *
     * @return array{cs: array, en: array}
     */
    public static function proSkript(): array
    {
        $sortiment = self::sekce('sortiment');
        $jak = self::sekce('jak');
        $trasa = self::sekce('trasa');
        $doklady = self::sekce('doklady');
        $reference = self::sekce('reference');
        $kontakt = self::sekce('kontakt');
        $udaje = ZakladniUdaje::nacti();
        $kategorie = array_values((array) $sortiment['kategorie']);
        $data = [];

        foreach (['cs', 'en'] as $jazyk) {
            $p = fn (array $polozka, string $pole) => (string) ($jazyk === 'en' ? ($polozka[$pole.'_en'] ?? '') : ($polozka[$pole] ?? ''));
            $kategoriePodleNazvu = collect($kategorie)->mapWithKeys(fn ($k) => [(string) ($k['nazev'] ?? '') => $p($k, 'nazev')]);
            $popisky = self::POPISKY[$jazyk];

            $data[$jazyk] = [
                'filters' => [$popisky['vse'], ...array_map(fn ($k) => $p($k, 'nazev'), $kategorie)],
                'detail' => $popisky['detail'],
                'cta' => $popisky['cta'],
                'termsNote' => $p($sortiment, 'podminky_poznamka'),
                'close' => $popisky['close'],
                'products' => array_map(fn ($produkt) => [
                    'tag' => $p($produkt, 'stitek'),
                    'cat' => $kategoriePodleNazvu[(string) ($produkt['kategorie'] ?? '')] ?? '',
                    'img' => self::obrazek($produkt['foto'] ?? ''),
                    'title' => $p($produkt, 'nazev'),
                    'desc' => $p($produkt, 'popis'),
                    'points' => array_values(array_filter(array_map(fn ($bod) => (string) ($bod[$jazyk] ?? ''), array_values((array) ($produkt['body'] ?? []))))),
                    'specs' => array_map(fn ($par) => ['k' => $p($par, 'nazev'), 'v' => $p($par, 'hodnota')], array_values((array) ($produkt['parametry'] ?? []))),
                    'terms' => $p($produkt, 'podminky'),
                ], array_values((array) $sortiment['produkty'])),
                'steps' => collect(array_values((array) $jak['kroky']))->map(fn ($krok, $i) => [
                    'n' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    't' => $p($krok, 'nazev'),
                    'd' => $p($krok, 'popis'),
                ])->all(),
                'mapFrom' => ['city' => $p($trasa, 'odkud'), 'sub' => $p($trasa, 'odkud_popis')],
                'mapTo' => ['city' => $p($trasa, 'kam'), 'sub' => $p($trasa, 'kam_popis')],
                'route' => ['bow' => 78],
                'compliance' => array_map(fn ($d) => ['k' => (string) ($d['stitek'] ?? ''), 't' => $p($d, 'nazev'), 'd' => $p($d, 'popis')], array_values((array) $doklady['polozky'])),
                'cases' => array_map(fn ($r) => ['badge' => (string) ($r['stitek'] ?? ''), 't' => $p($r, 'nazev'), 'd' => $p($r, 'popis')], array_values((array) $reference['polozky'])),
                'fields' => $popisky['fields'],
                'err' => $popisky['err'],
                'form' => $popisky['form'] + [
                    'note' => $p($kontakt, 'poznamka'),
                    'done' => $p($kontakt, 'odeslano'),
                ],
            ];
        }

        $data['kontakt'] = [
            'email' => (string) $udaje['email'],
            'telefon' => (string) $udaje['telefon'],
            'ochrana' => route('ochrana-udaju', absolute: false),
        ];

        return $data;
    }

    /**
     * Popisky ovládání webu (filtr, detail, formulář) – nejsou obsah, v administraci
     * se nemění. Kontakty v hláškách formuláře doplní skript ze Základních údajů.
     */
    public const POPISKY = [
        'cs' => [
            'vse' => 'Vše',
            'detail' => 'Detail',
            'cta' => 'Poptat tento sortiment',
            'close' => 'Zavřít',
            'fields' => [
                ['id' => 'name', 'label' => 'Jméno', 'type' => 'text', 'ph' => 'Jan'],
                ['id' => 'surname', 'label' => 'Příjmení', 'type' => 'text', 'ph' => 'Novák'],
                ['id' => 'company', 'label' => 'Firma', 'type' => 'text', 'ph' => 'Vaše s.r.o.'],
                ['id' => 'email', 'label' => 'E-mail', 'type' => 'email', 'ph' => 'jan@firma.cz'],
                ['id' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'ph' => '+420 — nepovinné'],
            ],
            'err' => ['req' => 'Povinné pole', 'email' => 'Neplatný e-mail', 'short' => 'Napište pár slov', 'phone' => 'Neplatné číslo', 'long' => 'Příliš dlouhé'],
            'form' => [
                'msg' => 'Vaše poptávka',
                'msgPh' => 'Sortiment, množství, gramáž, termín…',
                'submit' => 'Odeslat poptávku',
                'sending' => 'Odesílám…',
                'failed' => 'Poptávku se nepodařilo odeslat. Zkuste to prosím znovu, nebo napište přímo na {email}.',
                'throttle' => 'Odeslali jste několik poptávek za sebou. Zkuste to prosím za chvíli, nebo napište přímo na {email}.',
                'doneT' => 'Poptávka odeslána',
                'urgent' => 'Pokud spěcháte, volejte {telefon}.',
                'again' => 'Odeslat další',
                'gdpr' => 'Odesláním souhlasíte se zpracováním uvedených údajů pro vyřízení poptávky. Podrobnosti v <a href="{ochrana}">Ochraně osobních údajů</a>.',
            ],
        ],
        'en' => [
            'vse' => 'All',
            'detail' => 'Details',
            'cta' => 'Enquire about this range',
            'close' => 'Close',
            'fields' => [
                ['id' => 'name', 'label' => 'First name', 'type' => 'text', 'ph' => 'John'],
                ['id' => 'surname', 'label' => 'Last name', 'type' => 'text', 'ph' => 'Smith'],
                ['id' => 'company', 'label' => 'Company', 'type' => 'text', 'ph' => 'Your Ltd'],
                ['id' => 'email', 'label' => 'E-mail', 'type' => 'email', 'ph' => 'john@company.com'],
                ['id' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'ph' => '+44 — optional'],
            ],
            'err' => ['req' => 'Required', 'email' => 'Invalid e-mail', 'short' => 'Add a few words', 'phone' => 'Invalid number', 'long' => 'Too long'],
            'form' => [
                'msg' => 'Your enquiry',
                'msgPh' => 'Product, quantity, weight, deadline…',
                'submit' => 'Send enquiry',
                'sending' => 'Sending…',
                'failed' => 'The enquiry could not be sent. Please try again, or write directly to {email}.',
                'throttle' => 'You have sent several enquiries in a row. Please try again in a while, or write directly to {email}.',
                'doneT' => 'Enquiry sent',
                'urgent' => 'If it is urgent, call {telefon}.',
                'again' => 'Send another',
                'gdpr' => 'By sending you agree to your details being processed to handle the enquiry. See the <a href="{ochrana}">privacy notice</a>.',
            ],
        ],
    ];
}
