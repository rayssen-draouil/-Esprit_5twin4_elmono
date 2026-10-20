<?php

namespace Tests\Feature;

use App\Models\DecisionHistory;
use App\Models\Financement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectFinancementValidationTest extends TestCase
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

    private function getGestionnaireUser(): User
    {
        return User::factory()->create(['role' => 'gestionnaire']);
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
            'status' => 'planned',
            'validation_status' => 'draft',
        ], $attributes));
    }

    /* ============================================================
       PROJETS - Workflow de validation
       ============================================================ */

    public function test_gestionnaire_can_create_project_in_draft(): void
    {
        $gestionnaire = $this->getGestionnaireUser();

        $response = $this->actingAs($gestionnaire)->post(route('projects.store'), [
            'name' => 'Projet test brouillon',
            'type' => 'Infrastructure',
            'description' => 'Description de test',
            'budget' => 100000,
            'status' => 'planned',
            'progress' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(3)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $project = Project::where('name', 'Projet test brouillon')->first();
        $this->assertNotNull($project);
        $this->assertEquals('draft', $project->validation_status);
        $this->assertEquals($gestionnaire->id, $project->user_id);

        $this->assertDatabaseHas('decision_histories', [
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'action_type' => 'submit',
            'new_status' => 'draft',
        ]);
    }

    public function test_gestionnaire_can_submit_project_for_approval(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $response = $this->actingAs($gestionnaire, 'web')->post(route('projects.submit', $project->id), [
            'comment' => 'Prêt pour examen',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $project->refresh();
        $this->assertEquals('pending_approval', $project->validation_status);

        $this->assertDatabaseHas('decision_histories', [
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'action_type' => 'submit',
            'old_status' => 'draft',
            'new_status' => 'pending_approval',
            'comment' => 'Prêt pour examen',
        ]);
    }

    public function test_gestionnaire_cannot_submit_project_not_in_draft_or_changes_requested(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $project->refresh();
        $this->assertEquals('pending_approval', $project->validation_status);
    }

    public function test_gestionnaire_cannot_submit_another_user_project(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $other = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $other->id, 'validation_status' => 'draft']);

        $response = $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id));
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $project->refresh();
        $this->assertEquals('draft', $project->validation_status);
    }

    public function test_admin_can_approve_project(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'approve',
            'comment' => 'Tout est en ordre',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $project->refresh();
        $this->assertEquals('approved', $project->validation_status);
        $this->assertEquals('Tout est en ordre', $project->validation_comment);
        $this->assertEquals($admin->id, $project->validated_by);
        $this->assertNotNull($project->validated_at);

        $this->assertDatabaseHas('decision_histories', [
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'action_type' => 'approve',
            'old_status' => 'pending_approval',
            'new_status' => 'approved',
        ]);
    }

    public function test_admin_can_request_changes_on_project(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'request_changes',
            'comment' => 'Budget à réviser',
        ]);

        $response->assertRedirect(route('projects.show', $project->id));

        $project->refresh();
        $this->assertEquals('changes_requested', $project->validation_status);
        $this->assertEquals('Budget à réviser', $project->validation_comment);
    }

    public function test_admin_can_reject_project_with_mandatory_comment(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'reject',
        ]);
        $response->assertSessionHasErrors(['comment']);

        $response = $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'reject',
            'comment' => 'Motif insuffisant',
        ]);
        $response->assertRedirect(route('projects.show', $project->id));

        $project->refresh();
        $this->assertEquals('rejected', $project->validation_status);
        $this->assertEquals('Motif insuffisant', $project->validation_comment);
    }

    public function test_admin_cannot_approve_own_project(): void
    {
        $admin = $this->getAdminUser();
        $project = $this->makeProject(['user_id' => $admin->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'approve',
            'comment' => 'Auto-approbation',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $project->refresh();
        $this->assertEquals('pending_approval', $project->validation_status);
    }

    public function test_gestionnaire_cannot_decide_project(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $other = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $other->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($gestionnaire)->post(route('projects.decide', $project->id), [
            'action' => 'approve',
            'comment' => 'Tentative',
        ]);
        $response->assertStatus(403);
    }

    public function test_gestionnaire_can_resubmit_after_changes_requested(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'changes_requested']);

        $response = $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id), [
            'comment' => 'Corrections apportées',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $project->refresh();
        $this->assertEquals('pending_approval', $project->validation_status);

        $this->assertDatabaseHas('decision_histories', [
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'action_type' => 'submit',
            'old_status' => 'changes_requested',
            'new_status' => 'pending_approval',
        ]);
    }

    public function test_decision_history_is_recorded_for_all_actions(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id), ['comment' => 'Soumis']);
        $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'approve',
            'comment' => 'Approuvé',
        ]);

        $this->assertEquals(2, DecisionHistory::where('resource_type', 'project')
            ->where('resource_id', $project->id)
            ->count());

        $this->assertDatabaseHas('decision_histories', [
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'action_type' => 'submit',
            'user_id' => $gestionnaire->id,
        ]);
        $this->assertDatabaseHas('decision_histories', [
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'action_type' => 'approve',
            'user_id' => $admin->id,
        ]);
    }

    public function test_project_show_displays_validation_status_and_history(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $admin = $this->getAdminUser();
        $project = $this->makeProject([
            'user_id' => $gestionnaire->id,
            'validation_status' => 'pending_approval',
            'validation_comment' => 'En examen',
        ]);

        DecisionHistory::create([
            'resource_type' => 'project',
            'resource_id' => $project->id,
            'user_id' => $gestionnaire->id,
            'old_status' => 'draft',
            'new_status' => 'pending_approval',
            'comment' => 'Soumis',
            'action_type' => 'submit',
        ]);

        $response = $this->actingAs($admin)->get(route('projects.show', $project->id));
        $response->assertStatus(200);
        $response->assertSee('Pending approval'); // Blade ucfirst
        $response->assertSee('En examen');
        $response->assertSee('Soumis');
    }

    /* ============================================================
       FINANCEMENTS - Workflow de validation
       ============================================================ */

    public function test_gestionnaire_can_create_financement_in_draft(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject();

        $response = $this->actingAs($gestionnaire)->post(route('financements.store'), [
            'project_id' => $project->id,
            'source' => 'Subvention État',
            'amount' => 50000,
            'status' => 'planned',
            'funded_at' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $financement = Financement::where('source', 'Subvention État')->first();
        $this->assertNotNull($financement);
        $this->assertEquals('draft', $financement->validation_status);
        $this->assertEquals($gestionnaire->id, $financement->user_id);
    }

    public function test_gestionnaire_can_submit_financement_for_approval(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $response = $this->actingAs($gestionnaire)->post(route('financements.submit', $financement->id), [
            'comment' => 'Demande de financement',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $financement->refresh();
        $this->assertEquals('pending_approval', $financement->validation_status);
    }

    public function test_admin_can_approve_financement(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('financements.decide', $financement->id), [
            'action' => 'approve',
            'comment' => 'Financement validé',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $financement->refresh();
        $this->assertEquals('approved', $financement->validation_status);
        $this->assertEquals('Financement validé', $financement->validation_comment);
    }

    public function test_admin_can_reject_financement_with_mandatory_comment(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('financements.decide', $financement->id), [
            'action' => 'reject',
        ]);
        $response->assertSessionHasErrors(['comment']);

        $response = $this->actingAs($admin)->post(route('financements.decide', $financement->id), [
            'action' => 'reject',
            'comment' => 'Source non éligible',
        ]);
        $response->assertRedirect(route('financements.show', $financement->id));

        $financement->refresh();
        $this->assertEquals('rejected', $financement->validation_status);
    }

    public function test_pending_or_rejected_financement_not_counted_as_confirmed(): void
    {
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id,
            'source' => 'Approuvé',
            'amount' => 30000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);
        Financement::create([
            'project_id' => $project->id,
            'source' => 'En attente',
            'amount' => 20000,
            'status' => 'received',
            'validation_status' => 'pending_approval',
        ]);
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Rejeté',
            'amount' => 10000,
            'status' => 'received',
            'validation_status' => 'rejected',
        ]);

        $confirmed = $project->financements()
            ->where('status', 'received')
            ->where('validation_status', 'approved')
            ->sum('amount');

        $this->assertEquals(30000, $confirmed);
    }

    public function test_gestionnaire_cannot_decide_own_financement(): void
    {
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $response = $this->actingAs($gestionnaire)->post(route('financements.decide', $financement->id), [
            'action' => 'approve',
            'comment' => 'Auto-approbation',
        ]);
        $response->assertStatus(403);

        $financement->refresh();
        $this->assertEquals('pending_approval', $financement->validation_status);
    }

    public function test_citizen_cannot_access_validation_routes(): void
    {
        $citizen = User::factory()->create(['role' => 'citizen']);
        $project = $this->makeProject(['validation_status' => 'draft']);

        $response = $this->actingAs($citizen)->post(route('projects.submit', $project->id));
        $response->assertStatus(403);

        $response = $this->actingAs($citizen)->post(route('projects.decide', $project->id), [
            'action' => 'approve',
        ]);
        $response->assertStatus(403);
    }
}
