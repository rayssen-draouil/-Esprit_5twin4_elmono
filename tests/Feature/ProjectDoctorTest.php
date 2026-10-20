<?php

namespace Tests\Feature;

use App\Models\Financement;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Services\ProjectDoctorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectDoctorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Config::set('services.gemini.key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-1.5-flash');
    }

    private function makeAdmin(): User
    {
        return User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);
    }

    private function makeGestionnaire(): User
    {
        return User::factory()->create(['role' => 'gestionnaire']);
    }

    private function makeCitizen(): User
    {
        return User::factory()->create(['role' => 'citizen']);
    }

    private function makeProject(array $attributes = [], ?User $owner = null): Project
    {
        $owner = $owner ?? $this->makeAdmin();

        return Project::create(array_merge([
            'name' => 'Projet test docteur',
            'type' => 'Infrastructure',
            'description' => 'Un projet de test pour le docteur.',
            'budget' => 100000,
            'start_date' => now()->subMonths(2)->format('Y-m-d'),
            'end_date' => now()->addMonths(4)->format('Y-m-d'),
            'status' => 'in_progress',
            'progress' => 60,
            'validation_status' => 'approved',
            'user_id' => $owner->id,
        ], $attributes));
    }

    private function fakeGemini(array $payload): void
    {
        Http::fake(function ($request) use ($payload) {
            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]],
                        ],
                    ],
                ],
            ], 200);
        });
    }

    private function postDoctor(User $user, array $body)
    {
        return $this->actingAs($user)->postJson(route('projects.doctor.message'), $body);
    }

    /* ============================================================
       ACCÈS ET PERMISSIONS
       ============================================================ */

    public function test_guest_cannot_access_project_doctor(): void
    {
        $project = $this->makeProject();

        $response = $this->postJson(route('projects.doctor.message'), [
            'intent' => 'cost_estimation',
            'message' => 'Estime le coût.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_citizen_cannot_access_project_doctor(): void
    {
        $citizen = $this->makeCitizen();
        $project = $this->makeProject([], $citizen);

        $response = $this->postDoctor($citizen, [
            'intent' => 'cost_estimation',
            'message' => 'Estime le coût.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_access_another_users_project(): void
    {
        $owner = $this->makeCitizen();
        $other = $this->makeCitizen();
        $project = $this->makeProject([], $owner);

        $response = $this->postDoctor($other, [
            'intent' => 'cost_estimation',
            'message' => 'Estime le coût.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_access_global_analysis(): void
    {
        $admin = $this->makeAdmin();
        $this->makeProject();

        $this->fakeGemini(['synthese' => 'Analyse globale simulée.']);

        $response = $this->postDoctor($admin, [
            'intent' => 'project_overview',
            'message' => 'Donne un aperçu.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'project_overview']);
        $response->assertJsonFragment(['answer' => 'Analyse globale simulée.']);
    }

    public function test_doctor_status_endpoint_returns_availability(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->getJson(route('projects.doctor.status'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['available']);
    }

    /* ============================================================
       TROIS SUGGESTIONS RAPIDES
       ============================================================ */

    public function test_quick_suggestion_cost_estimation(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['budget' => 100000, 'progress' => 60]);

        ProjectExpense::create([
            'project_id' => $project->id,
            'description' => 'Matériaux',
            'category' => 'Matériaux',
            'amount' => 60000,
            'status' => 'approved',
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $this->fakeGemini(['synthese' => 'Coût estimé : 100 000 DT.']);

        $response = $this->postDoctor($admin, [
            'intent' => 'cost_estimation',
            'message' => 'Estimer le coût final.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'cost_estimation']);
    }

    public function test_quick_suggestion_delay_risk(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['progress' => 30]);

        $this->fakeGemini(['synthese' => 'Risque de retard évalué.', 'niveau_risque' => 'medium']);

        $response = $this->postDoctor($admin, [
            'intent' => 'delay_risk',
            'message' => 'Évaluer le risque de retard.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'delay_risk']);
    }

    public function test_quick_suggestion_funding_forecast(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id,
            'source' => 'État',
            'amount' => 40000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Donateur',
            'amount' => 20000,
            'status' => 'received',
            'validation_status' => 'pending_approval',
        ]);

        $this->fakeGemini(['synthese' => 'Besoin de financement estimé.']);

        $response = $this->postDoctor($admin, [
            'intent' => 'funding_forecast',
            'message' => 'Prévoir le besoin de financement.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'funding_forecast']);
    }

    public function test_general_question_returns_answer(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject();

        $this->fakeGemini(['synthese' => 'Réponse à la question libre.', 'reponse' => 'Détail de la réponse.']);

        $response = $this->postDoctor($admin, [
            'intent' => 'general_question',
            'message' => 'Quel est le statut de mes projets ?',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'general_question']);
    }

    public function test_free_text_detects_intent_and_project(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['name' => 'Pont du Nord', 'progress' => 30]);

        $this->fakeGemini(['synthese' => 'Risque de retard pour le projet Pont du Nord.', 'niveau_risque' => 'high']);

        // L'utilisateur n'envoie ni intent ni project_id : le texte libre doit suffire.
        $response = $this->postDoctor($admin, [
            'message' => 'quel est le retard du projet Pont du Nord ?',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['intent' => 'delay_risk']);
        $response->assertJson(['sources' => [$project->id]]);
    }

    public function test_free_text_mentions_project_cost_estimation(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['name' => 'Route Est', 'budget' => 100000, 'progress' => 60]);

        ProjectExpense::create([
            'project_id' => $project->id, 'description' => 'Matériaux', 'category' => 'Matériaux',
            'amount' => 60000, 'status' => 'approved', 'expense_date' => now()->format('Y-m-d'),
        ]);

        $this->fakeGemini(['synthese' => 'Coût estimé pour Route Est.', 'cout_final_estime' => 100000]);

        $response = $this->postDoctor($admin, [
            'message' => 'estime le coût du projet Route Est',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['intent' => 'cost_estimation']);
        $response->assertJson(['sources' => [$project->id]]);
    }

    /* ============================================================
       RÉCUPÉRATION DES DONNÉES ET SÉPARATION FINANCEMENTS
       ============================================================ */

    public function test_service_separates_confirmed_and_pending_funding(): void
    {
        $service = app(ProjectDoctorService::class);
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id, 'source' => 'Confirmé', 'amount' => 40000,
            'status' => 'received', 'validation_status' => 'approved',
        ]);
        Financement::create([
            'project_id' => $project->id, 'source' => 'En attente', 'amount' => 20000,
            'status' => 'received', 'validation_status' => 'pending_approval',
        ]);
        Financement::create([
            'project_id' => $project->id, 'source' => 'Rejeté', 'amount' => 10000,
            'status' => 'received', 'validation_status' => 'rejected',
        ]);

        $context = $service->buildProjectContext($project);

        $this->assertSame(40000.0, $context['funding']['confirmed']);
        $this->assertSame(20000.0, $context['funding']['pending']);
        $this->assertSame(10000.0, $context['funding']['rejected']);
    }

    public function test_service_respects_user_permissions(): void
    {
        $service = app(ProjectDoctorService::class);
        $citizen = $this->makeCitizen();
        $other = $this->makeCitizen();
        $this->makeProject(['name' => 'Projet A'], $citizen);
        $this->makeProject(['name' => 'Projet B'], $other);

        $accessible = $service->accessibleProjects($citizen);

        $this->assertCount(1, $accessible);
        $this->assertSame('Projet A', $accessible->first()->name);
    }

    public function test_admin_and_gestionnaire_see_all_projects(): void
    {
        $service = app(ProjectDoctorService::class);
        $admin = $this->makeAdmin();
        $gestionnaire = $this->makeGestionnaire();
        $this->makeProject(['name' => 'Projet A'], $gestionnaire);
        $this->makeProject(['name' => 'Projet B'], $gestionnaire);

        $this->assertCount(2, $service->accessibleProjects($admin));
        $this->assertCount(2, $service->accessibleProjects($gestionnaire));
    }

    /* ============================================================
       CALCULS DÉTERMINISTES
       ============================================================ */

    public function test_deterministic_cost_linear_extrapolation(): void
    {
        $service = app(ProjectDoctorService::class);
        $project = $this->makeProject(['budget' => 100000, 'progress' => 60]);

        ProjectExpense::create([
            'project_id' => $project->id, 'description' => 'Matériaux', 'category' => 'Matériaux',
            'amount' => 60000, 'status' => 'approved', 'expense_date' => now()->format('Y-m-d'),
        ]);

        $context = $service->buildProjectContext($project);
        $cost = $service->deterministicCost($context);

        $this->assertSame(100000.0, $cost['cout_final_estime']);
        $this->assertSame(0.0, $cost['ecart_estime']);
        $this->assertSame('extrapolation_lineaire', $cost['methode']);
    }

    public function test_deterministic_cost_without_expenses_is_indicative(): void
    {
        $service = app(ProjectDoctorService::class);
        $project = $this->makeProject(['budget' => 100000, 'progress' => 60]);

        $context = $service->buildProjectContext($project);
        $cost = $service->deterministicCost($context);

        $this->assertNull($cost['cout_final_estime']);
        $this->assertSame('low', $cost['niveau_confiance']);
        $this->assertNotEmpty($cost['informations_manquantes']);
    }

    public function test_deterministic_delay_risk_without_dates_is_unknown(): void
    {
        $service = app(ProjectDoctorService::class);
        $project = $this->makeProject(['start_date' => null, 'end_date' => null, 'progress' => 60]);

        $context = $service->buildProjectContext($project);
        $risk = $service->deterministicDelayRisk($context);

        $this->assertSame('unknown', $risk['niveau_risque']);
        $this->assertNull($risk['progression_temporelle_attendue']);
        $this->assertNotEmpty($risk['informations_manquantes']);
    }

    public function test_funding_forecast_formula_excludes_pending(): void
    {
        $service = app(ProjectDoctorService::class);
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id, 'source' => 'Confirmé', 'amount' => 40000,
            'status' => 'received', 'validation_status' => 'approved',
        ]);
        Financement::create([
            'project_id' => $project->id, 'source' => 'En attente', 'amount' => 20000,
            'status' => 'received', 'validation_status' => 'pending_approval',
        ]);

        $context = $service->buildProjectContext($project);
        $cost = $service->deterministicCost($context);
        $fund = $service->deterministicFunding($context, $cost);

        $this->assertSame(40000.0, $fund['financements_confirmes']);
        $this->assertSame(20000.0, $fund['financements_en_attente']);
        $this->assertSame(60000.0, $fund['besoin_financement_estime']); // 100000 - 40000
    }

    /* ============================================================
       GEMINI ERREURS ET RÉPONSES INVALIDES
       ============================================================ */

    public function test_gemini_quota_error_falls_back_to_deterministic(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['budget' => 100000, 'progress' => 60]);

        ProjectExpense::create([
            'project_id' => $project->id, 'description' => 'Matériaux', 'category' => 'Matériaux',
            'amount' => 60000, 'status' => 'approved', 'expense_date' => now()->format('Y-m-d'),
        ]);

        Http::fake(function () {
            return Http::response(['error' => ['message' => 'quota exceeded']], 429);
        });

        $response = $this->postDoctor($admin, [
            'intent' => 'cost_estimation',
            'message' => 'Estimer le coût final.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'cost_estimation']);
        $response->assertSee('100 000');
    }

    public function test_gemini_invalid_json_falls_back_to_deterministic(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['progress' => 30]);

        Http::fake(function () {
            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '{{{ invalid json }}}']]]]]], 200);
        });

        $response = $this->postDoctor($admin, [
            'intent' => 'delay_risk',
            'message' => 'Évaluer le risque de retard.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'delay_risk']);
    }

    public function test_gemini_not_configured_still_works_deterministic(): void
    {
        Config::set('services.gemini.key', null);

        $admin = $this->makeAdmin();
        $project = $this->makeProject(['budget' => 100000, 'progress' => 60]);

        ProjectExpense::create([
            'project_id' => $project->id, 'description' => 'Matériaux', 'category' => 'Matériaux',
            'amount' => 60000, 'status' => 'approved', 'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->postDoctor($admin, [
            'intent' => 'cost_estimation',
            'message' => 'Estimer le coût final.',
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'intent' => 'cost_estimation']);
        $response->assertSee('100 000');
    }

    /* ============================================================
       AUCUNE MODIFICATION AUTOMATIQUE DES DONNÉES MÉTIER
       ============================================================ */

    public function test_analysis_does_not_modify_project_or_financement(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject(['budget' => 100000, 'progress' => 60, 'status' => 'in_progress']);

        Financement::create([
            'project_id' => $project->id, 'source' => 'État', 'amount' => 40000,
            'status' => 'received', 'validation_status' => 'approved',
        ]);

        $this->fakeGemini(['synthese' => 'Analyse simulée.']);

        $this->postDoctor($admin, [
            'intent' => 'funding_forecast',
            'message' => 'Prévoir le besoin de financement.',
            'project_id' => $project->id,
        ]);

        $project->refresh();
        $this->assertSame('in_progress', $project->status);
        $this->assertSame(60, $project->progress);
        $this->assertSame(100000.00, (float) $project->budget);

        $this->assertDatabaseHas('financements', [
            'id' => $project->financements->first()->id,
            'validation_status' => 'approved',
            'amount' => 40000,
        ]);
    }

    public function test_invalid_intent_is_normalized_to_general_question(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject();

        $this->fakeGemini(['synthese' => 'Réponse normalisée.']);

        $response = $this->postDoctor($admin, [
            'intent' => 'invalid_intent',
            'message' => 'Quel est le statut de mes projets ?',
            'project_id' => $project->id,
        ]);

        // Un intent inconnu est normalisé en general_question, pas de 422.
        $response->assertStatus(200);
        $response->assertJson(['intent' => 'general_question']);
    }

    public function test_message_too_long_is_truncated(): void
    {
        $admin = $this->makeAdmin();
        $project = $this->makeProject();

        $this->fakeGemini(['synthese' => 'Réponse tronquée.']);

        $response = $this->postDoctor($admin, [
            'intent' => 'general_question',
            'message' => str_repeat('x', 2001),
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
    }
}
