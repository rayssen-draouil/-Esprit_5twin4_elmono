<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\InfrastructureMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationTest extends TestCase
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

    public function test_infrastructures_front_page_renders_clean_pagination(): void
    {
        $zone = Zone::first();
        for ($i = 1; $i <= 6; $i++) {
            Infrastructure::create([
                'zone_id' => $zone->id,
                'name' => "Extra Infra $i",
                'type' => 'Station de pompage',
                'reference_code' => "INF-EXTRA-$i",
                'status' => 'operational',
                'condition' => 'good',
                'criticality' => 'medium',
            ]);
        }

        $response = $this->get(route('front.infrastructures.index'));

        $response->assertStatus(200);
        $response->assertSee('aquasecure-pagination');
        $response->assertSee('pagination-pages');
        $response->assertSee('pagination-svg');
        // Ensure the old broken Tailwind full-width classes are not unstyled
        $response->assertDontSee('class="w-5 h-5"');
    }

    public function test_admin_maintenances_renders_clean_pagination(): void
    {
        $admin = $this->getAdminUser();
        $infra = Infrastructure::first();

        for ($i = 1; $i <= 5; $i++) {
            Maintenance::create([
                'infrastructure_id' => $infra->id,
                'reference_code' => "MNT-EXTRA-$i",
                'type' => 'Préventive',
                'priority' => 'medium',
                'status' => 'planned',
                'scheduled_at' => now()->addDays($i)->format('Y-m-d H:i:s'),
                'description' => "Extra maintenance $i",
            ]);
        }

        $response = $this->actingAs($admin)->get(route('maintenances.index'));

        $response->assertStatus(200);
        $response->assertSee('aquasecure-pagination');
        $response->assertSee('pagination-pages');
        $response->assertSee('pagination-svg');
        $response->assertDontSee('class="w-5 h-5"');
    }
}
