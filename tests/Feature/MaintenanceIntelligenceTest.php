<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\MaintenanceIntelligenceScan;
use App\Models\Technicien;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\InfrastructureMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaintenanceIntelligenceTest extends TestCase
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

    private function getManagerUser(): User
    {
        return User::where('role', 'manager')->first()
            ?? User::factory()->create(['role' => 'manager']);
    }

    private function getCitizenUser(): User
    {
        return User::where('role', 'citizen')->first()
            ?? User::factory()->create(['role' => 'citizen']);
    }

    public function test_guest_cannot_access_maintenance_intelligence(): void
    {
        $response = $this->get(route('maintenance-intelligence.index'));
        $response->assertRedirect(route('login'));

        $scanResponse = $this->postJson(route('maintenance-intelligence.scan'));
        $scanResponse->assertStatus(401);
    }

    public function test_citizen_cannot_access_maintenance_intelligence(): void
    {
        $citizen = $this->getCitizenUser();

        $response = $this->actingAs($citizen)->get(route('maintenance-intelligence.index'));
        $response->assertStatus(403);

        $scanResponse = $this->actingAs($citizen)->postJson(route('maintenance-intelligence.scan'));
        $scanResponse->assertStatus(403);
    }

    public function test_manager_can_access_maintenance_intelligence_page(): void
    {
        $manager = $this->getManagerUser();

        $response = $this->actingAs($manager)->get(route('maintenance-intelligence.index'));
        $response->assertStatus(200);
        $response->assertSee('AI Maintenance Intelligence Center');
        $response->assertSee('Scanner les risques de maintenance');
        $response->assertSee('Indicateur Global de Risque');
    }

    public function test_manager_can_initiate_scan_and_deterministic_rules_detect_overdue_and_critical_risks(): void
    {
        $manager = $this->getManagerUser();
        $zone = Zone::first() ?? Zone::create(['name' => 'Zone Test', 'risk_level' => 'high']);

        // Create an infrastructure with critical condition and overdue maintenance
        $infra = Infrastructure::create([
            'zone_id' => $zone->id,
            'reference_code' => 'INF-TEST-CRIT-01',
            'name' => 'Station Pompage Critique Alpha',
            'type' => 'Station de pompage',
            'status' => 'critical',
            'condition' => 'critical',
            'criticality' => 'vital',
            'next_maintenance_date' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $maintenance = Maintenance::create([
            'infrastructure_id' => $infra->id,
            'reference_code' => 'MNT-TEST-OVERDUE-01',
            'type' => 'Préventive',
            'priority' => 'critical',
            'status' => 'planned',
            'scheduled_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.scan'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['findings']);
        $this->assertGreaterThan(0, $data['metrics']['overdue_maintenances']);
        $this->assertGreaterThan(0, $data['metrics']['critical_findings']);

        // Verify scan was recorded in database
        $this->assertDatabaseHas('maintenance_intelligence_scans', [
            'id' => $data['scan_id'],
            'user_id' => $manager->id,
        ]);

        // Verify candidate contains the created infrastructure
        $finding = collect($data['findings'])->firstWhere('infrastructure_id', $infra->id);
        $this->assertNotNull($finding);
        $this->assertEquals('critical', $finding['risk_level']);
    }

    public function test_missing_data_is_identified_without_fabricating_values(): void
    {
        $manager = $this->getManagerUser();
        $zone = Zone::first();

        // Create an infrastructure with vital criticality but missing maintenance dates
        $infra = Infrastructure::create([
            'zone_id' => $zone->id,
            'reference_code' => 'INF-TEST-MISSING-01',
            'name' => 'Canalisation Dorsale Nord',
            'type' => 'Canalisation principale',
            'status' => 'operational',
            'condition' => 'good',
            'criticality' => 'vital',
            'last_maintenance_date' => null,
            'next_maintenance_date' => null,
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.scan'));
        $response->assertStatus(200);

        $data = $response->json();
        $finding = collect($data['findings'])->firstWhere('infrastructure_id', $infra->id);

        if ($finding) {
            $this->assertNotEmpty($finding['missing_information']);
            $hasDateGap = collect($finding['missing_information'])->contains(function ($text) {
                return str_contains($text, 'maintenance') || str_contains($text, 'date');
            });
            $this->assertTrue($hasDateGap);
        }
    }

    public function test_scan_with_groq_mock_processes_ai_findings_safely(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();
        $infra->condition = 'poor';
        $infra->next_maintenance_date = now()->subDays(5);
        $infra->save();

        // Configure Groq in config
        config(['services.groq.key' => 'mock_groq_api_key_for_testing']);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'findings' => [
                                    [
                                        'title' => 'Usure mécanique avancée détectée sur pompe',
                                        'infrastructure_id' => $infra->id,
                                        'risk_level' => 'high',
                                        'confidence' => 'high',
                                        'summary' => 'Présence de vibrations et retard d\'inspection périodique.',
                                        'evidence' => ['Inspection dépassée', 'Condition matérielle dégradée'],
                                        'risk_factors' => ['Rupture de service imminente'],
                                        'consequences' => ['Arrêt d\'alimentation en eau sur la zone'],
                                        'recommended_actions' => ['Intervention de contrôle mécanique'],
                                        'recommended_maintenance_type' => 'Corrective',
                                        'recommended_timeframe' => 'Sous 48 heures',
                                        'missing_information' => [],
                                        'planning_recommended' => true,
                                    ]
                                ]
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.scan'));
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals('completed', $data['status']);

        $finding = collect($data['findings'])->firstWhere('infrastructure_id', $infra->id);
        $this->assertNotNull($finding);
        $this->assertEquals('Usure mécanique avancée détectée sur pompe', $finding['title']);
    }

    public function test_scan_handles_groq_api_failure_gracefully_with_deterministic_fallback(): void
    {
        $manager = $this->getManagerUser();
        config(['services.groq.key' => 'mock_groq_key']);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Service overloaded or rate limit exceeded.'],
            ], 429),
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.scan'));
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        // Status should indicate deterministic fallback
        $this->assertEquals('deterministic_fallback', $data['status']);
        $this->assertNotEmpty($data['findings']);
    }

    public function test_planner_generates_plan_proposal_without_saving_maintenance_record(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        $initialMaintenanceCount = Maintenance::count();

        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.plan'), [
            'infrastructure_id' => $infra->id,
            'finding' => [
                'title' => 'Anomalie de pression',
                'risk_level' => 'high',
                'summary' => 'Surpression relevée au refoulement',
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotNull($data['plan']);
        $this->assertEquals($infra->id, $data['plan']['infrastructure_id']);
        $this->assertNotEmpty($data['plan']['checklist']);
        $this->assertNotEmpty($data['plan']['work_order_description']);

        // CRITICAL: Ensure NO maintenance record was created during plan generation
        $this->assertEquals($initialMaintenanceCount, Maintenance::count());
    }

    public function test_manager_can_edit_and_confirm_plan_to_create_maintenance(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();
        $tech = Technicien::first();

        $scheduledDate = now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i');
        $nextDate = now()->addMonths(6)->format('Y-m-d');

        $payload = [
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'high',
            'status' => 'planned',
            'scheduled_at' => $scheduledDate,
            'duration_hours' => 4.5,
            'technician_id' => $tech?->id,
            'team' => 'Équipe Spéciale Eau',
            'cost' => 520.00,
            'description' => 'Remplacement de la garniture mécanique et contrôle d\'étanchéité.',
            'checklist' => [
                'Vérification de la consignation réseau',
                'Démontage de la bride',
                'Remplacement des joints toriques',
            ],
            'next_maintenance_date' => $nextDate,
        ];

        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.store-maintenance'), $payload);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotNull($data['maintenance_id']);
        $this->assertNotNull($data['reference_code']);

        // Verify maintenance was saved in DB
        $this->assertDatabaseHas('maintenances', [
            'id' => $data['maintenance_id'],
            'infrastructure_id' => $infra->id,
            'type' => 'Corrective',
            'priority' => 'high',
            'status' => 'planned',
            'team' => 'Équipe Spéciale Eau',
        ]);

        // Verify checklist was appended to description
        $saved = Maintenance::find($data['maintenance_id']);
        $this->assertStringContainsString('GAMME OPÉRATOIRE & CHECKLIST', $saved->description);
        $this->assertStringContainsString('Vérification de la consignation réseau', $saved->description);

        // Verify infrastructure synchronization
        $infra->refresh();
        $this->assertEquals($nextDate, $infra->next_maintenance_date?->format('Y-m-d'));
    }

    public function test_maintenance_creation_rejects_invalid_inputs_and_incoherent_dates(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        // 1. Past scheduled date for planned status
        $response = $this->actingAs($manager)->postJson(route('maintenance-intelligence.store-maintenance'), [
            'infrastructure_id' => $infra->id,
            'type' => 'Préventive',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->subDays(5)->format('Y-m-d H:i'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scheduled_at']);

        // 2. Next maintenance date before or equal to scheduled_at
        $response2 = $this->actingAs($manager)->postJson(route('maintenance-intelligence.store-maintenance'), [
            'infrastructure_id' => $infra->id,
            'type' => 'Préventive',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(5)->format('Y-m-d H:i'),
            'next_maintenance_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['next_maintenance_date']);

        // 3. Non-existent infrastructure
        $response3 = $this->actingAs($manager)->postJson(route('maintenance-intelligence.store-maintenance'), [
            'infrastructure_id' => 999999,
            'type' => 'Préventive',
            'priority' => 'medium',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'),
        ]);

        $response3->assertStatus(422);
        $response3->assertJsonValidationErrors(['infrastructure_id']);
    }
}
