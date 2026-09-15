<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_homepage_is_public_and_contains_real_product_sections_and_guest_ctas(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertViewIs('public.home')
            ->assertSee('Organizza il lavoro')
            ->assertSee('Inizia gratis')
            ->assertSee('Accedi')
            ->assertSeeText("Tutto il progetto, in un'unica board")
            ->assertSee('AI dentro il progetto, non in una chat separata')
            ->assertSee('Il team vede quello che succede, mentre succede')
            ->assertSee('Genera descrizione')
            ->assertSee('Scomponi obiettivo')
            ->assertSee('Riassumi progetto')
            ->assertSee('Analizza progetto')
            ->assertSee('Posso iniziare gratuitamente?')
            ->assertSee('Come funzionano i workspace condivisi?')
            ->assertSeeText("L'AI modifica automaticamente le mie task?")
            ->assertSee('Come funzionano i crediti AI?')
            ->assertSee('Cosa succede se supero un limite dopo un downgrade?')
            ->assertSee(route('pricing'))
            ->assertViewHas('plans', fn ($plans): bool => $plans->count() === 4 && ! $plans->contains('slug', 'unlimited'));
    }

    public function test_authenticated_homepage_offers_dashboard_cta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Vai alla dashboard')
            ->assertDontSee('href="/register"');
    }

    public function test_public_pricing_uses_the_four_commercial_plans_and_real_catalog_values(): void
    {
        Plan::where('slug', 'pro')->update(['price_cents' => 777, 'max_projects' => 11]);

        $response = $this->get('/pricing');

        $response->assertOk()->assertViewIs('public.pricing')
            ->assertSee('Piani semplici, senza sorprese')
            ->assertSee('7,77')
            ->assertSee('11 progetti')
            ->assertSee('3.000 crediti AI / mese')
            ->assertSee('10.000 crediti AI / mese')
            ->assertSee('Progetti illimitati')
            ->assertSee('AI non inclusa')
            ->assertSee('I limiti membri includono il proprietario del workspace.')
            ->assertSee('Disponibile a breve')
            ->assertViewHas('plans', function ($plans): bool {
                return $plans->pluck('slug')->all() === ['free', 'pro', 'team', 'business'];
            });
        $response->assertDontSee('Unlimited')->assertDontSee('checkout')->assertDontSee('Stripe');
    }

    public function test_public_auth_routes_render_existing_auth_interface(): void
    {
        $this->get('/login')->assertOk()->assertSee('Accedi')->assertSee('name="email"', false)->assertSee('name="password"', false)->assertSee('Crea account')->assertDontSee('name="last_name"', false);
        $this->get('/register')->assertOk()->assertSee('Crea il tuo account')->assertSee('name="last_name"', false)->assertSee('name="username"', false)->assertSee('Conferma password')->assertSee('Accedi')->assertDontSee('id="login-email"', false);
    }
}
