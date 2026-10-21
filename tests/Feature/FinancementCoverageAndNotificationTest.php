<?php

namespace Tests\Feature;

use App\Models\Financement;
use App\Models\Project;
use App\Models\User;
use App\Services\FinancementCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class FinancementCoverageAndNotificationTest extends TestCase
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
       SEUILS D'ALERTE - Couverture financière
       ============================================================ */

    public function test_seuil_rouge_moins_de_10_pourcent(): void
    {
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 5000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(100000, $coverage['budget']);
        $this->assertEquals(0, $coverage['confirmed_others']);
        $this->assertEquals(5000, $coverage['request_amount']);
        $this->assertEquals(5000, $coverage['projected_total']);
        $this->assertEquals(5.0, $coverage['coverage_rate']);
        $this->assertEquals('rouge', $coverage['alert_level']);
        $this->assertStringContainsString('très insuffisante', $coverage['alert_message']);
    }

    public function test_seuil_orange_a_10_pourcent(): void
    {
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 10000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(10.0, $coverage['coverage_rate']);
        $this->assertEquals('orange', $coverage['alert_level']);
    }

    public function test_seuil_orange_a_29_999_pourcent(): void
    {
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 29999, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        // 29.999 arrondi à 2 décimales = 30.00, donc bascule en bleu
        $this->assertEquals(30.0, $coverage['coverage_rate']);
        $this->assertEquals('bleu', $coverage['alert_level']);
    }

    public function test_seuil_bleu_a_30_pourcent(): void
    {
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 30000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(30.0, $coverage['coverage_rate']);
        $this->assertEquals('bleu', $coverage['alert_level']);
    }

    public function test_seuil_bleu_a_49_999_pourcent(): void
    {
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 49999, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        // 49.999 arrondi à 2 décimales = 50.00, donc bascule en vert
        $this->assertEquals(50.0, $coverage['coverage_rate']);
        $this->assertEquals('vert', $coverage['alert_level']);
    }

    public function test_seuil_vert_a_50_pourcent(): void
    {
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 50000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(50.0, $coverage['coverage_rate']);
        $this->assertEquals('vert', $coverage['alert_level']);
    }

    /* ============================================================
       FINANCEMENTS MULTIPLES
       ============================================================ */

    public function test_financements_multiples_sommes_confirmes(): void
    {
        $project = $this->makeProject(['budget' => 100000]);

        // Deux financements déjà confirmés
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention 1',
            'amount' => 20000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);
        Financement::create([
            'project_id' => $project->id,
            'source' => 'Subvention 2',
            'amount' => 15000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);

        // Nouvelle demande en cours
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 30000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(35000, $coverage['confirmed_others']);
        $this->assertEquals(30000, $coverage['request_amount']);
        $this->assertEquals(65000, $coverage['projected_total']);
        $this->assertEquals(65.0, $coverage['coverage_rate']);
        $this->assertEquals('vert', $coverage['alert_level']);
        $this->assertEquals(35000, $coverage['remaining_to_fund']);
    }

    /* ============================================================
       EXCLUSION DES DEMANDES EN ATTENTE / REJETÉES
       ============================================================ */

    public function test_demandes_en_attente_exclues_des_confirmes(): void
    {
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id,
            'source' => 'En attente',
            'amount' => 50000,
            'status' => 'received',
            'validation_status' => 'pending_approval',
        ]);

        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 10000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(0, $coverage['confirmed_others']);
        $this->assertEquals(10000, $coverage['projected_total']);
        $this->assertEquals(10.0, $coverage['coverage_rate']);
    }

    public function test_demandes_rejetees_exclues_des_confirmes(): void
    {
        $project = $this->makeProject(['budget' => 100000]);

        Financement::create([
            'project_id' => $project->id,
            'source' => 'Rejeté',
            'amount' => 50000,
            'status' => 'received',
            'validation_status' => 'rejected',
        ]);

        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 10000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(0, $coverage['confirmed_others']);
        $this->assertEquals(10000, $coverage['projected_total']);
    }

    /* ============================================================
       PAS DE DOUBLE COMPTAGE
       ============================================================ */

    public function test_demande_deja_approuvee_non_comptee_double(): void
    {
        $project = $this->makeProject(['budget' => 100000]);

        // Un financement déjà approuvé sur ce projet
        $existing = Financement::create([
            'project_id' => $project->id,
            'source' => 'Déjà approuvé',
            'amount' => 40000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);

        // Nouvelle demande (différente de l'existante)
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 20000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertEquals(40000, $coverage['confirmed_others']);
        $this->assertEquals(20000, $coverage['request_amount']);
        $this->assertEquals(60000, $coverage['projected_total']);
        $this->assertEquals(60.0, $coverage['coverage_rate']);
    }

    /* ============================================================
       BUDGET NUL OU INVALIDE
       ============================================================ */

    public function test_budget_nul_retourne_couverture_nulle(): void
    {
        $project = $this->makeProject(['budget' => 0]);
        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 50000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertNull($coverage['coverage_rate']);
        $this->assertFalse($coverage['is_budget_valid']);
        $this->assertEquals('rouge', $coverage['alert_level']);
    }

    /* ============================================================
       SURFINANCEMENT
       ============================================================ */

    public function test_surfinancement_detecte(): void
    {
        $project = $this->makeProject(['budget' => 50000]);

        Financement::create([
            'project_id' => $project->id,
            'source' => 'Déjà confirmé',
            'amount' => 30000,
            'status' => 'received',
            'validation_status' => 'approved',
        ]);

        $financement = $this->makeFinancement(['project_id' => $project->id, 'amount' => 30000, 'validation_status' => 'pending_approval']);

        $coverage = FinancementCoverageService::calculate($financement);

        $this->assertTrue($coverage['is_overfunded']);
        $this->assertEquals(60000, $coverage['projected_total']);
        $this->assertEquals(120.0, $coverage['coverage_rate']);
        $this->assertStringContainsString('dépasse le budget', $coverage['alert_message']);
    }

    /* ============================================================
       NOTIFICATIONS
       ============================================================ */

    public function test_admin_notifie_lors_soumission_projet(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id), [
            'comment' => 'Prêt pour examen',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $admin->id,
            'type' => 'App\Notifications\ProjetSubmitted',
        ]);

        $notification = DatabaseNotification::where('notifiable_id', $admin->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Nouveau projet soumis', $notification->data['title']);
    }

    public function test_admin_notifie_lors_soumission_financement(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $this->actingAs($gestionnaire)->post(route('financements.submit', $financement->id), [
            'comment' => 'Demande de financement',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $admin->id,
            'type' => 'App\Notifications\FinancementSubmitted',
        ]);
    }

    public function test_gestionnaire_notifie_lors_approbation_financement(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $this->actingAs($admin)->post(route('financements.decide', $financement->id), [
            'action' => 'approve',
            'comment' => 'Financement validé',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $gestionnaire->id,
            'type' => 'App\Notifications\FinancementDecision',
        ]);

        $notification = DatabaseNotification::where('notifiable_id', $gestionnaire->id)->first();
        $this->assertStringContainsString('approuvé', $notification->data['message']);
    }

    public function test_gestionnaire_notifie_lors_rejet_financement(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $this->actingAs($admin)->post(route('financements.decide', $financement->id), [
            'action' => 'reject',
            'comment' => 'Source non éligible',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $gestionnaire->id,
            'type' => 'App\Notifications\FinancementDecision',
        ]);

        $notification = DatabaseNotification::where('notifiable_id', $gestionnaire->id)->first();
        $this->assertStringContainsString('rejeté', $notification->data['message']);
    }

    public function test_gestionnaire_notifie_lors_demande_corrections_financement(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $financement = $this->makeFinancement(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $this->actingAs($admin)->post(route('financements.decide', $financement->id), [
            'action' => 'request_changes',
            'comment' => 'Budget à réviser',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $gestionnaire->id,
            'type' => 'App\Notifications\FinancementDecision',
        ]);

        $notification = DatabaseNotification::where('notifiable_id', $gestionnaire->id)->first();
        $this->assertStringContainsString('demande de corrections', $notification->data['message']);
    }

    public function test_gestionnaire_notifie_lors_approbation_projet(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'pending_approval']);

        $this->actingAs($admin)->post(route('projects.decide', $project->id), [
            'action' => 'approve',
            'comment' => 'Tout est en ordre',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $gestionnaire->id,
            'type' => 'App\Notifications\ProjetDecision',
        ]);
    }

    /* ============================================================
       MARQUAGE COMME LU
       ============================================================ */

    public function test_notification_marque_comme_lue(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id), [
            'comment' => 'Prêt pour examen',
        ]);

        $notification = DatabaseNotification::where('notifiable_id', $admin->id)->first();
        $this->assertNull($notification->read_at);

        $this->actingAs($admin)->put(route('notifications.read', $notification->id));

        $this->assertNotNull(DatabaseNotification::find($notification->id)->read_at);
    }

    public function test_notifications_toutes_marquees_comme_lues(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['user_id' => $gestionnaire->id, 'validation_status' => 'draft']);

        $this->actingAs($gestionnaire)->post(route('projects.submit', $project->id), [
            'comment' => 'Prêt',
        ]);

        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $admin->id)->whereNull('read_at')->count());

        $this->actingAs($admin)->put(route('notifications.read-all'));

        $this->assertEquals(0, DatabaseNotification::where('notifiable_id', $admin->id)->whereNull('read_at')->count());
    }

    /* ============================================================
       PERMISSIONS
       ============================================================ */

    public function test_citizen_ne_peut_pas_acceder_routes_validation(): void
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

    /* ============================================================
       AFFICHAGE ANALYSE FINANCIERE SUR LA VUE SHOW
       ============================================================ */

    public function test_financement_show_affiche_analyse_financiere(): void
    {
        $admin = $this->getAdminUser();
        $gestionnaire = $this->getGestionnaireUser();
        $project = $this->makeProject(['budget' => 100000]);
        $financement = $this->makeFinancement([
            'project_id' => $project->id,
            'user_id' => $gestionnaire->id,
            'amount' => 30000,
            'validation_status' => 'pending_approval',
        ]);

        $response = $this->actingAs($admin)->get(route('financements.show', $financement->id));
        $response->assertStatus(200);
        $response->assertSee('100 000');
        $response->assertSee('30 000');
        $response->assertSee('Couverture du projet');
        $response->assertSee('30 %');
    }
}
