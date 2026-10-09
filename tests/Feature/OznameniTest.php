<?php

namespace Tests\Feature;

use App\Enums\DruhOznameni;
use App\Enums\KanalOznameni;
use App\Enums\StavOznameni;
use App\Enums\ZavaznostOznameni;
use App\Filament\Pages\MojeOznameni;
use App\Filament\Resources\Oznameni\OznameniResource;
use App\Filament\Resources\Oznameni\Pages\CreateOznameni;
use App\Filament\Resources\Oznameni\Pages\EditOznameni;
use App\Filament\Resources\Oznameni\Pages\ListOznameni;
use App\Filament\Resources\Oznameni\Pages\ViewOznameni;
use App\Mail\OznameniMail;
use App\Models\Oznameni;
use App\Models\OznameniDoruceni;
use App\Models\OznameniPrijemce;
use App\Models\OznameniSkupina;
use App\Models\OznameniSouhlas;
use App\Models\User;
use App\Support\Oznameni\Cileni;
use App\Support\Oznameni\NastaveniOznameni;
use App\Support\Oznameni\Odeslani;
use App\Support\Oznameni\Oznam;
use App\Support\Oznameni\OznameniNejdeOdeslat;
use App\Support\Oznameni\Predvolby;
use App\Support\Oznameni\Pruh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Oznámení (docs/oznameni.md, krok 1): centrum, pruh, e-mail přes Poštu,
 * cílení (každý jednou), předvolby a souhlasy, odhlášení jedním kliknutím,
 * administrace s potvrzením počtu a limitem, plánované odeslání, Oznam z kódu.
 */
class OznameniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Pruh::zapomen();
    }

    private function ucet(string $role, string $jmeno = 'Jana', string $prijmeni = 'Nováková', ?string $email = null): User
    {
        return User::forceCreate([
            'jmeno' => $jmeno,
            'prijmeni' => $prijmeni,
            'email' => $email ?? strtolower($jmeno.'.'.$prijmeni.'.'.$role.uniqid()).'@pekarna-novak.cz',
            'role' => $role,
            'password' => 'heslo-ktere-nikdo-nezna',
        ]);
    }

    private function souhlas(User $user, DruhOznameni $druh = DruhOznameni::Novinky): void
    {
        Predvolby::uloz($user, [$druh->value => ['centrum' => true, 'email' => true]], 'predvolby');
    }

    public function test_oznameni_z_kodu_prijde_do_centra_i_e_mailem_a_klic_ho_neposle_dvakrat(): void
    {
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');

        $oznameni = Oznam::provozni('Objednávka 2026-104 je na cestě')
            ->text('Balík jsme předali **PPL**, doručení zítra.')
            ->odkaz('/objednavky/104', 'Sledovat zásilku')
            ->komu($petr)
            ->klic('objednavka-104-odeslana')
            ->posli();

        $this->assertSame(StavOznameni::Odeslano, $oznameni->stav);
        $this->assertSame(1, $oznameni->pocet_prijemcu);
        Mail::assertSent(OznameniMail::class, fn (OznameniMail $m) => $m->hasTo($petr->email));

        // Stejný klíč podruhé nic nepošle.
        $znovu = Oznam::provozni('Objednávka 2026-104 je na cestě')->komu($petr)->klic('objednavka-104-odeslana')->posli();
        $this->assertTrue($znovu->is($oznameni));
        Mail::assertSentCount(1);

        $this->assertSame('odeslano', OznameniDoruceni::query()->value('stav'));

        // Centrum: JSON pro zvoneček, čas ISO 8601 s posunem.
        $json = $this->actingAs($petr)->getJson(route('oznameni.centrum'))->assertOk()->json();
        $this->assertSame(1, $json['neprectenych']);
        $this->assertSame('Objednávka 2026-104 je na cestě', $json['polozky'][0]['titulek']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $json['polozky'][0]['odeslano']);
        $this->assertStringContainsString('signature=', $json['polozky'][0]['odkaz']);
    }

    public function test_cileni_kazdy_jednou_a_superadmin_mimo_hromadne(): void
    {
        $spravce = $this->ucet('admin', 'Jana', 'Nováková');
        $klient1 = $this->ucet('klient', 'Petr', 'Svoboda');
        $klient2 = $this->ucet('klient', 'Eva', 'Dvořáková');
        $vyvojar = $this->ucet('superadmin', 'René', 'Rafael');

        $skupina = OznameniSkupina::query()->create(['nazev' => 'Stálí zákazníci']);
        $skupina->clenove()->attach([$klient1->id, $vyvojar->id]);

        // Role klient + skupina (Petr je v obou) + vybraná Jana – každý jednou, vývojář ze skupiny ne.
        $cileni = ['komu' => 'vybrani', 'role' => ['klient'], 'skupiny' => [$skupina->id], 'uzivatele' => [$spravce->id]];
        $this->assertEqualsCanonicalizing([$spravce->id, $klient1->id, $klient2->id], Cileni::dotaz($cileni)->pluck('id')->all());

        $this->assertNotContains($vyvojar->id, Cileni::dotaz(['komu' => 'vsichni'])->pluck('id')->all());
        // Jmenovitě vybraný vývojář oznámení dostane.
        $this->assertContains($vyvojar->id, Cileni::dotaz(['komu' => 'vybrani', 'uzivatele' => [$vyvojar->id]])->pluck('id')->all());

        $oznameni = Oznam::servisni('V sobotu zavřeno')->roli('klient')->skupine($skupina->id)->komu($spravce)->posli();
        $this->assertSame(3, $oznameni->pocet_prijemcu);
        $this->assertSame(3, OznameniPrijemce::query()->count());
        $this->assertSame('Klienti · Stálí zákazníci · 1 vybraný', Cileni::popis((array) $oznameni->cileni));
    }

    public function test_novinky_e_mailem_jen_se_souhlasem_a_souhlas_se_zapise(): void
    {
        $souhlasi = $this->ucet('klient', 'Petr', 'Svoboda');
        $nesouhlasi = $this->ucet('klient', 'Eva', 'Dvořáková');
        $this->souhlas($souhlasi);

        $zaznam = OznameniSouhlas::query()->sole();
        $this->assertTrue($zaznam->udelen);
        $this->assertSame('email', $zaznam->kanal);
        $this->assertStringContainsString('Souhlasím se zasíláním novinek', $zaznam->text);

        $oznameni = Oznam::novinky('Nově objednávky přes mobil')->roli('klient')->posli();

        Mail::assertSent(OznameniMail::class, fn (OznameniMail $m) => $m->hasTo($souhlasi->email));
        Mail::assertNotSent(OznameniMail::class, fn (OznameniMail $m) => $m->hasTo($nesouhlasi->email));
        $this->assertSame('Bez souhlasu.', OznameniDoruceni::query()->where('stav', 'preskoceno')->value('duvod'));

        // V centru ho mají oba (novinka v aplikaci není obchodní sdělení e-mailem).
        $this->assertSame(2, $oznameni->prijemci()->where('v_centru', true)->count());

        // E-mail novinek má odhlášení jedním kliknutím (RFC 8058).
        Mail::assertSent(OznameniMail::class, function (OznameniMail $m) {
            $hlavicky = $m->headers()->text;

            return str_contains($hlavicky['List-Unsubscribe'], '/oznameni/odhlasit/')
                && $hlavicky['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
    }

    public function test_odhlaseni_odkazem_i_jednim_kliknutim_z_posty(): void
    {
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');
        $this->souhlas($petr);
        $odkaz = URL::signedRoute('oznameni.odhlasit', ['user' => $petr->id, 'druh' => 'novinky']);

        // Otevření odkazu (i skenerem pošty) nic neodhlásí – jen ukáže tlačítko.
        $this->get($odkaz)->assertOk()->assertSee('Odhlásit');
        $this->assertTrue(Predvolby::chce($petr, DruhOznameni::Novinky, KanalOznameni::Email));

        // Jedno kliknutí z poštovního programu: POST bez cookies a CSRF.
        $this->post($odkaz, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertFalse(Predvolby::chce($petr, DruhOznameni::Novinky, KanalOznameni::Email));
        $this->assertSame('jedno-kliknuti', OznameniSouhlas::query()->latest('id')->value('zdroj'));
        $this->assertFalse(OznameniSouhlas::query()->latest('id')->first()->udelen);

        // Neplatný podpis a provozní druh (ten se neodhlašuje) nic nedělají.
        $this->get('/oznameni/odhlasit/'.$petr->id.'/novinky')->assertForbidden();
        $this->get(URL::signedRoute('oznameni.odhlasit', ['user' => $petr->id, 'druh' => 'provozni']))->assertNotFound();
    }

    public function test_predvolby_na_webu_zamcene_centrum_a_vypnuty_e_mail(): void
    {
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');

        $this->actingAs($petr)->get(route('oznameni.stranka'))->assertOk()->assertSee('Předvolby')->assertSee('(vždy)', false);

        // Formulář posílá jen zaškrtnuté – servisní e-mail vypnutý, centrum zamčené zůstává.
        $this->actingAs($petr)->post(route('oznameni.predvolby'), ['predvolby' => ['servisni' => []]])->assertRedirect();

        $this->assertFalse(Predvolby::chce($petr->fresh(), DruhOznameni::Servisni, KanalOznameni::Email));
        $this->assertTrue(Predvolby::chce($petr->fresh(), DruhOznameni::Servisni, KanalOznameni::Centrum));

        Oznam::servisni('Nový ceník od listopadu')->komu($petr)->posli();
        Mail::assertNothingSent();
        $this->assertSame('Vypnuto v předvolbách.', OznameniDoruceni::query()->value('duvod'));
    }

    public function test_centrum_precteno_archiv_a_proklik_jen_vlastni(): void
    {
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');
        $eva = $this->ucet('klient', 'Eva', 'Dvořáková');

        Oznam::provozni('Platba přijata')->komu($petr)->kanaly(KanalOznameni::Centrum)->posli();
        Oznam::provozni('Faktura 2026-55')->odkaz('/faktury/55')->komu($petr)->kanaly(KanalOznameni::Centrum)->posli();
        [$platba, $faktura] = OznameniPrijemce::query()->orderBy('id')->get();

        // Cizí oznámení nikdo neoznačí.
        $this->actingAs($eva)->postJson(route('oznameni.precteno', $platba))->assertNotFound();

        $this->actingAs($petr)->postJson(route('oznameni.precteno', $platba))->assertOk()->assertJsonPath('neprectenych', 1);
        $this->actingAs($petr)->postJson(route('oznameni.archiv', $platba))->assertOk()->assertJsonCount(1, 'polozky');

        // Proklik (podepsaný, funguje i z e-mailu bez přihlášení) zapíše přečteno i proklik.
        auth()->logout();
        $this->get($faktura->odkazProkliku())->assertRedirect(url('/faktury/55'));
        $this->assertNotNull($faktura->fresh()->prokliknuto_at);
        $this->assertNotNull($faktura->fresh()->precteno_at);

        $this->actingAs($petr)->getJson(route('oznameni.centrum'))->assertJsonPath('neprectenych', 0);
        $this->actingAs($petr)->get(route('oznameni.stranka', ['zobrazit' => 'archiv']))->assertSee('Platba přijata')->assertDontSee('Faktura 2026-55');

        // Nepřihlášený na stránce oznámení → přihlášení.
        auth()->logout();
        $this->get(route('oznameni.stranka'))->assertRedirect(Route::has('login') ? route('login') : '/admin/login');
    }

    public function test_pruh_pro_vsechny_vidi_i_neprihlaseny_cileny_jen_prijemce(): void
    {
        // Mezipaměť jako na serveru (serializuje) – modely z ní Laravel nerozbalí.
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');
        $jana = $this->ucet('admin', 'Jana', 'Nováková');

        Oznam::servisni('V noci na neděli bude web 30 minut nedostupný')
            ->vsem()->kanaly(KanalOznameni::Pruh)
            ->zavaznost(ZavaznostOznameni::Varovani)
            ->odstavka(now()->addDay()->setTime(22, 0), now()->addDay()->setTime(22, 30))
            ->pruh(null, now()->addDays(2))
            ->posli();

        Oznam::servisni('Výpadek platební brány')->komu($jana)->kanaly(KanalOznameni::Pruh)->zavaznost(ZavaznostOznameni::Kriticke)->posli();

        $this->get('/')->assertOk()->assertSee('V noci na neděli bude web 30 minut nedostupný')->assertSee('data-ozn-od', false)->assertDontSee('Výpadek platební brány');
        $this->get('/')   /* /kontakt jen přesměruje na kotvu úvodu */ ->assertSee('V noci na neděli');   // podruhé z mezipaměti

        $this->actingAs($petr)->get('/')->assertDontSee('Výpadek platební brány');
        $this->actingAs($jana)->get('/admin')->assertOk()->assertSee('Výpadek platební brány')->assertSee('V noci na neděli');

        // Ukončený pruh zmizí.
        Oznameni::query()->update(['pruh_do' => now()->subMinute()]);
        Pruh::zapomen();
        auth()->logout();
        $this->get('/')->assertDontSee('V noci na neděli');
    }

    public function test_pruh_jen_u_servisnich_a_push_zatim_neumime(): void
    {
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');

        $this->expectException(OznameniNejdeOdeslat::class);
        Oznam::novinky('Nová funkce')->komu($petr)->kanaly(KanalOznameni::Pruh, KanalOznameni::MobilPush)->posli();
    }

    public function test_marketing_je_vychozi_vypnuty_a_zapne_ho_superadmin(): void
    {
        $this->assertFalse(NastaveniOznameni::marketing());
        $this->assertFalse(NastaveniOznameni::portalKoncovym());
        $this->assertNotContains(DruhOznameni::Marketing, NastaveniOznameni::druhyZAdministrace());

        $this->actingAs($this->ucet('admin'));
        Livewire::test(ListOznameni::class)->assertActionHidden('nastaveni');

        $this->actingAs($this->ucet('superadmin', 'René', 'Rafael'));
        Livewire::test(ListOznameni::class)
            ->callAction('nastaveni', ['marketing' => true, 'portal_koncovym' => false])
            ->assertNotified('Nastavení oznámení uloženo');

        $this->assertTrue(NastaveniOznameni::marketing());
        $this->assertContains(DruhOznameni::Marketing, NastaveniOznameni::druhyZAdministrace());
    }

    public function test_administrace_koncept_nahled_zkouska_a_odeslani_s_potvrzenim_poctu(): void
    {
        config(['oznameni.limity.potvrzeni_od' => 3]);
        $jana = $this->ucet('admin', 'Jana', 'Nováková');
        $klienti = collect(['Petr Svoboda', 'Eva Dvořáková', 'Karel Novotný'])
            ->map(fn ($j) => $this->ucet('klient', ...explode(' ', $j)));

        // Klient do administrace oznámení nesmí (a sám je taky příjemce – klientů je tedy 4).
        $this->actingAs($this->ucet('klient', 'Zdeněk', 'Malý'))->get(OznameniResource::getUrl())->assertForbidden();
        $this->actingAs($jana);

        Livewire::test(CreateOznameni::class)
            ->fillForm([
                'druh' => 'servisni',
                'titulek' => 'Od listopadu otevíráme v 6:00',
                'text' => "Pečeme **dřív**.\n\nTěšíme se na vás.",
                'kanaly' => ['centrum', 'email'],
                'cileni' => ['komu' => 'vybrani', 'role' => ['klient'], 'uzivatele' => [$jana->id]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $oznameni = Oznameni::query()->sole();
        $this->assertSame(StavOznameni::Koncept, $oznameni->stav);
        $this->assertSame($jana->id, $oznameni->vytvoril_id);

        Livewire::test(EditOznameni::class, ['record' => $oznameni->getRouteKey()])
            ->callAction('nahled')
            ->callAction('test')
            ->assertNotified('Zkouška odešla na '.$jana->email);
        Mail::assertSent(OznameniMail::class, fn (OznameniMail $m) => $m->zkouska && $m->hasTo($jana->email));
        $this->assertSame(0, OznameniPrijemce::query()->count(), 'zkouška nikoho nezapíše');

        // 5 příjemců (4 klienti + Jana) ≥ 3 → počet se musí opsat.
        Livewire::test(EditOznameni::class, ['record' => $oznameni->getRouteKey()])
            ->mountAction('odeslat')
            ->assertSet('poctyOdeslani.prijemcu', 5)
            ->assertSet('poctyOdeslani.email', 5)
            ->setActionData(['potvrzeni' => '4'])
            ->callMountedAction()
            ->assertHasActionErrors(['potvrzeni'])
            ->setActionData(['potvrzeni' => '5'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertRedirect(OznameniResource::getUrl('view', ['record' => $oznameni]));

        $oznameni->refresh();
        $this->assertSame(StavOznameni::Odeslano, $oznameni->stav);
        $this->assertSame(5, $oznameni->pocet_prijemcu);
        $this->assertSame($jana->id, $oznameni->odeslal_id);
        Mail::assertSent(OznameniMail::class, 6);   // zkouška + 5

        // Odeslané už nejde upravit, detail ukáže čísla.
        $this->assertFalse(OznameniResource::canEdit($oznameni));
        Livewire::test(ViewOznameni::class, ['record' => $oznameni->getRouteKey()])->assertSee('příjemců')->assertSee('přečetlo');
        $this->get(OznameniResource::getUrl())->assertOk()->assertSee('Od listopadu otevíráme v 6:00');
    }

    public function test_limit_prijemcu_a_prazdny_vyber_neodejde(): void
    {
        config(['oznameni.limity.max_prijemcu' => 2]);
        $jana = $this->ucet('admin');
        foreach (range(1, 3) as $i) {
            $this->ucet('klient', 'Klient', 'Číslo'.$i);
        }
        $this->actingAs($jana);

        $hodne = Oznameni::query()->create(['druh' => 'servisni', 'titulek' => 'Všem', 'kanaly' => ['centrum'], 'cileni' => ['komu' => 'vsichni']]);
        $nikdo = Oznameni::query()->create(['druh' => 'servisni', 'titulek' => 'Nikomu', 'kanaly' => ['centrum'], 'cileni' => ['komu' => 'vybrani']]);

        $this->assertStringContainsString('nejvýš 2 příjemců', implode(' ', app(Odeslani::class)->chyby($hodne)));
        $this->assertContains('Vyberte, komu oznámení půjde.', app(Odeslani::class)->chyby($nikdo));

        Livewire::test(EditOznameni::class, ['record' => $hodne->getRouteKey()])
            ->mountAction('odeslat')
            ->assertNotified('Oznámení zatím nejde odeslat');
        $this->assertSame(StavOznameni::Koncept, $hodne->fresh()->stav);
    }

    public function test_naplanovane_odejde_z_planovace_a_zrusit_plan_vrati_koncept(): void
    {
        $petr = $this->ucet('klient', 'Petr', 'Svoboda');
        $this->actingAs($this->ucet('admin'));

        $oznameni = Oznameni::query()->create([
            'druh' => 'servisni', 'titulek' => 'Inventura v pondělí', 'kanaly' => ['centrum'],
            'cileni' => ['komu' => 'vsichni'], 'naplanovano_na' => now()->addHour(),
        ]);
        app(Odeslani::class)->odeslat($oznameni);
        $this->assertSame(StavOznameni::Naplanovano, $oznameni->fresh()->stav);
        $this->assertFalse(Odeslani::maPraci());

        $this->travel(61)->minutes();
        $this->assertTrue(Odeslani::maPraci());
        $this->assertSame(1, app(Odeslani::class)->naplanovana());
        $this->assertSame(StavOznameni::Odeslano, $oznameni->fresh()->stav);
        $this->assertSame(1, OznameniPrijemce::query()->where('user_id', $petr->id)->count());

        $druhe = Oznameni::query()->create([
            'druh' => 'servisni', 'titulek' => 'Zrušené', 'kanaly' => ['centrum'],
            'cileni' => ['komu' => 'vsichni'], 'naplanovano_na' => now()->addDay(),
        ]);
        app(Odeslani::class)->odeslat($druhe);
        Livewire::test(ViewOznameni::class, ['record' => $druhe->getRouteKey()])->callAction('zrusitPlan');
        $this->assertSame(StavOznameni::Koncept, $druhe->fresh()->stav);
    }

    public function test_zvonecek_na_webu_i_v_administraci_a_moje_oznameni(): void
    {
        $jana = $this->ucet('admin');
        Oznam::servisni('Nová verze administrace')->komu($jana)->kanaly(KanalOznameni::Centrum)->posli();

        $this->actingAs($jana)->get('/admin')->assertOk()
            ->assertSee('ozn-zvonecek', false)
            ->assertSee('js/oznameni.js', false)
            ->assertSee(MojeOznameni::getUrl(), false);
        $this->actingAs($jana)->get('/')   /* /kontakt jen přesměruje na kotvu úvodu */ ->assertSee('ozn-zvonecek', false);
        auth()->logout();
        $this->get('/')   /* /kontakt jen přesměruje na kotvu úvodu */ ->assertDontSee('ozn-zvonecek', false);

        $this->actingAs($jana);
        Livewire::test(MojeOznameni::class)
            ->assertSee('Nová verze administrace')
            ->callAction('predvolby', ['novinky' => ['centrum' => true, 'email' => true], 'servisni' => ['email' => false]])
            ->assertNotified('Předvolby uložené');

        $this->assertTrue(Predvolby::chce($jana->fresh(), DruhOznameni::Novinky, KanalOznameni::Email));
        $this->assertFalse(Predvolby::chce($jana->fresh(), DruhOznameni::Servisni, KanalOznameni::Email));
        $this->assertSame('predvolby', OznameniSouhlas::query()->value('zdroj'));
    }

    public function test_ochrana_osobnich_udaju_popise_oznameni_az_kdyz_se_pouzivaji(): void
    {
        if (! Route::has('ochrana-udaju')) {
            $this->markTestSkipped('Projekt nemá stránku Ochrana osobních údajů ze šablony.');
        }

        if (! Route::has('ochrana-udaju')) {
            $this->markTestSkipped('Projekt nemá stránku Ochrana osobních údajů ze šablony.');
        }

        $this->get('/ochrana-osobnich-udaju')->assertDontSee('Oznámení a novinky');

        Oznam::provozni('Účet založen')->komu($this->ucet('klient'))->kanaly(KanalOznameni::Centrum)->posli();

        $this->get('/ochrana-osobnich-udaju')->assertSee('Oznámení a novinky')->assertSee('bez měřicích pixelů');
    }
}
