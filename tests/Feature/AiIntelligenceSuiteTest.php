<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\Signalement;
use App\Models\Technicien;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\InfrastructureMaintenanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntelligenceSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(InfrastructureMaintenanceSeeder::class);
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

    /* =========================================================================
     * FEATURE 1: AI Infrastructure Health Analyst Tests
     * ========================================================================= */

    public function test_guest_cannot_run_infrastructure_health_analysis(): void
    {
        $infra = Infrastructure::first();
        $response = $this->postJson(route('infrastructures.ai-health', $infra));
        $response->assertStatus(401);
    }

    public function test_citizen_cannot_run_infrastructure_health_analysis(): void
    {
        $citizen = $this->getCitizenUser();
        $infra = Infrastructure::first();

        $response = $this->actingAs($citizen)->postJson(route('infrastructures.ai-health', $infra));
        $response->assertStatus(403);
    }

    public function test_manager_can_analyze_infrastructure_health_with_mocked_groq(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        config(['services.groq.key' => 'mock_test_key']);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'infrastructure_id' => $infra->id,
                                'assessment_title' => 'Diagnostic global du réservoir',
                                'condition_summary' => 'Équipement en état d\'usure moyenne nécessitant une vérification.',
                                'overall_risk_level' => 'medium',
                                'top_risk_factors' => [
                                    [
                                        'title' => 'Échéance de contrôle dépassée',
                                        'explanation' => 'Aucune révision n\'a été consignée récemment.',
                                        'evidence' => 'Date de dernière révision inconnue',
                                        'urgency' => 'Sous 15 jours',
                                    ]
                                ],
                                'recommended_actions' => [
                                    [
                                        'action' => 'Planifier un contrôle d\'étanchéité',
                                        'reason' => 'Éviter les pertes en eau',
                                        'suggested_urgency' => 'Normale',
                                        'review_level' => 'Technicien',
                                    ]
                                ],
                                'maintenance_checklist' => [
                                    'Contrôle des vannes',
                                    'Vérification des capteurs de niveau'
                                ],
                                'missing_information' => ['Historique complet des coûts'],
                                'maintenance_recommended' => true,
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $initialMaintenanceCount = Maintenance::count();

        $response = $this->actingAs($manager)->postJson(route('infrastructures.ai-health', $infra));
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('analysis', $data);

        $analysis = $data['analysis'];
        $this->assertEquals('medium', $analysis['overall_risk_level']);
        $this->assertEquals('Diagnostic global du réservoir', $analysis['assessment_title']);

        // Verify analysis was persisted in database
        $infra->refresh();
        $this->assertNotNull($infra->ai_health_analysis);
        $this->assertNotNull($infra->ai_health_analyzed_at);
        $this->assertEquals('medium', $infra->ai_health_analysis['overall_risk_level']);

        // Verify analysis does NOT create maintenance records automatically
        $this->assertEquals($initialMaintenanceCount, Maintenance::count());
    }

    public function test_infrastructure_analysis_handles_groq_failure_with_deterministic_fallback(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        config(['services.groq.key' => 'mock_test_key']);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $response = $this->actingAs($manager)->postJson(route('infrastructures.ai-health', $infra));
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertFalse($data['analysis']['is_ai_generated']);
        $this->assertNotEmpty($data['analysis']['evidence']);
    }

    /* =========================================================================
     * FEATURE 2: Smart Citizen Report Analyzer Tests
     * ========================================================================= */

    public function test_manager_can_analyze_citizen_report_and_preserves_original_text(): void
    {
        $manager = $this->getManagerUser();

        $signalement = Signalement::create([
            'user_id' => $manager->id,
            'type' => 'Autre',
            'description' => 'Grosse fuite d\'eau propre sur la chaussée principale près de l\'école.',
            'localisation' => 'Avenue Habib Bourguiba, Tunis',
            'priority' => 'low',
            'status' => 'pending',
        ]);

        config(['services.groq.key' => 'mock_test_key']);

        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'report_id' => $signalement->id,
                                'suggested_category' => 'fuite',
                                'suggested_severity' => 'high',
                                'suggested_priority' => 'high',
                                'summary' => 'Rupture présumée de canalisation sous voirie avec écoulement continu.',
                                'location_details' => 'Avenue Habib Bourguiba',
                                'recommended_next_action' => 'Dépêcher une équipe d\'intervention d\'urgence.',
                                'possible_duplicate_reports' => [],
                                'duplicate_reason' => null,
                                'uncertainties' => [],
                                'requires_human_review' => true,
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($manager)->postJson(route('signalements.ai-analyze', $signalement));
        $response->assertStatus(200);

        $signalement->refresh();

        // 1. Citizen original description must NEVER be overwritten
        $this->assertEquals(
            'Grosse fuite d\'eau propre sur la chaussée principale près de l\'école.',
            $signalement->description
        );

        // 2. AI analysis is saved separately
        $this->assertEquals('completed', $signalement->ai_status);
        $this->assertNotNull($signalement->ai_analysis);
        $this->assertEquals('fuite', $signalement->ai_analysis['suggested_category']);
        $this->assertEquals('high', $signalement->ai_analysis['suggested_priority']);
    }

    public function test_manager_can_accept_ai_report_suggestions(): void
    {
        $manager = $this->getManagerUser();

        $signalement = Signalement::create([
            'user_id' => $manager->id,
            'type' => 'Autre',
            'description' => 'Fuite constatée',
            'priority' => 'low',
            'status' => 'pending',
            'ai_status' => 'completed',
            'ai_analysis' => [
                'suggested_category' => 'fuite',
                'suggested_priority' => 'high',
                'summary' => 'Fuite sur réseau d\'eau',
            ],
        ]);

        $response = $this->actingAs($manager)->postJson(route('signalements.ai-accept', $signalement));
        $response->assertStatus(200);

        $signalement->refresh();
        $this->assertEquals('Fuite', $signalement->type);
        $this->assertEquals('high', $signalement->priority);
        $this->assertEquals('fuite', $signalement->manager_category);
        $this->assertEquals('high', $signalement->manager_severity);
        $this->assertNotNull($signalement->ai_reviewed_at);
        $this->assertEquals($manager->id, $signalement->ai_reviewed_by);
    }

    public function test_manager_can_edit_report_classification_manually(): void
    {
        $manager = $this->getManagerUser();

        $signalement = Signalement::create([
            'user_id' => $manager->id,
            'type' => 'Autre',
            'description' => 'Baisse anormale de pression constatée',
            'priority' => 'low',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)->postJson(route('signalements.ai-edit', $signalement), [
            'type' => 'Pression',
            'priority' => 'medium',
        ]);

        $response->assertStatus(200);

        $signalement->refresh();
        $this->assertEquals('Pression', $signalement->type);
        $this->assertEquals('medium', $signalement->priority);
        $this->assertEquals('pression', $signalement->manager_category);
        $this->assertEquals('medium', $signalement->manager_severity);
        $this->assertNotNull($signalement->ai_reviewed_at);
    }

    /* =========================================================================
     * FEATURE 3: AI Technician Matchmaker Tests
     * ========================================================================= */

    public function test_technician_recommendation_filters_and_ranks_eligible_technicians(): void
    {
        $manager = $this->getManagerUser();

        // Create 2 active technicians and 1 inactive technician
        $techActive = Technicien::create([
            'name' => 'Kamel Ben Salah',
            'email' => 'kamel.test@aquasecure.tn',
            'phone' => '+216 71 100 200',
            'speciality' => 'Électromécanique et Pompage',
            'status' => 'available',
        ]);

        $techInactive = Technicien::create([
            'name' => 'Inactif Test',
            'email' => 'inactif.test@aquasecure.tn',
            'phone' => '+216 71 100 300',
            'speciality' => 'Électromécanique et Pompage',
            'status' => 'inactive',
        ]);

        $infra = Infrastructure::first();

        $response = $this->actingAs($manager)->postJson(route('maintenances.recommend-technicians'), [
            'type' => 'Corrective',
            'priority' => 'high',
            'infrastructure_id' => $infra->id,
            'description' => 'Révision complète pompe de refoulement',
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);

        $rankings = $data['recommendations']['candidate_rankings'];
        $this->assertNotEmpty($rankings);

        // Security check: Inactive technician must NEVER be recommended
        $techIds = collect($rankings)->pluck('technician_id')->all();
        $this->assertNotContains($techInactive->id, $techIds);
        $this->assertContains($techActive->id, $techIds);
    }

    public function test_technician_recommendation_sanitizes_fake_technician_ids_from_ai(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        $techReal = Technicien::create([
            'name' => 'Real Technician',
            'email' => 'real.tech@aquasecure.tn',
            'speciality' => 'Hydraulique générale',
            'status' => 'available',
        ]);

        config(['services.groq.key' => 'mock_test_key']);

        // Mock Groq hallucinating an unknown ID (999999) along with a real one
        Http::fake([
            'api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'maintenance_id' => null,
                                'candidate_rankings' => [
                                    [
                                        'technician_id' => 999999, // Fake hallucinated ID
                                        'rank' => 1,
                                        'specialty_match' => 'Excellente',
                                        'reasoning' => 'Fake profile',
                                    ],
                                    [
                                        'technician_id' => $techReal->id,
                                        'rank' => 2,
                                        'specialty_match' => 'Bonne',
                                        'reasoning' => 'Profil réel disponible',
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenances.recommend-technicians'), [
            'type' => 'Préventive',
            'priority' => 'medium',
            'infrastructure_id' => $infra->id,
        ]);

        $response->assertStatus(200);
        $rankings = $response->json()['recommendations']['candidate_rankings'];

        $techIds = collect($rankings)->pluck('technician_id')->all();
        // The fake ID 999999 must be strictly filtered out by the backend!
        $this->assertNotContains(999999, $techIds);
        $this->assertContains($techReal->id, $techIds);
    }

    public function test_manager_can_safely_assign_technician_to_maintenance(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        $maintenance = Maintenance::create([
            'infrastructure_id' => $infra->id,
            'type' => 'Préventive',
            'status' => 'planned',
            'priority' => 'medium',
            'scheduled_at' => now()->addDays(2),
            'reference_code' => 'TEST-MAINT-001',
        ]);

        $tech = Technicien::create([
            'name' => 'Sami Trabelsi',
            'email' => 'sami.trabelsi@aquasecure.tn',
            'speciality' => 'Détection de fuites',
            'status' => 'available',
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenances.assign-technician', $maintenance), [
            'technician_id' => $tech->id,
        ]);

        $response->assertStatus(200);

        $maintenance->refresh();
        $this->assertEquals($tech->id, $maintenance->technician_id);
    }

    public function test_manager_cannot_assign_inactive_technician_to_maintenance(): void
    {
        $manager = $this->getManagerUser();
        $infra = Infrastructure::first();

        $maintenance = Maintenance::create([
            'infrastructure_id' => $infra->id,
            'type' => 'Préventive',
            'status' => 'planned',
            'priority' => 'medium',
            'scheduled_at' => now()->addDays(2),
            'reference_code' => 'TEST-MAINT-002',
        ]);

        $techInactive = Technicien::create([
            'name' => 'Inactif Assign Test',
            'email' => 'inactif.assign@aquasecure.tn',
            'speciality' => 'Tuyauterie',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($manager)->postJson(route('maintenances.assign-technician', $maintenance), [
            'technician_id' => $techInactive->id,
        ]);

        $response->assertStatus(422);

        $maintenance->refresh();
        $this->assertNull($maintenance->technician_id);
    }
}
