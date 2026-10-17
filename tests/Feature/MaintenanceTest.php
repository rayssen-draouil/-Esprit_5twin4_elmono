<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\Technicien;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\InfrastructureMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
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

    public function test_guest_cannot_access_maintenances(): void
    {
        $response = $this->get(route('maintenances.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_citizen_cannot_access_maintenances(): void
    {
        $citizen = $this->getCitizenUser();
        $response = $this->actingAs($citizen)->get(route('maintenances.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_maintenances_index(): void
    {
        $admin = $this->getAdminUser();
        $response = $this->actingAs($admin)->get(route('maintenances.index'));

        $response->assertStatus(200);
        $response->assertSee('Gestion des opérations de maintenance');
        $response->assertSee('Total opérations');
    }

    public function test_admin_can_create_maintenance_and_sync_with_infrastructure(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();
        $this->assertNotNull($infra);

        $data = [
            'infrastructure_id' => $infra->id,
            'type' => 'Préventive',
            'priority' => 'high',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'description' => 'Test de vérification pompe hydraulique',
            'cost' => 750.50,
            'duration_hours' => 2.5,
        ];

        $response = $this->actingAs($admin)->post(route('maintenances.store'), $data);
        $response->assertRedirect();

        $this->assertDatabaseHas('maintenances', [
            'infrastructure_id' => $infra->id,
            'description' => 'Test de vérification pompe hydraulique',
        ]);

        $created = Maintenance::where('description', 'Test de vérification pompe hydraulique')->first();
        $this->assertNotNull($created->reference_code);
        $this->assertTrue(str_starts_with($created->reference_code, 'MNT-'));

        $created->delete();
    }

    public function test_smart_overdue_logic_and_completed_status_sync(): void
    {
        $infra = Infrastructure::first();

        // 1. Create an overdue maintenance (scheduled in past + planned)
        $mOverdue = Maintenance::create([
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'critical',
            'status' => 'planned',
            'scheduled_at' => now()->subDays(5),
            'description' => 'Opération en retard test',
        ]);

        $this->assertTrue($mOverdue->is_overdue);
        $this->assertEquals('overdue', $mOverdue->display_status);
        $this->assertEquals('En retard', $mOverdue->display_status_label);

        // 2. Complete maintenance and verify parent infrastructure sync
        $admin = $this->getAdminUser();
        $response = $this->actingAs($admin)->put(route('maintenances.update', $mOverdue), [
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'critical',
            'status' => 'completed',
            'scheduled_at' => $mOverdue->scheduled_at->format('Y-m-d H:i:s'),
            'result' => 'Réparation effectuée et validée avec succès.',
        ]);

        $response->assertRedirect(route('maintenances.show', $mOverdue));

        $mOverdue->refresh();
        $this->assertFalse($mOverdue->is_overdue);
        $this->assertEquals('completed', $mOverdue->status);
        $this->assertNotNull($mOverdue->completed_at);

        $infra->refresh();
        $this->assertEquals(now()->format('Y-m-d'), $infra->last_maintenance_date?->format('Y-m-d'));

        $mOverdue->delete();
    }

    public function test_admin_can_start_reported_maintenance(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        // Create a reported maintenance
        $reported = Maintenance::create([
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'critical',
            'status' => 'reported',
            'scheduled_at' => now(),
            'description' => 'Signalement citoyen à démarrer par admin',
        ]);

        $this->assertEquals('reported', $reported->status);
        $this->assertNull($reported->started_at);

        // Admin starts the maintenance
        $response = $this->actingAs($admin)->post(route('maintenances.start', $reported));

        $response->assertRedirect(route('maintenances.show', $reported));
        $response->assertSessionHas('success');

        $reported->refresh();
        $this->assertEquals('in_progress', $reported->status);
        $this->assertNotNull($reported->started_at);

        $infra->refresh();
        $this->assertEquals('maintenance', $infra->status);

        $reported->delete();
    }

    public function test_maintenance_validation_requires_future_date_for_planned_status(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        $data = [
            'infrastructure_id' => $infra->id,
            'type' => 'Préventive',
            'priority' => 'high',
            'status' => 'planned',
            'scheduled_at' => now()->subDays(3)->format('Y-m-d H:i:s'), // in the past!
            'description' => 'Test date dans le passé pour planned',
        ];

        $response = $this->actingAs($admin)->post(route('maintenances.store'), $data);
        $response->assertSessionHasErrors(['scheduled_at']);
    }

    public function test_maintenance_validation_requires_result_when_completed(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        // Completed without result should fail
        $data = [
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'medium',
            'status' => 'completed',
            'scheduled_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
            'result' => '', // empty result!
            'description' => 'Test sans compte-rendu',
        ];

        $response = $this->actingAs($admin)->post(route('maintenances.store'), $data);
        $response->assertSessionHasErrors(['result']);
    }

    public function test_maintenance_validation_chronological_order_of_execution(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        // Completed at before started at should fail
        $data = [
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'medium',
            'status' => 'in_progress',
            'scheduled_at' => now()->format('Y-m-d H:i:s'),
            'started_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
            'completed_at' => now()->subHours(4)->format('Y-m-d H:i:s'), // completed before started!
            'description' => 'Test chronologie impossible',
        ];

        $response = $this->actingAs($admin)->post(route('maintenances.store'), $data);
        $response->assertSessionHasErrors(['completed_at']);
    }

    public function test_maintenance_team_validation_contains_only_letters(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        // Team containing digits / invalid characters should fail with "letters only" message
        $data = [
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'team' => 'Equipe 12345$$',
        ];

        $response = $this->actingAs($admin)->post(route('maintenances.store'), $data);
        $response->assertSessionHasErrors(['team']);
        $this->assertEquals(
            'Le nom de l’équipe ne doit contenir que des lettres, espaces ou tirets.',
            session('errors')->first('team')
        );
    }

    public function test_maintenance_form_renders_novalidate_and_feedback_system(): void
    {
        $admin = $this->getAdminUser();
        $response = $this->actingAs($admin)->get(route('maintenances.create'));

        $response->assertStatus(200);
        $response->assertSee('novalidate');
        $response->assertSee('validated-form');
        $response->assertSee('data-rules=');
        $response->assertSee('validation-feedback');
    }
}


