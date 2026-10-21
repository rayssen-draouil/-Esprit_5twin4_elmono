<?php

namespace Tests\Feature;

use App\Models\Equipement;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_technician_user_with_a_linked_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sarah Petit',
            'email' => 'sarah.petit@example.com',
            'role' => 'technician',
            'speciality' => 'Hydraulique',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'sarah.petit@example.com')->firstOrFail();
        $this->assertSame('technician', $user->role);
        $this->assertDatabaseHas('techniciens', [
            'user_id' => $user->id,
            'name' => 'Sarah Petit',
            'email' => 'sarah.petit@example.com',
            'speciality' => 'Hydraulique',
            'status' => 'available',
        ]);
    }

    public function test_admin_cannot_create_a_technician_without_a_speciality(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Sarah Petit',
                'email' => 'sarah.petit@example.com',
                'role' => 'technician',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors([
                'speciality' => 'Veuillez renseigner la spécialité du technicien.',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'sarah.petit@example.com']);
    }

    public function test_manager_sees_the_intervention_overview_and_can_filter_the_styled_list(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'team' => 'Équipe hydraulique',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'team' => 'Équipe terminée',
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
        ]);

        $this->actingAs($manager)
            ->get(route('interventions.index', ['status' => 'planned']))
            ->assertOk()
            ->assertSee('Résumé des interventions')
            ->assertSee('Interventions au total')
            ->assertSee('2')
            ->assertSee('Suivi des interventions')
            ->assertSee('Équipe hydraulique')
            ->assertDontSee('Équipe terminée')
            ->assertDontSee('résultat(s)')
            ->assertSee('intervention-table');
    }

    public function test_technician_with_an_in_progress_intervention_is_displayed_as_busy(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $technicianUser = User::factory()->create(['role' => 'technician']);
        $technician = \App\Models\Technicien::create([
            'user_id' => $technicianUser->id,
            'name' => $technicianUser->name,
            'email' => $technicianUser->email,
            'status' => 'available',
        ]);
        $intervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe en cours',
            'scheduled_at' => now(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($manager)
            ->get(route('techniciens.index'))
            ->assertOk()
            ->assertSee('Occupé');

        $this->get(route('techniciens.index', ['status' => 'busy']))
            ->assertOk()
            ->assertSee($technician->name)
            ->assertSee('Occupé');

        $this->get(route('techniciens.index', ['status' => 'available']))
            ->assertOk()
            ->assertDontSee($technician->name);

        $this->get(route('techniciens.show', $technician))
            ->assertOk()
            ->assertSee('Occupé');

        $intervention->update(['status' => 'completed']);

        $this->get(route('techniciens.index'))
            ->assertOk()
            ->assertSee('Disponible');
        $this->get(route('techniciens.index', ['status' => 'busy']))
            ->assertOk()
            ->assertDontSee($technician->name);
    }

    public function test_equipment_is_added_to_the_shared_inventory_without_a_technician_owner(): void
    {
        $firstEquipment = Equipement::create([
            'name' => 'Multimètre',
            'type' => 'Mesure',
        ]);
        $secondEquipment = Equipement::create([
            'name' => 'Détecteur de fuite',
            'type' => 'Détection',
        ]);

        $this->assertCount(2, Equipement::all());
        $this->assertDatabaseHas('equipements', ['id' => $firstEquipment->id, 'name' => 'Multimètre']);
        $this->assertInstanceOf(Equipement::class, $secondEquipment);
    }
}
