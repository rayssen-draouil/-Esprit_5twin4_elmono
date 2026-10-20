<?php

namespace Tests\Feature;

use App\Models\DecisionHistory;
use App\Models\Financement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function getAdminUser(): User
    {
        return User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);
    }

    private function getManagerUser(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function getGestionnaireUser(): User
    {
        return User::factory()->create(['role' => 'gestionnaire']);
    }

    private function getCitizenUser(): User
    {
        return User::factory()->create(['role' => 'citizen']);
    }

    private function makeProject(array $attributes = []): Project
    {
        return Project::create(array_merge([
            'name' => 'Projet test',
            'type' => 'Infrastructure',
            'description' => 'Description',
            'budget' => 100000,
            'status' => 'planned',
            'progress' => 0,
            'validation_status' => 'draft',
        ], $attributes));
    }

    private function makeFinancement(array $attributes = []): Financement
    {
        $projectId = $attributes['project_id'] ?? (Project::first() ?? $this->makeProject())->id;

        return Financement::create(array_merge([
            'project_id' => $projectId,
            'source' => 'Source test',
            'amount' => 50000,
            'status' => 'received',
            'validation_status' => 'draft',
        ], $attributes));
    }

    /* ============================================================
       ACCÈS ET PERMISSIONS
       ============================================================ */

    public function test_admin_can_access_pilotage(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
    }

    public function test_manager_can_access_pilotage(): void
    {
        $manager = $this->getManagerUser();

        $response = $this->actingAs($manager)->get(route('back.pilotage'));

        $response->assertStatus(200);
    }

    public function test_gestionnaire_can_access_pilotage(): void
    {
        $gestionnaire = $this->getGestionnaireUser();

        $response = $this->actingAs($gestionnaire)->get(route('back.pilotage'));

        $response->assertStatus(200);
    }

    public function test_citizen_cannot_access_pilotage(): void
    {
        $citizen = $this->getCitizenUser();

        $response = $this->actingAs($citizen)->get(route('back.pilotage'));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_pilotage(): void
    {
        $response = $this->get(route('back.pilotage'));

        $response->assertRedirect('/login');
    }

    /* ============================================================
       KPI CALCULATIONS WITH REAL DATA
       ============================================================ */

    public function test_pilotage_displays_real_project_kpis(): void
    {
        $admin = $this->getAdminUser();

        // Créer des projets avec différents statuts
        Project::create([
            'name' => 'Projet 1', 'type' => 'Infrastructure', 'budget' => 100000,
            'status' => 'in_progress', 'progress' => 50,
            'validation_status' => 'approved',
        ]);
        Project::create([
            'name' => 'Projet 2', 'type' => 'Infrastructure', 'budget' => 200000,
            'status' => 'planned', 'progress' => 0,
            'validation_status' => 'pending_approval',
        ]);
        Project::create([
            'name' => 'Projet 3', 'type' => 'Infrastructure', 'budget' => 50000,
            'status' => 'completed', 'progress' => 100,
            'validation_status' => 'rejected',
        ]);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('3'); // total projets
        $response->assertSee('1'); // en attente
        $response->assertSee('1'); // approuvés
        $response->assertSee('1'); // rejetés
    }

    public function test_pilotage_displays_real_financement_kpis(): void
    {
        $admin = $this->getAdminUser();
        $project = $this->makeProject();

        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention A',
            'amount' => 30000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention B',
            'amount' => 20000,
            'status' => 'received',
            'validation_status' => 'pending_approval',
        ]);
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention C',
            'amount' => 10000,
            'status' => 'received',
            'validation_status' => 'rejected',
        ]);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('3'); // total financements
        $response->assertSee('1'); // en attente
        $response->assertSee('1'); // approuvés
        $response->assertSee('1'); // rejetés
        $response->assertSee('30 000 €'); // montants approuvés
        $response->assertSee('10 000 €'); // montants rejetés
    }

    public function test_pilotage_calculates_budget_and_funding_totals(): void
    {
        $admin = $this->getAdminUser();
        $project = $this->makeProject(['budget' => 100000]);

        // Financement approuvé pour ce projet
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention A',
            'amount' => 40000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('100 000 €'); // budget total
        $response->assertSee('40 000 €'); // financements confirmés
        $response->assertSee('60 000 €'); // reste à financer
    }

    public function test_pilotage_underfunded_projects_count(): void
    {
        $admin = $this->getAdminUser();
        $project = $this->makeProject(['budget' => 100000]);

        // Financement partiel : seulement 30 000 € sur 100 000 €
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention A',
            'amount' => 30000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('1'); // sous-financés
    }

    public function test_pilotage_empty_state_when_no_data(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('Aucune donnée disponible');
    }

    public function test_pilotage_shows_pending_validations(): void
    {
        $admin = $this->getAdminUser();
        $project = $this->makeProject(['validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('Projet test'); // titre du projet en attente
    }

    public function test_pilotage_shows_financial_alerts_for_underfunded_projects(): void
    {
        $admin = $this->getAdminUser();
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention A',
            'amount' => 30000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('Sous-financement');
    }

    public function test_pilotage_shows_recent_activity(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        DecisionHistory::create([
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'user_id' => $admin->id,
            'old_status' => 'draft',
            'new_status' => 'pending_approval',
            'action_type' => 'submit',
        ]);

        $response = $this->actingAs($admin)->get(route('back.pilotage'));

        $response->assertStatus(200);
        $response->assertSee('submit'); // action_type affiché dans l'activité récente
    }
}
