<?php

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\MaintenanceAssignmentInvitation;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TechnicianAssignmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $technicianUser;
    protected User $citizen;
    protected User $technician;
    protected User $technician2;
    protected Zone $zone;
    protected Infrastructure $infrastructure;
    protected Maintenance $maintenance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager@aquasecure.fr',
        ]);

        $this->citizen = User::factory()->create([
            'role' => 'citizen',
            'email' => 'citoyen@aquasecure.fr',
        ]);

        // Le technicien est un rôle applicatif : il s'agit du compte utilisateur.
        $this->technician = User::factory()->create([
            'role' => 'technician',
            'email' => 'sarah.petit@aquasecure.fr',
            'name' => 'Sarah Petit',
            'phone' => '+33 4 72 00 11 22',
            'speciality' => 'Capteurs & Télémétrie',
            'status' => 'available',
        ]);

        $this->technicianUser = $this->technician;

        $this->technician2 = User::factory()->create([
            'role' => 'technician',
            'email' => 'marc.lemoine@aquasecure.fr',
            'name' => 'Marc Lemoine',
            'phone' => '+33 5 56 00 22 33',
            'speciality' => 'Génie Civil',
            'status' => 'available',
        ]);

        $this->zone = Zone::create([
            'name' => 'Bassin Rhône',
            'address' => 'Lyon, Rhône',
            'description' => 'Zone fluviale',
            'risk_level' => 'medium',
        ]);

        $this->infrastructure = Infrastructure::create([
            'name' => 'Station Sète Nord',
            'zone_id' => $this->zone->id,
            'type' => 'Station de pompage',
            'status' => 'operational',
            'installation_date' => '2023-01-01',
        ]);

        $this->maintenance = Maintenance::create([
            'infrastructure_id' => $this->infrastructure->id,
            'reference_code' => 'MNT-2026-TEST',
            'type' => 'Préventive',
            'priority' => 'high',
            'status' => 'planned',
            'scheduled_at' => now()->addDays(2),
            'description' => 'Contrôle complet des vannes d\'isolement.',
        ]);
    }

    /**
     * Test 1: Selecting an eligible technician creates one pending invitation and sends Brevo email.
     */
    public function test_manager_can_issue_assignment_invitation_with_brevo_mock(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'messageId' => '<mocked-message-id-123@smtp-relay.brevo.com>',
            ], 201),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson(route('maintenances.assign-technician', $this->maintenance), [
                'technician_id' => $this->technician->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'email_sent' => true,
            ]);

        $this->assertDatabaseHas('maintenance_assignment_invitations', [
            'maintenance_id' => $this->maintenance->id,
            'technician_id' => $this->technician->id,
            'status' => 'pending',
            'email_delivery_status' => 'sent',
            'brevo_message_id' => '<mocked-message-id-123@smtp-relay.brevo.com>',
        ]);

        // Maintenance should still be planned until technician accepts
        $fresh = $this->maintenance->fresh();
        $this->assertEquals('planned', $fresh->status);
        $this->assertFalse((bool) $fresh->technician_assignment_locked);

        // Verify that Brevo API was called with technician email
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email' &&
                   $request['to'][0]['email'] === 'sarah.petit@aquasecure.fr';
        });
    }

    /**
     * Test 2: Brevo email failure is handled gracefully without losing the invitation record.
     */
    public function test_brevo_email_failure_preserves_invitation_record(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'message' => 'Rate limit exceeded on transactional emails',
            ], 429),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson(route('maintenances.assign-technician', $this->maintenance), [
                'technician_id' => $this->technician->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'email_sent' => false,
            ]);

        $this->assertDatabaseHas('maintenance_assignment_invitations', [
            'maintenance_id' => $this->maintenance->id,
            'technician_id' => $this->technician->id,
            'status' => 'pending',
            'email_delivery_status' => 'failed',
        ]);
    }

    /**
     * Test 3: Unauthorized user (e.g. citizen or guest) cannot issue invitations.
     */
    public function test_unauthorized_user_cannot_issue_invitation(): void
    {
        $response = $this->actingAs($this->citizen)
            ->postJson(route('maintenances.assign-technician', $this->maintenance), [
                'technician_id' => $this->technician->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 4: Protection against email link scanners — GET request does NOT accept assignment.
     */
    public function test_get_request_to_invitation_does_not_mutate_state(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'id-1'], 201),
        ]);

        $this->actingAs($this->manager)->postJson(route('maintenances.assign-technician', $this->maintenance), [
            'technician_id' => $this->technician->id,
        ]);

        $invitation = MaintenanceAssignmentInvitation::first();
        // Plain raw token can be simulated
        $rawToken = bin2hex(random_bytes(32));
        $invitation->update(['token_hash' => hash('sha256', $rawToken)]);

        // Simulate link scanner opening the link via GET
        $response = $this->get(route('maintenances.invitation.show', ['token' => $rawToken]));

        $response->assertStatus(200);
        $response->assertSee('Accepter', false);
        $response->assertSee('Décliner', false);

        // Verify status remains pending!
        $this->assertEquals('pending', $invitation->fresh()->status);
        $this->assertEquals('planned', $this->maintenance->fresh()->status);
        $this->assertFalse((bool) $this->maintenance->fresh()->technician_assignment_locked);
    }

    /**
     * Test 5: Technician explicitly accepting via POST locks assignment and transitions status to in_progress.
     */
    public function test_technician_explicit_acceptance_locks_assignment_and_starts_maintenance(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $invitation = MaintenanceAssignmentInvitation::create([
            'maintenance_id' => $this->maintenance->id,
            'technician_id' => $this->technician->id,
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'pending',
            'invited_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);

        $response = $this->post(route('maintenances.invitation.accept', ['token' => $rawToken]));

        $response->assertRedirect(route('maintenances.invitation.show', ['token' => $rawToken]));
        $response->assertSessionHas('success');

        $this->assertEquals('accepted', $invitation->fresh()->status);
        $fresh = $this->maintenance->fresh();
        $this->assertEquals('in_progress', $fresh->status);
        $this->assertTrue((bool) $fresh->technician_assignment_locked);
        $this->assertEquals($this->technician->id, $fresh->technician_id);
        $this->assertNotNull($fresh->assignment_accepted_at);

        // Infrastructure should be set to maintenance
        $this->assertEquals('maintenance', $this->infrastructure->fresh()->status);
    }

    /**
     * Test 6: Inactive technician is rejected.
     */
    public function test_cannot_invite_inactive_technician(): void
    {
        $this->technician->update(['status' => 'inactive']);

        $response = $this->actingAs($this->manager)
            ->postJson(route('maintenances.assign-technician', $this->maintenance), [
                'technician_id' => $this->technician->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test 7: Locked maintenance rejects normal edit attempt to change technician.
     */
    public function test_locked_maintenance_blocks_changing_technician_on_update(): void
    {
        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->manager)
            ->put(route('maintenances.update', $this->maintenance), [
                'infrastructure_id' => $this->infrastructure->id,
                'technician_id' => $this->technician2->id, // Attempt to replace Sarah with Marc
                'type' => 'Corrective',
                'priority' => 'high',
                'status' => 'in_progress',
                'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'),
            ]);

        // Should fail validation or redirect back with error
        $this->assertEquals($this->technician->id, $this->maintenance->fresh()->technician_id);
    }

    /**
     * Test 8: Technician sees only their own assigned operations in "Mes interventions".
     */
    public function test_technician_sees_only_own_assigned_operations(): void
    {
        // Maintenance 1 assigned to Sarah
        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'in_progress',
        ]);

        // Maintenance 2 assigned to Marc
        $maint2 = Maintenance::create([
            'infrastructure_id' => $this->infrastructure->id,
            'reference_code' => 'MNT-2026-MARC',
            'technician_id' => $this->technician2->id,
            'technician_assignment_locked' => true,
            'type' => 'Inspection',
            'priority' => 'low',
            'status' => 'in_progress',
            'scheduled_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->technicianUser)
            ->get(route('technicien.interventions.index'));

        $response->assertStatus(200);
        $response->assertSee('MNT-2026-TEST');
        $response->assertDontSee('MNT-2026-MARC');
    }

    /**
     * Test 9: Technician can submit technical report and intervention moves to awaiting_validation.
     */
    public function test_technician_submits_completion_report_moving_to_awaiting_validation(): void
    {
        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->technicianUser)
            ->post(route('technicien.interventions.complete', $this->maintenance), [
                'result' => 'Remplacement des joints d\'étanchéité et test sous 4.5 bars concluant.',
                'findings' => 'Usure mécanique normale sur garniture.',
                'parts_replaced' => 'Joint EPDM 150mm',
                'tests_verification' => 'Test de pression 15 min sans fuite.',
                'duration_hours' => 3.5,
            ]);

        $response->assertRedirect(route('technicien.interventions.show', $this->maintenance));

        $fresh = $this->maintenance->fresh();
        $this->assertEquals('awaiting_validation', $fresh->status);
        $this->assertNotNull($fresh->completion_submitted_at);
        $this->assertEquals(3.5, $fresh->duration_hours);
        $this->assertStringContainsString('Remplacement des joints', $fresh->result);
    }

    /**
     * Test 10: Missing required technical report is rejected.
     */
    public function test_submitting_completion_without_result_is_rejected(): void
    {
        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->technicianUser)
            ->post(route('technicien.interventions.complete', $this->maintenance), [
                'result' => '', // Empty result
            ]);

        $response->assertSessionHasErrors('result');
        $this->assertEquals('in_progress', $this->maintenance->fresh()->status);
    }

    /**
     * Test 11: Manager validates completion — moves to completed, sends Brevo notification, restores infra status.
     */
    public function test_manager_validates_and_closes_maintenance_with_brevo_notification(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'closed-123'], 201),
        ]);

        $this->infrastructure->update(['status' => 'maintenance']);

        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'awaiting_validation',
            'result' => 'Contrôle complet exécuté et conforme.',
            'completion_submitted_at' => now(),
            'duration_hours' => 2.0,
        ]);

        $response = $this->actingAs($this->manager)
            ->post(route('maintenances.validate-completion', $this->maintenance), [
                'manager_validation_notes' => 'Travail soigné, rapport validé.',
            ]);

        $response->assertRedirect(route('maintenances.show', $this->maintenance));
        $response->assertSessionHas('success');

        $fresh = $this->maintenance->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertNotNull($fresh->completion_validated_at);
        $this->assertEquals($this->manager->id, $fresh->completion_validated_by);

        // Infrastructure should be restored to operational
        $this->assertEquals('operational', $this->infrastructure->fresh()->status);

        // Brevo email dispatched to technician
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email' &&
                   str_contains($request['subject'], 'validée et clôturée');
        });
    }

    /**
     * Test 12: Manager requests corrections — moves back to in_progress with notes.
     */
    public function test_manager_can_request_corrections_reverting_to_in_progress(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'corr-123'], 201),
        ]);

        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'awaiting_validation',
            'result' => 'Purge effectuée.',
            'completion_submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->post(route('maintenances.request-corrections', $this->maintenance), [
                'correction_notes' => 'Veuillez préciser la pression finale mesurée après la purge.',
            ]);

        $response->assertRedirect(route('maintenances.show', $this->maintenance));

        $fresh = $this->maintenance->fresh();
        $this->assertEquals('in_progress', $fresh->status);
        $this->assertEquals('Veuillez préciser la pression finale mesurée après la purge.', $fresh->manager_correction_notes);

        // Brevo email dispatched to technician
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email' &&
                   str_contains($request['subject'], 'Compléments demandés');
        });
    }

    /**
     * Test 13: Manager dashboard displays awaiting validations, invitations, and allows direct actions.
     */
    public function test_manager_dashboard_displays_awaiting_validations_and_invitations(): void
    {
        // 1. Setup a maintenance awaiting validation
        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'awaiting_validation',
            'result' => 'Contrôle effectué, aucune fuite.',
            'completion_submitted_at' => now(),
            'completion_report' => [
                'findings' => 'Joint d étanchéité en bon état.',
                'parts_replaced' => 'Aucune',
                'tests_verification' => 'Pression stable à 3.2 bars.',
            ],
        ]);

        // 2. Setup a pending invitation
        MaintenanceAssignmentInvitation::create([
            'maintenance_id' => $this->maintenance->id,
            'technician_id' => $this->technician2->id,
            'manager_id' => $this->manager->id,
            'token_hash' => hash('sha256', 'mock-raw-token'),
            'status' => 'pending',
            'invited_at' => now(),
            'expires_at' => now()->addDays(3),
            'email_delivery_status' => 'sent',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('manager.dashboard'));

        $response->assertStatus(200)
            ->assertSee('Validation des Maintenances Terminées')
            ->assertSee($this->maintenance->reference_code)
            ->assertSee('Contrôle effectué, aucune fuite.')
            ->assertSee('Invitations en Attente')
            ->assertSee($this->technician2->name);
    }

    /**
     * Test 14: Manager can validate completion directly from dashboard and return to dashboard.
     */
    public function test_manager_can_validate_completion_from_dashboard_with_redirect(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'val-dash-123'], 201),
        ]);

        $this->maintenance->update([
            'technician_id' => $this->technician->id,
            'technician_assignment_locked' => true,
            'status' => 'awaiting_validation',
            'result' => 'Terminé.',
            'completion_submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->post(route('maintenances.validate-completion', $this->maintenance), [
                'redirect_to' => 'dashboard',
                'manager_validation_notes' => 'Validé depuis le tableau de bord.',
            ]);

        $response->assertRedirect(route('manager.dashboard'));

        $fresh = $this->maintenance->fresh();
        $this->assertEquals('completed', $fresh->status);
        $this->assertEquals('Validé depuis le tableau de bord.', $fresh->manager_validation_notes);
    }
}
