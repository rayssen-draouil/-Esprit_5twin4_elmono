<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carte interactive des zones (Leaflet + OpenStreetMap).
 *
 * Couvre : validation des coordonnées, endpoint back.zones.map-data,
 * contrôle des rôles, rendu des cartes et rétrocompatibilité des zones
 * sans géolocalisation.
 */
class ZoneMapTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($this->manager);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Zone Cartographiée',
            'address' => 'Avenue Habib Bourguiba, Tunis',
            'description' => 'Zone de test cartographiée.',
            'risk_level' => 'medium',
        ], $overrides);
    }

    public function test_store_with_valid_coordinates_persists_position(): void
    {
        $response = $this->post(route('back.zones.store'), $this->validPayload([
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 2500,
        ]));

        $response->assertRedirect(route('back.zones.index'));

        $zone = Zone::where('name', 'Zone Cartographiée')->firstOrFail();

        $this->assertTrue($zone->hasCoordinates());
        $this->assertEqualsWithDelta(36.8065, (float) $zone->latitude, 0.0001);
        $this->assertEqualsWithDelta(10.1815, (float) $zone->longitude, 0.0001);
        $this->assertSame(2500, $zone->radius_m);

        $coordinates = $zone->coordinates();
        $this->assertIsArray($coordinates);
        $this->assertSame(36.8065, $coordinates['latitude']);
        $this->assertSame(10.1815, $coordinates['longitude']);
    }

    public function test_store_without_coordinates_still_succeeds(): void
    {
        $response = $this->post(route('back.zones.store'), $this->validPayload([
            'latitude' => null,
            'longitude' => null,
            'radius_m' => null,
        ]));

        $response->assertRedirect(route('back.zones.index'));

        $zone = Zone::where('name', 'Zone Cartographiée')->firstOrFail();

        $this->assertFalse($zone->hasCoordinates());
        $this->assertNull($zone->latitude);
        $this->assertNull($zone->longitude);
        $this->assertNull($zone->radius_m);
        $this->assertNull($zone->coordinates());
    }

    public function test_store_rejects_out_of_range_coordinates(): void
    {
        $tooHigh = $this->from(route('back.zones.create'))->post(route('back.zones.store'), $this->validPayload([
            'name' => 'Zone Latitude Invalide',
            'latitude' => 91,
            'longitude' => 10.1815,
        ]));
        $tooHigh->assertSessionHasErrors('latitude');

        $tooLow = $this->from(route('back.zones.create'))->post(route('back.zones.store'), $this->validPayload([
            'name' => 'Zone Longitude Invalide',
            'latitude' => 36.8065,
            'longitude' => -180.5,
        ]));
        $tooLow->assertSessionHasErrors('longitude');

        $this->assertDatabaseMissing('zones', ['name' => 'Zone Latitude Invalide']);
        $this->assertDatabaseMissing('zones', ['name' => 'Zone Longitude Invalide']);
    }

    public function test_store_rejects_partial_coordinates(): void
    {
        $latOnly = $this->from(route('back.zones.create'))->post(route('back.zones.store'), $this->validPayload([
            'name' => 'Zone Latitude Seule',
            'latitude' => 36.8065,
        ]));
        $latOnly->assertSessionHasErrors('longitude');

        $lngOnly = $this->from(route('back.zones.create'))->post(route('back.zones.store'), $this->validPayload([
            'name' => 'Zone Longitude Seule',
            'longitude' => 10.1815,
        ]));
        $lngOnly->assertSessionHasErrors('latitude');

        $this->assertDatabaseCount('zones', 0);
    }

    public function test_store_rejects_invalid_radius(): void
    {
        $zero = $this->from(route('back.zones.create'))->post(route('back.zones.store'), $this->validPayload([
            'name' => 'Zone Rayon Zéro',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 0,
        ]));
        $zero->assertSessionHasErrors('radius_m');

        $tooBig = $this->from(route('back.zones.create'))->post(route('back.zones.store'), $this->validPayload([
            'name' => 'Zone Rayon Énorme',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 500000,
        ]));
        $tooBig->assertSessionHasErrors('radius_m');

        $this->assertDatabaseCount('zones', 0);
    }

    public function test_update_can_clear_coordinates(): void
    {
        $zone = Zone::factory()->create([
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 3000,
        ]);

        $response = $this->put(route('back.zones.update', $zone), [
            'name' => $zone->name,
            'address' => $zone->address,
            'risk_level' => $zone->risk_level,
            'latitude' => '',
            'longitude' => '',
            'radius_m' => '',
        ]);

        $response->assertRedirect(route('back.zones.show', $zone));

        $zone->refresh();
        $this->assertFalse($zone->hasCoordinates());
        $this->assertNull($zone->latitude);
        $this->assertNull($zone->radius_m);
    }

    public function test_map_data_returns_geolocated_zones_without_personal_data(): void
    {
        $located = Zone::factory()->create([
            'name' => 'Zone Tunis Centre',
            'risk_level' => 'medium',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 2500,
            'ai_risk_score' => 42,
            'ai_risk_level' => 'medium',
        ]);
        Zone::factory()->create([
            'name' => 'Zone Sans Position',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->getJson(route('back.zones.map-data'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('located', 1)
            ->assertJsonCount(1, 'zones')
            ->assertJsonPath('zones.0.id', $located->id)
            ->assertJsonPath('zones.0.name', 'Zone Tunis Centre')
            ->assertJsonPath('zones.0.risk_level', 'medium')
            ->assertJsonPath('zones.0.risk_label', 'Moyen')
            ->assertJsonPath('zones.0.ai_risk_score', 42);

        $payload = $response->json('zones.0');

        // Les décimaux reviennent en chaîne selon le pilote PDO : comparaison tolérante.
        $this->assertEqualsWithDelta(36.8065, (float) $payload['latitude'], 0.0001);
        $this->assertEqualsWithDelta(10.1815, (float) $payload['longitude'], 0.0001);
        $this->assertEquals(2500, $payload['radius_m']);
        $this->assertStringEndsWith('/back/zones/'.$located->id, (string) $payload['url']);

        $keys = array_keys($payload);
        $forbidden = ['email', 'password', 'user_id', 'phone', 'telegram_chat_id'];
        foreach ($forbidden as $secret) {
            $this->assertNotContains($secret, $keys);
        }

        // Aucune zone non géolocalisée n'apparaît sur la carte.
        $names = array_column($response->json('zones'), 'name');
        $this->assertNotContains('Zone Sans Position', $names);
    }

    public function test_map_data_requires_authentication(): void
    {
        auth()->logout();

        $this->get(route('back.zones.map-data'))->assertRedirect(route('login'));
    }

    public function test_map_data_forbidden_for_citizen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'citizen']));

        $this->get(route('back.zones.map-data'))->assertForbidden();
    }

    public function test_form_renders_map_and_synchronized_coordinate_fields(): void
    {
        $response = $this->get(route('back.zones.create'));

        $response->assertOk()
            ->assertSee('data-mode="form"', false)
            ->assertSee('name="latitude"', false)
            ->assertSee('name="longitude"', false)
            ->assertSee('name="radius_m"', false)
            ->assertSee('data-zone-search', false)
            ->assertSee('Utiliser ma position', false);
    }

    public function test_form_prefills_map_with_existing_coordinates(): void
    {
        $zone = Zone::factory()->create([
            'latitude' => 34.7406,
            'longitude' => 10.7603,
            'radius_m' => 4000,
        ]);

        $response = $this->get(route('back.zones.edit', $zone));

        $response->assertOk()
            ->assertSee('data-mode="form"', false)
            ->assertSee('[34.7406,10.7603]', false)
            ->assertSee('name="radius_m"', false);
    }

    public function test_show_renders_map_with_zone_and_alerts_read_only(): void
    {
        $zone = Zone::factory()->create([
            'name' => 'Zone Carto Show',
            'latitude' => 36.8065,
            'longitude' => 10.1815,
            'radius_m' => 3000,
            'risk_level' => 'high',
            'ai_risk_score' => 71,
            'ai_risk_level' => 'high',
        ]);

        $alert = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Contamination Carto',
            'severity' => 'high',
        ]);

        $alertsBefore = Alert::count();

        $response = $this->get(route('back.zones.show', $zone));

        $response->assertOk()
            ->assertSee('data-mode="zone"', false)
            ->assertSee('Zone Carto Show', false);

        // Données de la zone + alerte rendues dans la config de la carte.
        $content = $response->getContent();
        $this->assertStringContainsString('36.8065', $content);
        $this->assertStringContainsString('10.1815', $content);
        $this->assertStringContainsString('Contamination Carto', $content);
        $this->assertStringContainsString(route('back.alerts.show', $alert), $content);
        $this->assertStringContainsString('ai_risk_score', $content);

        // Lecture seule : aucune alerte créée ou modifiée par l'affichage.
        $this->assertSame($alertsBefore, Alert::count());
    }

    public function test_show_without_coordinates_renders_clear_empty_state(): void
    {
        $zone = Zone::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->get(route('back.zones.show', $zone));

        $response->assertOk()
            ->assertSee('Aucune position géographique pour cette zone.', false)
            ->assertSee('Ajouter une position sur la carte', false)
            ->assertSee(route('back.zones.edit', $zone));

        $this->assertStringNotContainsString('data-zone-map', $response->getContent());
    }

    public function test_index_renders_toggle_and_map_panel(): void
    {
        Zone::factory()->create(['latitude' => 36.8065, 'longitude' => 10.1815]);
        Zone::factory()->create(['latitude' => null, 'longitude' => null]);

        $response = $this->get(route('back.zones.index'));

        $response->assertOk()
            ->assertSee('data-zone-view-toggle', false)
            ->assertSee('id="zones-list-panel"', false)
            ->assertSee('id="zones-map-panel"', false)
            ->assertSee('data-mode="index"', false)
            ->assertSee(route('back.zones.map-data'), false);
    }
}
