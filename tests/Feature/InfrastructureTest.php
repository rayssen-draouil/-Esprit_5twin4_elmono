<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\InfrastructureMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(InfrastructureMaintenanceSeeder::class);
    }

    private function getAdminUser(): User
    {
        return User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);
    }

    private function getCitizenUser(): User
    {
        return User::where('role', 'citizen')->first()
            ?? User::factory()->create(['role' => 'citizen']);
    }

    public function test_guest_cannot_access_backoffice_infrastructures(): void
    {
        $response = $this->get(route('infrastructures.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_backoffice_infrastructures(): void
    {
        $citizen = $this->getCitizenUser();
        $response = $this->actingAs($citizen)->get(route('infrastructures.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_infrastructures_index(): void
    {
        $admin = $this->getAdminUser();
        $response = $this->actingAs($admin)->get(route('infrastructures.index'));

        $response->assertStatus(200);
        $response->assertSee('Gestion des infrastructures');
        $response->assertSee('Total infrastructures');
    }

    public function test_admin_can_search_infrastructures(): void
    {
        $admin = $this->getAdminUser();
        $zone = Zone::first() ?? Zone::factory()->create();

        $infra = Infrastructure::create([
            'zone_id' => $zone->id,
            'name' => 'Station Alpha Test Unique',
            'type' => 'Station de pompage',
            'reference_code' => 'INF-TEST-999',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'medium',
        ]);

        $response = $this->actingAs($admin)->get(route('infrastructures.index', ['search' => 'Alpha Test Unique']));
        $response->assertStatus(200);
        $response->assertSee('Station Alpha Test Unique');
        $response->assertSee('INF-TEST-999');

        $infra->delete();
    }

    public function test_admin_can_create_infrastructure(): void
    {
        $admin = $this->getAdminUser();
        $zone = Zone::first() ?? Zone::factory()->create();

        $data = [
            'name' => 'Nouvelle Station Test 2026',
            'zone_id' => $zone->id,
            'type' => 'Usine de filtration',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'high',
            'location' => 'Zone Portuaire, 34200 Sète',
            'capacity' => '50 000 m³/j',
            'description' => 'Test de création automatique',
        ];

        $response = $this->actingAs($admin)->post(route('infrastructures.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('infrastructures', ['name' => 'Nouvelle Station Test 2026']);

        $created = Infrastructure::where('name', 'Nouvelle Station Test 2026')->first();
        $this->assertNotNull($created->reference_code);
        $this->assertTrue(str_starts_with($created->reference_code, 'INF-'));

        $created->delete();
    }

    public function test_admin_can_view_infrastructure_details(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        $this->assertNotNull($infra);

        $response = $this->actingAs($admin)->get(route('infrastructures.show', $infra));
        $response->assertStatus(200);
        $response->assertSee($infra->name);
        $response->assertSee('Indicateur de Santé');
    }

    public function test_admin_can_update_infrastructure(): void
    {
        $admin = $this->getAdminUser();
        $zone = Zone::first();

        $infra = Infrastructure::create([
            'zone_id' => $zone->id,
            'name' => 'Infra Avant Update',
            'type' => 'Barrage',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'medium',
        ]);

        $response = $this->actingAs($admin)->put(route('infrastructures.update', $infra), [
            'name' => 'Infra Apres Update',
            'zone_id' => $zone->id,
            'type' => 'Barrage',
            'status' => 'maintenance',
            'condition' => 'fair',
            'criticality' => 'vital',
        ]);

        $response->assertRedirect(route('infrastructures.show', $infra));
        $this->assertDatabaseHas('infrastructures', ['id' => $infra->id, 'name' => 'Infra Apres Update', 'status' => 'maintenance']);

        $infra->delete();
    }

    public function test_public_can_view_front_infrastructures_and_details(): void
    {
        $infra = Infrastructure::first();
        $this->assertNotNull($infra);

        // Front Index
        $responseIndex = $this->get(route('front.infrastructures.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Nos infrastructures');
        $responseIndex->assertSee($infra->name);

        // Front Show
        $responseShow = $this->get(route('front.infrastructures.show', $infra->reference_code ?? $infra->id));
        $responseShow->assertStatus(200);
        $responseShow->assertSee($infra->name);
        $responseShow->assertSee('État du site');
    }

    public function test_citizen_can_report_malfunction_on_infrastructure(): void
    {
        $infra = Infrastructure::first();
        $this->assertNotNull($infra);

        $reportData = [
            'malfunction_type' => 'Fuite ou rupture de canalisation',
            'priority' => 'critical',
            'description' => 'Fuite violente constatée avec jet d eau sous pression.',
            'reporter_name' => 'Kamel Jlassi',
            'reporter_phone' => '+216 98 765 432',
        ];

        $response = $this->post(route('front.infrastructures.report', $infra->reference_code ?? $infra->id), $reportData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('maintenances', [
            'infrastructure_id' => $infra->id,
            'status' => 'reported',
            'type' => 'Corrective',
        ]);

        $created = Maintenance::where('infrastructure_id', $infra->id)
            ->where('status', 'reported')
            ->latest('id')
            ->first();

        $this->assertNotNull($created);
        $this->assertStringContainsString('Kamel Jlassi', $created->description);
        $this->assertStringContainsString('Fuite violente', $created->description);

        $created->delete();
    }

    public function test_infrastructure_date_validation_rejects_incoherent_dates(): void
    {
        $admin = $this->getAdminUser();
        $zone = Zone::first();

        // 1. Installation date cannot be in the future
        $response1 = $this->actingAs($admin)->post(route('infrastructures.store'), [
            'name' => 'Station Incoherente 1',
            'zone_id' => $zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'medium',
            'installation_date' => now()->addYear()->format('Y-m-d'),
        ]);
        $response1->assertSessionHasErrors(['installation_date']);

        // 2. Commissioning date cannot precede installation date
        $response2 = $this->actingAs($admin)->post(route('infrastructures.store'), [
            'name' => 'Station Incoherente 2',
            'zone_id' => $zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'medium',
            'installation_date' => '2024-01-01',
            'commissioning_date' => '2023-01-01',
        ]);
        $response2->assertSessionHasErrors(['commissioning_date']);

        // 3. Next maintenance cannot precede last maintenance
        $response3 = $this->actingAs($admin)->post(route('infrastructures.store'), [
            'name' => 'Station Incoherente 3',
            'zone_id' => $zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'medium',
            'installation_date' => '2023-01-01',
            'last_maintenance_date' => now()->subDays(5)->format('Y-m-d'),
            'next_maintenance_date' => now()->subDays(10)->format('Y-m-d'),
        ]);
        $response3->assertSessionHasErrors(['next_maintenance_date']);
    }

    public function test_citizen_report_validation_rejects_invalid_inputs(): void
    {
        $infra = Infrastructure::first();
        $this->assertNotNull($infra);

        // Short description (less than 10 chars)
        $response = $this->post(route('front.infrastructures.report', $infra->reference_code ?? $infra->id), [
            'malfunction_type' => 'Fuite',
            'priority' => 'critical',
            'description' => 'Court',
            'reporter_phone' => 'abc-invalid-phone',
        ]);

        $response->assertSessionHasErrors(['description', 'reporter_phone']);
    }

    public function test_infrastructure_name_validation_requires_letters(): void
    {
        $admin = $this->getAdminUser();
        $zone = Zone::first() ?? Zone::factory()->create();

        // Digits-only name should be rejected
        $response = $this->actingAs($admin)->post(route('infrastructures.store'), [
            'name' => '12345678',
            'zone_id' => $zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'medium',
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertEquals(
            'Le nom de l’infrastructure doit contenir des lettres (seuls les lettres, chiffres, espaces et tirets sont acceptés).',
            session('errors')->first('name')
        );
    }

    public function test_infrastructure_form_renders_novalidate_and_feedback_system(): void
    {
        $admin = $this->getAdminUser();
        $response = $this->actingAs($admin)->get(route('infrastructures.create'));

        $response->assertStatus(200);
        $response->assertSee('novalidate');
        $response->assertSee('validated-form');
        $response->assertSee('data-rules=');
        $response->assertSee('validation-feedback');
    }
}


