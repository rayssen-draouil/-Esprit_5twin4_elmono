<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\TechnicianSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TechnicianAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_technician_is_a_user_carrying_the_technician_role(): void
    {
        $technician = User::factory()->create([
            'role' => 'technician',
            'email' => 'm.trabelsi@sonede.tn',
            'name' => 'Ing. Mohamed Trabelsi',
            'speciality' => 'Électromécanique',
            'status' => 'available',
        ]);

        $this->assertTrue($technician->isTechnician());
        $this->assertSame('Technicien', $technician->roleLabel());
        $this->assertSame('Électromécanique', $technician->speciality);
        $this->assertSame('Disponible', $technician->status_label);
    }

    public function test_the_technician_scope_only_returns_technician_users(): void
    {
        User::factory()->create(['role' => 'technician', 'email' => 'tech@aquasecure.tn']);
        User::factory()->create(['role' => 'manager', 'email' => 'manager@aquasecure.tn']);
        User::factory()->create(['role' => 'citizen', 'email' => 'citizen@aquasecure.tn']);

        $this->assertCount(1, User::technicians()->get());
        $this->assertSame('tech@aquasecure.tn', User::technicians()->first()->email);
    }

    public function test_the_technician_seeder_creates_the_reference_workforce(): void
    {
        $this->seed(TechnicianSeeder::class);

        $this->assertCount(6, User::technicians()->get());

        $trabelsi = User::where('email', 'm.trabelsi@sonede.tn')->first();
        $this->assertNotNull($trabelsi);
        $this->assertSame('technician', $trabelsi->role);
        $this->assertSame('Électromécanique & Groupes Motopompes (SONEDE)', $trabelsi->speciality);
        $this->assertNotNull($trabelsi->email_verified_at);
        $this->assertTrue(Hash::check(User::DEFAULT_PASSWORD, $trabelsi->password));
    }

    public function test_the_technician_seeder_never_downgrades_an_admin_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'salem@gmail.com',
        ]);

        $this->seed(TechnicianSeeder::class);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_the_technician_seeder_promotes_a_citizen_account(): void
    {
        $citizen = User::factory()->create([
            'role' => 'citizen',
            'email' => 'k.zouari@sonede.tn',
        ]);

        $this->seed(TechnicianSeeder::class);

        $this->assertSame('technician', $citizen->fresh()->role);
    }

    public function test_a_technician_receives_the_maintenances_assigned_to_him(): void
    {
        $zone = Zone::create([
            'name' => 'Zone Test',
            'address' => 'Tunis',
            'description' => 'Zone de test',
            'risk_level' => 'low',
        ]);

        $infrastructure = Infrastructure::create([
            'name' => 'Station Test',
            'zone_id' => $zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'installation_date' => '2024-01-01',
        ]);

        $technician = User::factory()->create(['role' => 'technician']);
        $other = User::factory()->create(['role' => 'technician']);

        Maintenance::create([
            'infrastructure_id' => $infrastructure->id,
            'reference_code' => 'MNT-TECH-1',
            'type' => 'Préventive',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(2),
            'technician_id' => $technician->id,
        ]);

        Maintenance::create([
            'infrastructure_id' => $infrastructure->id,
            'reference_code' => 'MNT-TECH-2',
            'type' => 'Préventive',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(3),
            'technician_id' => $other->id,
        ]);

        $this->assertCount(1, $technician->maintenances);
        $this->assertSame('MNT-TECH-1', $technician->maintenances->first()->reference_code);
    }

    public function test_the_technician_list_displays_the_technician_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        User::factory()->create([
            'role' => 'technician',
            'name' => 'Techn. Ahmed Ben Salem',
            'email' => 'a.bensalem@agriculture.gov.tn',
            'speciality' => 'Génie Civil',
        ]);

        $response = $this->actingAs($admin)->get(route('techniciens.index'));

        $response->assertOk()
            ->assertSee('Techn. Ahmed Ben Salem')
            ->assertSee('a.bensalem@agriculture.gov.tn')
            ->assertSee('Génie Civil');
    }

    public function test_a_technician_user_reaches_the_intervention_workspace(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->get(route('technicien.interventions.index'))
            ->assertOk();

        $this->actingAs($technician)
            ->get(route('dashboard'))
            ->assertRedirect(route('technicien.interventions.index'));
    }

    public function test_a_manager_can_create_a_technician_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('techniciens.store'), [
            'name' => 'Techn. Youssef Mansouri',
            'email' => 'y.mansouri@sonede.tn',
            'phone' => '+216 75 290 300',
            'speciality' => 'Dessalement d\'Eau de Mer',
            'status' => 'available',
        ]);

        $response->assertRedirect();

        $technician = User::where('email', 'y.mansouri@sonede.tn')->first();

        $this->assertNotNull($technician);
        $this->assertSame('technician', $technician->role);
        $this->assertSame('Techn. Youssef Mansouri', $technician->name);
        $this->assertSame('Dessalement d\'Eau de Mer', $technician->speciality);
        $this->assertTrue(Hash::check(User::DEFAULT_PASSWORD, $technician->password));
    }

    public function test_a_manager_can_update_a_technician_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create([
            'role' => 'technician',
            'email' => 'k.zouari@sonede.tn',
            'name' => 'Techn. Karim Zouari',
        ]);

        $this->actingAs($admin)->put(route('techniciens.update', $technician), [
            'name' => 'Techn. Karim Zouari',
            'email' => 'k.zouari@sonede.tn',
            'speciality' => 'Détection de Fuites',
            'status' => 'busy',
        ])->assertRedirect();

        $technician->refresh();

        $this->assertSame('Détection de Fuites', $technician->speciality);
        $this->assertSame('busy', $technician->status);
        $this->assertSame('technician', $technician->role);
    }

    public function test_the_admin_user_form_exposes_the_technician_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.users.create'));

        $response->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('name="speciality"', false)
            ->assertSee('name="status"', false);
    }

    public function test_a_manager_cannot_be_reached_through_a_technician_route(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->get(route('techniciens.show', $manager))
            ->assertNotFound();
    }

    public function test_an_account_assigned_as_intervenant_stays_reachable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);

        $zone = Zone::create([
            'name' => 'Zone Intervenant',
            'address' => 'Tunis',
            'description' => 'Zone de test',
            'risk_level' => 'low',
        ]);

        $infrastructure = Infrastructure::create([
            'name' => 'Station Intervenant',
            'zone_id' => $zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'installation_date' => '2024-01-01',
        ]);

        Maintenance::create([
            'infrastructure_id' => $infrastructure->id,
            'reference_code' => 'MNT-INTERV-1',
            'type' => 'Préventive',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(2),
            'technician_id' => $manager->id,
        ]);

        $this->actingAs($admin)
            ->get(route('techniciens.show', $manager))
            ->assertOk()
            ->assertSee('MNT-INTERV-1');
    }
}
