<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Signalement;
use App\Models\User;
use App\Models\UserZoneSubscription;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages et endpoints publics (citoyens) des zones et des alertes.
 *
 * Vérifie le rendu des nouvelles pages, la carte publique, la recherche de
 * zone la plus proche, le suivi de signalement et les zones suivies, sans
 * jamais exposer de données sensibles.
 */
class FrontZoneAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_other_public_pages_render_with_the_new_layout(): void
    {
        foreach ([
            route('home'),
            route('front.about'),
            route('front.services'),
            route('front.projects.index'),
            route('front.funding'),
            route('front.news'),
            route('front.contact'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_front_zones_page_renders_cards_and_map(): void
    {
        Zone::factory()->create([
            'name' => 'Zone Front Citoyen',
            'address' => 'Rue du Test, Montpellier',
            'risk_level' => 'high',
            'latitude' => 43.6108,
            'longitude' => 3.8767,
            'radius_m' => 1200,
        ]);

        $response = $this->get(route('front.zones.index'));

        $response->assertOk();
        $response->assertSee('Zone Front Citoyen');
        $response->assertSee('zones-list-panel', false);
        $response->assertSee('zones-map-panel', false);
        $response->assertSee('data-zone-map', false);
        $response->assertSee('data-mode="index"', false);
        $response->assertSee('Trouver ma zone');
    }

    public function test_zone_detail_page_shows_public_citizen_information(): void
    {
        $zone = Zone::factory()->create([
            'name' => 'Zone Detail Citoyen',
            'risk_level' => 'high',
            'latitude' => 43.6108,
            'longitude' => 3.8767,
            'radius_m' => 900,
        ]);

        Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Fuite visible',
            'message' => 'Une fuite a ete reperee sur la conduite principale.',
            'severity' => 'critical',
        ]);

        $response = $this->get(route('front.zones.show', $zone));

        $response->assertOk();
        $response->assertSee('Zone Detail Citoyen');
        $response->assertSee('Fuite visible');
        $response->assertSee('Suivre cette zone');
        $response->assertSee('Alertes en cours');
        $response->assertSee('data-mode="zone"', false);
        $response->assertDontSee('telegram_chat_id');
        $response->assertDontSee('reporter_email');
    }

    public function test_map_data_exposes_only_public_fields(): void
    {
        $zone = Zone::factory()->create([
            'name' => 'Zone Carte Publique',
            'risk_level' => 'medium',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 1500,
        ]);

        $response = $this->getJson(route('front.zones.map-data'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('zones.0.id', $zone->id);
        $response->assertJsonPath('zones.0.latitude', 36.8065);
        $response->assertJsonPath('zones.0.longitude', 10.1815);
        $response->assertJsonPath('zones.0.risk_level', 'medium');
        $response->assertJsonPath('zones.0.radius_m', 1500);
        $response->assertJsonStructure([
            'zones' => [[
                'id', 'name', 'address', 'risk', 'risk_level', 'risk_label',
                'ai_risk_level', 'ai_risk_level_label', 'ai_risk_score',
                'alerts', 'radius_m', 'latitude', 'longitude', 'url',
            ]],
        ]);

        $payload = $response->json('zones.0');
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayNotHasKey('ai_summary', $payload);
    }

    public function test_nearest_returns_the_closest_zone(): void
    {
        $near = Zone::factory()->create([
            'name' => 'Zone Proche',
            'latitude' => 43.6108,
            'longitude' => 3.8767,
        ]);

        Zone::factory()->create([
            'name' => 'Zone Lointaine',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);

        $response = $this->getJson(route('front.zones.nearest', [
            'lat' => 43.6110,
            'lng' => 3.8770,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('zone.id', $near->id);
        $response->assertJsonPath('zone.name', 'Zone Proche');
        $this->assertLessThan(1, $response->json('zone.distance_km'));
    }

    public function test_nearest_validates_coordinates(): void
    {
        $this->getJson(route('front.zones.nearest', ['lat' => 200, 'lng' => 3]))
            ->assertStatus(422);
    }

    public function test_alert_detail_shows_explanation_and_recommended_actions(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zone Alerte Citoyen']);

        $alert = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Contamination suspectee',
            'message' => 'Une anomalie a ete detectee sur le reseau.',
            'severity' => 'critical',
        ]);

        $response = $this->get(route('front.alerts.show', $alert));

        $response->assertOk();
        $response->assertSee('Contamination suspectee');
        $response->assertSee('Zone Alerte Citoyen');
        $response->assertSee('Ce que cela signifie');
        $response->assertSee('Les bons réflexes');
        $response->assertSee('Avancement du traitement');
        $response->assertDontSee('telegram_chat_id');
    }

    public function test_live_endpoint_returns_public_alert_summary(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zone Live Citoyen']);

        Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Fuite live',
            'severity' => 'high',
        ]);

        $response = $this->getJson(route('front.alerts.live'));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'new_count',
            'kpis' => ['open', 'critical', 'resolved_today'],
            'latest' => [['type', 'zone', 'severity', 'severityLevel', 'date', 'url']],
            'updated_at',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('kpis.open'));
        $response->assertDontSee('telegram_chat_id');
    }

    public function test_tracking_token_resolves_report_without_authentication(): void
    {
        $signalement = Signalement::create([
            'type' => "Fuite d'eau",
            'description' => 'Fuite visible devant le numero 12.',
            'location' => 'Rue du Test',
            'status' => 'new',
            'reported_at' => now(),
        ]);

        $response = $this->get(route('front.track', ['ref' => $signalement->tracking_token]));

        $response->assertOk();
        $response->assertSee($signalement->tracking_token);
        $response->assertSee('Signalement reçu');
        $response->assertSee('Rue du Test');
        $response->assertDontSee('reporter_email');
    }

    public function test_legacy_reference_requires_the_authenticated_owner(): void
    {
        $owner = User::factory()->create(['role' => 'citizen']);
        $other = User::factory()->create(['role' => 'citizen']);

        $signalement = Signalement::create([
            'user_id' => $owner->id,
            'type' => "Fuite d'eau",
            'description' => 'Description du dossier prive.',
            'location' => 'Rue Privee',
            'status' => 'new',
            'reported_at' => now(),
        ]);

        $reference = $signalement->publicReference();

        $this->get(route('front.track', ['ref' => $reference]))
            ->assertOk()
            ->assertSee('Aucun dossier ne correspond');

        $this->actingAs($other)
            ->get(route('front.track', ['ref' => $reference]))
            ->assertOk()
            ->assertSee('Aucun dossier ne correspond');

        $this->actingAs($owner)
            ->get(route('front.track', ['ref' => $reference]))
            ->assertOk()
            ->assertSee($reference)
            ->assertSee('Rue Privee');
    }

    public function test_authenticated_user_can_follow_and_unfollow_a_zone(): void
    {
        $user = User::factory()->create(['role' => 'citizen']);
        $zone = Zone::factory()->create(['name' => 'Zone Suivie']);

        $this->actingAs($user)
            ->postJson(route('front.zones.subscribe', $zone))
            ->assertOk()
            ->assertJsonPath('subscribed', true);

        $this->assertDatabaseHas('user_zone_subscriptions', [
            'user_id' => $user->id,
            'zone_id' => $zone->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('front.zones.unsubscribe', $zone))
            ->assertOk()
            ->assertJsonPath('subscribed', false);

        $this->assertDatabaseMissing('user_zone_subscriptions', [
            'user_id' => $user->id,
            'zone_id' => $zone->id,
        ]);
    }

    public function test_subscriptions_endpoint_returns_followed_zone_ids(): void
    {
        $user = User::factory()->create(['role' => 'citizen']);
        $zone = Zone::factory()->create();

        UserZoneSubscription::create(['user_id' => $user->id, 'zone_id' => $zone->id]);

        $this->actingAs($user)
            ->getJson(route('front.zones.subscriptions'))
            ->assertOk()
            ->assertJsonPath('zone_ids', [$zone->id]);
    }

    public function test_guest_cannot_follow_a_zone(): void
    {
        $zone = Zone::factory()->create();

        $this->post(route('front.zones.subscribe', $zone))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('user_zone_subscriptions', 0);
    }
}
