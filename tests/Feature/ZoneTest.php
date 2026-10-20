<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Incident;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_zone_list_loads_with_search_and_counts(): void
    {
        Zone::factory()->create(['name' => 'Zone Littoral', 'risk_level' => 'low']);
        Zone::factory()->create(['name' => 'Zone Montagne', 'risk_level' => 'high']);

        $response = $this->get(route('back.zones.index'));

        $response->assertOk()
            ->assertSee('Zone Littoral')
            ->assertSee('Zone Montagne')
            ->assertSee('Infrastructures');

        $filtered = $this->get(route('back.zones.index', ['q' => 'Littoral', 'risk' => 'low']));

        $filtered->assertOk()->assertSee('Zone Littoral')->assertDontSee('Zone Montagne');
    }

    public function test_front_zones_page_loads_with_zone_data(): void
    {
        Zone::factory()->create([
            'name' => 'Zone Front Test',
            'risk_level' => 'high',
        ]);

        $response = $this->get('/zones');

        $response->assertOk()
            ->assertSee('Zone Front Test')
            ->assertSee('Élevé')
            ->assertSee('incident(s)');
    }

    public function test_create_show_and_edit_pages_render(): void
    {
        $zone = Zone::factory()->create();

        $this->get(route('back.zones.create'))->assertOk()->assertSee('Nom de la zone');
        $this->get(route('back.zones.show', $zone))->assertOk()->assertSee($zone->name);
        $this->get(route('back.zones.edit', $zone))->assertOk()->assertSee($zone->name);
    }

    public function test_store_with_valid_data_creates_zone(): void
    {
        $response = $this->post(route('back.zones.store'), [
            'name' => 'Zone Atlantique',
            'address' => 'Nantes, Loire-Atlantique',
            'description' => 'Littoral et estuaire.',
            'risk_level' => 'medium',
        ]);

        $response->assertRedirect(route('back.zones.index'));
        $this->assertDatabaseHas('zones', [
            'name' => 'Zone Atlantique',
            'address' => 'Nantes, Loire-Atlantique',
            'risk_level' => 'medium',
        ]);
    }

    public function test_store_with_invalid_data_returns_errors(): void
    {
        $response = $this->from(route('back.zones.create'))
            ->post(route('back.zones.store'), [
                'name' => '',
                'address' => '',
                'description' => 'Test',
                'risk_level' => 'critical',
            ]);

        $response->assertSessionHasErrors(['name', 'address', 'risk_level'])
            ->assertSessionHas('errors');

        $errors = session('errors')->toArray();
        $this->assertSame('Le nom de la zone est obligatoire.', $errors['name'][0]);
        $this->assertSame("L'adresse est obligatoire.", $errors['address'][0]);

        $this->assertDatabaseCount('zones', 0);
    }

    public function test_store_rejects_duplicate_zone_name(): void
    {
        Zone::factory()->create(['name' => 'Zone Dupliquée']);

        $response = $this->post(route('back.zones.store'), [
            'name' => 'Zone Dupliquée',
            'address' => '10 rue des Eaux, Paris',
            'risk_level' => 'low',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('zones', 1);
    }

    public function test_update_modifies_zone_and_keeps_same_id(): void
    {
        $zone = Zone::factory()->create([
            'name' => 'Zone Avant',
            'risk_level' => 'low',
        ]);

        $response = $this->put(route('back.zones.update', $zone), [
            'name' => 'Zone Après',
            'address' => '25 quai de la Garonne, Bordeaux',
            'description' => 'Description mise à jour.',
            'risk_level' => 'medium',
        ]);

        $response->assertRedirect(route('back.zones.show', $zone));
        $this->assertDatabaseHas('zones', [
            'id' => $zone->id,
            'name' => 'Zone Après',
            'risk_level' => 'medium',
        ]);
        $this->assertDatabaseCount('zones', 1);
    }

    public function test_delete_of_empty_zone_succeeds(): void
    {
        $zone = Zone::factory()->create();

        $response = $this->delete(route('back.zones.destroy', $zone));

        $response->assertRedirect(route('back.zones.index'));
        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }

    public function test_delete_is_blocked_when_zone_has_infrastructures_and_incidents(): void
    {
        $zone = Zone::factory()->create();
        $zone->infrastructures()->create([
            'name' => 'Station de test',
            'type' => 'Station de pompage',
            'status' => 'operational',
        ]);
        Incident::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Équipement hors ligne',
        ]);

        $response = $this->delete(route('back.zones.destroy', $zone));

        $response->assertRedirect(route('back.zones.index'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            'Impossible de supprimer cette zone',
            session('error')
        );
        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }

    public function test_alert_is_created_when_risk_becomes_high(): void
    {
        $zone = Zone::factory()->create(['risk_level' => 'low']);
        $this->assertDatabaseCount('alerts', 0);

        $zone->update(['risk_level' => 'high']);

        $this->assertDatabaseHas('alerts', [
            'zone_id' => $zone->id,
            'type' => 'Risque élevé',
            'severity' => 'critical',
        ]);
    }

    public function test_alert_is_created_for_fuite_incident(): void
    {
        $zone = Zone::factory()->create(['risk_level' => 'low']);

        $incident = Incident::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Fuite de conduite',
            'description' => 'Fuite importante sur la conduite principale.',
        ]);

        $this->assertDatabaseHas('alerts', [
            'zone_id' => $zone->id,
            'incident_id' => $incident->id,
            'type' => 'Fuite',
        ]);

        Incident::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Équipement hors ligne',
            'description' => 'Panne de capteur.',
        ]);

        $this->assertSame(1, Alert::count());
    }
}
