<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Equipement;
use App\Models\Intervention;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TechnicianAccountEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_intervention_schedule_conflicts_are_detected_and_non_overlapping_slots_are_allowed(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $technicianUser = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $technicianUser->id,
            'name' => $technicianUser->name,
            'email' => $technicianUser->email,
        ]);
        $start = now()->addDays(2)->setTime(10, 0);
        $existing = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe du matin',
            'scheduled_at' => $start,
            'scheduled_duration_minutes' => 120,
            'status' => 'planned',
        ]);
        $this->actingAs($manager);

        $this->postJson(route('interventions.check-schedule'), [
            'technician_id' => $technician->id,
            'scheduled_at' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
            'scheduled_duration_minutes' => 60,
        ])->assertOk()
            ->assertJsonPath('conflicts.0.id', $existing->id);
        $this->postJson(route('interventions.check-schedule', $existing), [
            'technician_id' => $technician->id,
            'scheduled_at' => $start->format('Y-m-d\TH:i'),
            'scheduled_duration_minutes' => 120,
        ])->assertOk()->assertJsonPath('conflicts', []);

        $this->post(route('interventions.store'), [
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe chevauchante',
            'scheduled_at' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
            'scheduled_duration_minutes' => 60,
            'priority' => 'high',
            'status' => 'planned',
        ])->assertSessionHasErrors('scheduled_at');

        $this->post(route('interventions.store'), [
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe suivante',
            'scheduled_at' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
            'scheduled_duration_minutes' => 60,
            'priority' => 'medium',
            'status' => 'planned',
        ])->assertRedirect();

        $this->assertDatabaseHas('interventions', [
            'team' => 'Équipe suivante',
            'technician_id' => $technician->id,
        ]);
    }

    public function test_technician_with_in_progress_intervention_cannot_be_assigned_another_intervention(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $technicianUser = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $technicianUser->id,
            'name' => $technicianUser->name,
            'email' => $technicianUser->email,
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe en cours',
            'scheduled_at' => now()->addDay(),
            'scheduled_duration_minutes' => 60,
            'status' => 'in_progress',
        ]);
        $targetIntervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'team' => 'Nouvelle équipe',
            'scheduled_at' => now()->addDays(3),
            'scheduled_duration_minutes' => 60,
            'status' => 'planned',
        ]);
        $this->assertCount(
            1,
            app(\App\Services\InterventionSchedulingService::class)->conflicts(
                $technician->id,
                now()->addDays(4)->format('Y-m-d H:i:s'),
                60,
            )
        );
        $this->actingAs($manager)
            ->postJson(route('interventions.check-schedule'), [
                'technician_id' => $technician->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Ce technicien est déjà affecté à une autre intervention en cours. Il doit la terminer avant d’en recevoir une autre.');

        $this->post(route('interventions.store'), [
                'incident_id' => Incident::factory()->create()->id,
                'technician_id' => $technician->id,
                'team' => 'Nouvelle affectation',
                'scheduled_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'scheduled_duration_minutes' => 60,
                'priority' => 'medium',
                'status' => 'planned',
            ])
            ->assertSessionHasErrors([
                'scheduled_at' => 'Ce technicien est déjà affecté à une autre intervention en cours. Il doit la terminer avant d’en recevoir une autre.',
            ]);

        $this->put(route('interventions.update', $targetIntervention), [
            'incident_id' => $targetIntervention->incident_id,
            'technician_id' => $technician->id,
            'team' => 'Nouvelle équipe',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'scheduled_duration_minutes' => 60,
            'priority' => 'medium',
            'status' => 'planned',
        ])->assertSessionHasErrors('scheduled_at');

        $this->assertNull($targetIntervention->fresh()->technician_id);
    }

    public function test_new_intervention_requires_a_technician_assignment(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)
            ->from(route('interventions.create'))
            ->post(route('interventions.store'), [
                'incident_id' => Incident::factory()->create()->id,
                'technician_id' => '',
                'team' => 'Équipe terrain',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'scheduled_duration_minutes' => 60,
                'priority' => 'medium',
                'status' => 'planned',
            ])
            ->assertSessionHasErrors([
                'technician_id' => 'Il faut affecter un technicien à l’intervention.',
            ]);

        $this->assertDatabaseCount('interventions', 0);
    }

    public function test_email_notification_is_sent_when_a_technician_is_assigned_or_reassigned(): void
    {
        Http::fake([
            'api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message-id'], 201),
        ]);
        config([
            'services.brevo.driver' => 'api',
            'services.brevo.api_key' => 'test-brevo-api-key',
            'services.brevo.sender_email' => 'notifications@example.test',
            'services.brevo.sender_name' => 'AquaSecure',
        ]);

        $manager = User::factory()->create(['role' => 'manager']);
        $firstUser = User::factory()->create(['role' => 'technician']);
        $firstTechnician = Technicien::create([
            'user_id' => $firstUser->id,
            'name' => $firstUser->name,
            'email' => 'first-technician@example.test',
        ]);
        $secondUser = User::factory()->create(['role' => 'technician']);
        $secondTechnician = Technicien::create([
            'user_id' => $secondUser->id,
            'name' => $secondUser->name,
            'email' => 'second-technician@example.test',
        ]);
        $incident = Incident::factory()->create([
            'type' => 'Fuite de canalisation',
            'location' => 'Zone Nord',
        ]);
        $this->actingAs($manager);

        $this->post(route('interventions.store'), [
            'incident_id' => $incident->id,
            'technician_id' => $firstTechnician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'scheduled_duration_minutes' => 60,
            'priority' => 'high',
            'status' => 'planned',
        ])->assertSessionHas('success');

        $intervention = Intervention::query()->where('incident_id', $incident->id)->firstOrFail();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['to'][0]['email'] === 'first-technician@example.test'
            && str_contains($request['subject'], 'Nouvelle intervention assignée')
            && str_contains($request['htmlContent'], 'Fuite de canalisation')
            && str_contains($request['htmlContent'], 'Zone Nord'));

        $this->put(route('interventions.update', $intervention), [
            'incident_id' => $incident->id,
            'technician_id' => $firstTechnician->id,
            'team' => 'Équipe terrain modifiée',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'scheduled_duration_minutes' => 60,
            'priority' => 'high',
            'status' => 'planned',
        ])->assertSessionHas('success');
        Http::assertSentCount(1);

        $this->put(route('interventions.update', $intervention), [
            'incident_id' => $incident->id,
            'technician_id' => $secondTechnician->id,
            'team' => 'Nouvelle équipe',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'scheduled_duration_minutes' => 60,
            'priority' => 'high',
            'status' => 'planned',
        ])->assertSessionHas('success');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['to'][0]['email'] === 'second-technician@example.test');
    }

    public function test_ai_equipment_recommendations_include_available_shared_equipment_only(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $incident = Incident::factory()->create(['type' => 'Fuite']);
        $intervention = Intervention::create([
            'incident_id' => $incident->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $availableEquipment = Equipement::create([
            'name' => 'Détecteur acoustique',
            'type' => 'Détection de fuite',
            'status' => 'available',
        ]);
        $busyEquipment = Equipement::create([
            'name' => 'Pompe de secours',
            'type' => 'Pompage',
            'status' => 'in_use',
        ]);
        $otherEquipment = Equipement::create([
            'name' => 'Caméra thermique',
            'type' => 'Test',
            'status' => 'available',
        ]);
        config(['services.anthropic.key' => 'mock_anthropic_test_key']);
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                            'recommendations' => [
                                ['equipment_id' => $busyEquipment->id, 'reason' => 'Matériel indisponible.'],
                                ['equipment_id' => $availableEquipment->id, 'reason' => 'Détection adaptée à la fuite.'],
                            ],
                        ]),
                ]],
            ]),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('technicien.interventions.recommend-equipements', $intervention))
            ->assertOk()
            ->assertJsonPath('is_ai_generated', true);

        $this->assertSame(
            [$availableEquipment->id],
            collect($response->json('recommendations'))->pluck('id')->all()
        );
        Http::assertSent(function ($request) use ($incident, $availableEquipment, $otherEquipment, $busyEquipment): bool {
            $input = json_decode($request['messages'][0]['content'], true);
            $availableIds = collect($input['available_equipment'])->pluck('id')->all();

            return $request->hasHeader('x-api-key', 'mock_anthropic_test_key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $input['incident_type'] === $incident->type
                && $availableIds !== []
                && collect($availableIds)->contains($availableEquipment->id)
                && collect($availableIds)->contains($otherEquipment->id)
                && ! collect($availableIds)->contains($busyEquipment->id);
        });
    }

    public function test_ai_api_can_return_no_relevant_equipment(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $incident = Incident::factory()->create(['type' => 'Contamination']);
        $intervention = Intervention::create([
            'incident_id' => $incident->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        Equipement::create([
            'name' => 'Clé à molette',
            'type' => 'Outillage mécanique',
            'status' => 'available',
        ]);
        config(['services.anthropic.key' => 'mock_anthropic_test_key']);
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '{"recommendations":[]}',
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('technicien.interventions.recommend-equipements', $intervention))
            ->assertOk()
            ->assertJsonPath('is_ai_generated', true)
            ->assertJsonPath('recommendations', [])
            ->assertJsonPath('notice', 'Aucun équipement suffisamment pertinent trouvé');
    }

    public function test_equipment_recommendations_show_notice_when_no_type_is_compatible(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $incident = Incident::factory()->create(['type' => 'Fuite']);
        $intervention = Intervention::create([
            'incident_id' => $incident->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        Equipement::create([
            'name' => 'Kit de mesure électrique',
            'type' => 'Électricité',
            'status' => 'available',
        ]);
        config(['services.anthropic.key' => null]);

        $this->actingAs($user)
            ->postJson(route('technicien.interventions.recommend-equipements', $intervention))
            ->assertOk()
            ->assertJsonPath('recommendations', [])
            ->assertJsonPath('notice', 'Aucun équipement suffisamment pertinent trouvé');
    }

    public function test_intervention_and_equipment_create_and_update_reject_invalid_values(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create(['role' => 'manager']);
        $technicianUser = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $technicianUser->id,
            'name' => $technicianUser->name,
            'email' => $technicianUser->email,
        ]);
        $incident = Incident::factory()->create();
        $this->actingAs($manager);

        $this->get(route('interventions.create'))
            ->assertOk()
            ->assertSee('class="validated-form"', false)
            ->assertDontSee('Préparer l’intervention avec l’IA')
            ->assertSee('id="technician_id"', false)
            ->assertSee('data-rules="required"', false)
            ->assertSee('name="scheduled_duration_minutes"', false)
            ->assertSee('name="work_plan"', false)
            ->assertSee('name="team"', false)
            ->assertSee('maxlength="255"', false)
            ->assertSee('id="started_at"', false)
            ->assertSee('id="completed_at"', false)
            ->assertSee('setCustomValidity', false);
        $this->get(route('equipements.create'))
            ->assertOk()
            ->assertSee('class="validated-form"', false)
            ->assertSee('name="name"', false)
            ->assertSee('maxlength="255"', false)
            ->assertSee('name="description"', false)
            ->assertSee('maxlength="5000"', false);

        $this->from(route('interventions.create'))->post(route('interventions.store'), [
            'incident_id' => $incident->id,
            'technician_id' => $technician->id,
            'team' => str_repeat('A', 256),
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'planned',
            'started_at' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'completed_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors(['team', 'completed_at']);
        $this->get(route('interventions.create'))
            ->assertOk()
            ->assertSee('class="form-error-summary-banner"', false)
            ->assertSee("Veuillez corriger 2 erreurs avant d'enregistrer", false)
            ->assertSee('id="team"', false)
            ->assertSee('class="is-invalid"', false)
            ->assertSee('field-error-badge', false);

        $intervention = Intervention::create([
            'incident_id' => $incident->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);

        $this->put(route('interventions.update', $intervention), [
            'incident_id' => $incident->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe modifiée',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'statut-invalide',
        ])->assertSessionHasErrors(['status']);
        $this->assertDatabaseHas('interventions', [
            'id' => $intervention->id,
            'team' => 'Équipe terrain',
            'status' => 'planned',
        ]);

        $equipment = Equipement::create([
            'name' => 'Multimètre',
            'type' => 'Mesure',
            'serial_number' => 'EQ-001',
            'status' => 'available',
        ]);
        $anotherEquipment = Equipement::create([
            'name' => 'Détecteur',
            'type' => 'Détection',
            'serial_number' => 'EQ-002',
            'status' => 'available',
        ]);

        $this->from(route('equipements.create'))->post(route('equipements.store'), [
            'name' => 'Capteur',
            'type' => 'Mesure',
            'serial_number' => 'EQ-001',
            'status' => 'available',
        ])->assertSessionHasErrors(['serial_number']);
        $this->get(route('equipements.create'))
            ->assertOk()
            ->assertSee('class="form-error-summary-banner"', false)
            ->assertSee('Numéro de série')
            ->assertSee('déjà attribué');

        $this->put(route('equipements.update', $anotherEquipment), [
            'name' => 'Détecteur',
            'type' => 'Détection',
            'serial_number' => 'EQ-001',
            'status' => 'available',
        ])->assertSessionHasErrors(['serial_number']);

        $this->assertDatabaseHas('equipements', [
            'id' => $anotherEquipment->id,
            'serial_number' => 'EQ-002',
        ]);
        $this->assertDatabaseHas('equipements', [
            'id' => $equipment->id,
            'serial_number' => 'EQ-001',
        ]);
    }

    public function test_admin_manages_technician_contact_details_from_user_accounts_and_directory_is_read_only(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Téléphone du technicien')
            ->assertSee('Spécialité du technicien')
            ->assertSee('Compétences du technicien');

        $this->post(route('admin.users.store'), [
            'name' => 'Samir Ben Salah',
            'email' => 'samir@example.com',
            'role' => 'technician',
            'phone' => '+216 20 123 456',
            'speciality' => 'Électromécanique',
            'skills' => 'Diagnostic de pompes, entretien de moteurs, lecture de schémas électriques',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'samir@example.com')->firstOrFail();
        $technician = $user->technicien()->firstOrFail();

        $this->assertDatabaseHas('techniciens', [
            'id' => $technician->id,
            'phone' => '+216 20 123 456',
            'speciality' => 'Électromécanique',
            'skills' => 'Diagnostic de pompes, entretien de moteurs, lecture de schémas électriques',
        ]);

        $this->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('+216 20 123 456')
            ->assertSee('Électromécanique')
            ->assertSee('Diagnostic de pompes');

        $this->put(route('admin.users.update', $user), [
            'name' => 'Samir Ben Salah',
            'email' => 'samir@example.com',
            'role' => 'technician',
            'phone' => '+216 21 987 654',
            'speciality' => 'Hydraulique',
            'skills' => 'Recherche de fuites, réparation de conduites, essais de pression',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('techniciens', [
            'id' => $technician->id,
            'phone' => '+216 21 987 654',
            'speciality' => 'Hydraulique',
            'skills' => 'Recherche de fuites, réparation de conduites, essais de pression',
        ]);

        $this->get(route('techniciens.index'))->assertOk();
        $this->get(route('techniciens.show', $technician))->assertOk()->assertDontSee('Modifier');
        $this->get('/admin/techniciens/create')->assertNotFound();
        $this->get('/admin/techniciens/'.$technician->id.'/edit')->assertNotFound();
        $this->post('/admin/techniciens')->assertStatus(405);
        $this->put('/admin/techniciens/'.$technician->id)->assertStatus(405);
        $this->delete('/admin/techniciens/'.$technician->id)->assertStatus(405);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)
            ->get(route('techniciens.index'))
            ->assertOk();
        $this->get(route('techniciens.show', $technician))->assertOk();
    }

    public function test_public_registration_creates_a_technician_account_and_profile(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('technician-speciality-field')
            ->assertSee('Spécialité')
            ->assertSee('Compétences professionnelles')
            ->assertSee('technician-skills-field')
            ->assertSee('novalidate', false);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Nadia Ben Ali',
            'email' => 'nadia@example.com',
            'role' => 'technician',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['speciality', 'skills']);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Veuillez renseigner votre spécialité pour créer un compte technicien.')
            ->assertSee('Veuillez renseigner vos compétences pour créer un compte technicien.')
            ->assertSee('aria-invalid="true"', false);

        $response = $this->post(route('register.store'), [
            'name' => 'Nadia Ben Ali',
            'email' => 'nadia@example.com',
            'role' => 'technician',
            'speciality' => 'Hydraulique',
            'skills' => 'Détection de fuites, réparation de canalisations, soudure PEHD',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $user = User::where('email', 'nadia@example.com')->firstOrFail();
        $this->assertSame('technician', $user->role);
        $this->assertSame(User::TECHNICIAN_APPROVAL_PENDING, $user->technician_approval_status);
        $this->assertDatabaseHas('techniciens', [
            'user_id' => $user->id,
            'name' => 'Nadia Ben Ali',
            'email' => 'nadia@example.com',
            'speciality' => 'Hydraulique',
            'skills' => 'Détection de fuites, réparation de canalisations, soudure PEHD',
        ]);
    }

    public function test_unapproved_technician_cannot_log_in_until_admin_approves_them(): void
    {
        $technician = User::factory()->create([
            'role' => 'technician',
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_PENDING,
            'password' => 'password123',
        ]);
        $this->withoutVite();

        $this->post(route('login.store'), [
            'email' => $technician->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('En attente')
            ->assertSee('Approuver')
            ->assertSee('Refuser');

        $this->patch(route('admin.users.approve-technician', $technician))
            ->assertRedirect();

        $this->assertSame(
            User::TECHNICIAN_APPROVAL_APPROVED,
            $technician->fresh()->technician_approval_status
        );

        auth()->logout();
        $this->post(route('login.store'), [
            'email' => $technician->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('technicien.interventions.index'));
    }

    public function test_admin_can_reject_pending_technician_and_pending_session_cannot_open_workspace(): void
    {
        $technician = User::factory()->create([
            'role' => 'technician',
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_PENDING,
        ]);

        $this->actingAs($technician)
            ->get(route('technicien.interventions.index'))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->patch(route('admin.users.reject-technician', $technician))
            ->assertRedirect();

        $this->assertSame(
            User::TECHNICIAN_APPROVAL_REJECTED,
            $technician->fresh()->technician_approval_status
        );

        auth()->logout();
        $this->post(route('login.store'), [
            'email' => $technician->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_intervention_form_only_lists_technicians_with_technician_accounts(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create(['role' => 'manager']);
        $accountTechnician = User::factory()->create([
            'name' => 'Compte technicien',
            'role' => 'technician',
        ]);
        $technician = Technicien::create([
            'user_id' => $accountTechnician->id,
            'name' => $accountTechnician->name,
            'email' => $accountTechnician->email,
        ]);
        $unlinkedTechnician = Technicien::create(['name' => 'Sans compte']);
        $pendingUser = User::factory()->create([
            'role' => 'technician',
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_PENDING,
        ]);
        $pendingTechnician = Technicien::create([
            'user_id' => $pendingUser->id,
            'name' => $pendingUser->name,
            'email' => $pendingUser->email,
        ]);

        $response = $this->actingAs($manager)->get(route('interventions.create'));

        $response->assertOk()
            ->assertSee('value="'.$technician->id.'"', false)
            ->assertDontSee('value="'.$unlinkedTechnician->id.'"', false)
            ->assertDontSee('value="'.$pendingTechnician->id.'"', false);

        $this->post(route('interventions.store'), [
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $unlinkedTechnician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'planned',
        ])->assertSessionHasErrors('technician_id');

        $this->post(route('interventions.store'), [
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $pendingTechnician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => 'planned',
        ])->assertSessionHasErrors('technician_id');

        $this->assertSame(0, Intervention::count());
    }

    public function test_equipment_crud_manages_the_shared_inventory_without_a_technician_owner(): void
    {
        $this->withoutVite();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->get(route('equipements.index'))->assertOk();
        $this->get(route('equipements.create'))
            ->assertOk()
            ->assertDontSee('technicien_id');

        $response = $this->post(route('equipements.store'), [
            'name' => 'Multimètre',
            'type' => 'Mesure',
            'serial_number' => 'MT-100',
            'status' => 'available',
            'description' => 'Multimètre terrain',
        ]);

        $response->assertRedirect();
        $equipement = Equipement::where('serial_number', 'MT-100')->firstOrFail();

        $this->get(route('equipements.show', $equipement))->assertOk();
        $this->get(route('equipements.edit', $equipement))->assertOk();

        $this->put(route('equipements.update', $equipement), [
            'name' => 'Multimètre numérique',
            'type' => 'Mesure',
            'serial_number' => 'MT-100',
            'status' => 'in_use',
        ])->assertRedirect(route('equipements.show', $equipement));

        $this->assertDatabaseHas('equipements', [
            'id' => $equipement->id,
            'name' => 'Multimètre numérique',
            'status' => 'in_use',
        ]);

        $this->delete(route('equipements.destroy', $equipement))
            ->assertRedirect(route('equipements.index'));

        $this->assertDatabaseMissing('equipements', ['id' => $equipement->id]);
    }

    public function test_equipment_inventory_can_be_searched_by_name_type_and_serial_number(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Equipement::create([
            'name' => 'Détecteur acoustique',
            'type' => 'Inspection',
            'serial_number' => 'DET-001',
            'status' => 'available',
        ]);
        Equipement::create([
            'name' => 'Caméra de canalisation',
            'type' => 'Inspection vidéo',
            'serial_number' => 'CAM-002',
            'status' => 'available',
        ]);

        $this->actingAs($manager)
            ->get(route('equipements.index', ['search' => 'Détecteur']))
            ->assertOk()
            ->assertSee('Détecteur acoustique')
            ->assertDontSee('Caméra de canalisation')
            ->assertSee('name="search"', false)
            ->assertSee('equipment-search', false)
            ->assertDontSee('résultat(s)')
            ->assertSee('Effacer');

        $this->get(route('equipements.index', ['search' => 'Inspection vidéo']))
            ->assertOk()
            ->assertSee('Caméra de canalisation')
            ->assertDontSee('Détecteur acoustique');

        $this->get(route('equipements.index', ['search' => 'DET-001']))
            ->assertOk()
            ->assertSee('Détecteur acoustique')
            ->assertDontSee('Caméra de canalisation');
    }

    public function test_equipment_image_can_be_uploaded_replaced_and_removed_with_equipment(): void
    {
        Storage::fake('public');
        $pngContent = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
            true
        );
        $this->assertIsString($pngContent);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->post(route('equipements.store'), [
            'name' => 'Détecteur acoustique',
            'type' => 'Détection',
            'status' => 'available',
            'image' => UploadedFile::fake()->createWithContent('detecteur.png', $pngContent),
        ])->assertRedirect();

        $equipment = Equipement::where('name', 'Détecteur acoustique')->firstOrFail();
        $oldImagePath = $equipment->image_path;
        $this->assertNotNull($oldImagePath);
        Storage::disk('public')->assertExists($oldImagePath);

        $this->get(route('equipements.show', $equipment))
            ->assertOk()
            ->assertSee(asset('storage/'.$oldImagePath), false);
        $this->get(route('equipements.index'))
            ->assertOk()
            ->assertSee('<th>Image</th>', false)
            ->assertSee('<th>Équipement</th>', false)
            ->assertSee(asset('storage/'.$oldImagePath), false);
        $this->get(route('equipements.edit', $equipment))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false);

        $this->put(route('equipements.update', $equipment), [
            'name' => $equipment->name,
            'type' => $equipment->type,
            'status' => 'available',
            'image' => UploadedFile::fake()->createWithContent('detecteur-remplacement.png', $pngContent),
        ])->assertRedirect(route('equipements.show', $equipment));

        $newImagePath = $equipment->fresh()->image_path;
        $this->assertNotSame($oldImagePath, $newImagePath);
        Storage::disk('public')->assertMissing($oldImagePath);
        Storage::disk('public')->assertExists($newImagePath);

        $this->delete(route('equipements.destroy', $equipment))
            ->assertRedirect(route('equipements.index'));
        Storage::disk('public')->assertMissing($newImagePath);
    }

    public function test_technician_can_select_multiple_shared_equipements_for_an_assigned_intervention(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $intervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $firstEquipment = Equipement::create([
            'name' => 'Multimètre',
            'type' => 'Mesure',
        ]);
        $secondEquipment = Equipement::create([
            'name' => 'Détecteur',
            'type' => 'Détection',
        ]);

        $this->actingAs($user)
            ->get(route('technicien.interventions.index'))
            ->assertOk()
            ->assertSee('Multimètre')
            ->assertSee('Détecteur');

        $this->post(route('technicien.interventions.respond', $intervention), [
            'response' => 'confirmed',
            'equipements' => [$firstEquipment->id, $secondEquipment->id],
        ])->assertRedirect(route('technicien.interventions.index'));

        $this->assertSame('confirmed', $intervention->fresh()->technician_response);
        $this->assertSame('in_progress', $intervention->fresh()->status);
        $this->assertNotNull($intervention->fresh()->started_at);
        $this->assertSame('in_use', $firstEquipment->fresh()->status);
        $this->assertSame('in_use', $secondEquipment->fresh()->status);
        $this->assertEqualsCanonicalizing(
            [$firstEquipment->id, $secondEquipment->id],
            $intervention->equipements()->pluck('equipements.id')->all()
        );

        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)
            ->get(route('equipements.index'))
            ->assertOk()
            ->assertSee('Réservé par')
            ->assertSee($user->name);

        $this->actingAs($user)
            ->post(route('technicien.interventions.respond', $intervention), [
            'response' => 'refused',
        ])->assertSessionHas('error');
    }

    public function test_expired_in_progress_interventions_are_completed_by_the_scheduled_command(): void
    {
        $scheduledStart = now()->subMinutes(90)->startOfMinute();
        $intervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'team' => 'Équipe terminée automatiquement',
            'scheduled_at' => $scheduledStart,
            'scheduled_duration_minutes' => 60,
            'status' => 'in_progress',
            'started_at' => $scheduledStart,
        ]);
        $equipment = Equipement::create([
            'name' => 'Matériel réservé',
            'type' => 'Inspection',
            'status' => 'in_use',
        ]);
        $intervention->equipements()->attach($equipment);
        $notExpired = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'team' => 'Équipe toujours en cours',
            'scheduled_at' => now()->subMinutes(30),
            'scheduled_duration_minutes' => 60,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $this->artisan('interventions:complete-expired')
            ->assertSuccessful();

        $this->assertSame('completed', $intervention->fresh()->status);
        $this->assertEquals(
            $scheduledStart->copy()->addMinutes(60)->timestamp,
            $intervention->fresh()->completed_at->timestamp
        );
        $this->assertSame('available', $equipment->fresh()->status);
        $this->assertSame('in_progress', $notExpired->fresh()->status);
        $this->assertNull($notExpired->fresh()->completed_at);
    }

    public function test_technician_must_select_an_equipment_before_confirming(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $intervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);

        $this->actingAs($user)
            ->post(route('technicien.interventions.respond', $intervention), [
                'response' => 'confirmed',
            ])
            ->assertSessionHasErrors('equipements');

        $this->assertSame('pending', $intervention->fresh()->technician_response);
    }

    public function test_equipment_used_by_an_open_intervention_is_unavailable_until_that_intervention_is_closed(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $equipment = Equipement::create([
            'name' => 'Détecteur acoustique',
            'type' => 'Détection',
        ]);
        $firstIntervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe 1',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $secondIntervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe 2',
            'scheduled_at' => now()->addDays(2),
            'status' => 'planned',
        ]);
        $this->actingAs($user);

        $this->post(route('technicien.interventions.respond', $firstIntervention), [
            'response' => 'confirmed',
            'equipements' => [$equipment->id],
        ])->assertRedirect(route('technicien.interventions.index'));

        $this->post(route('technicien.interventions.respond', $secondIntervention), [
            'response' => 'confirmed',
            'equipements' => [$equipment->id],
        ])->assertSessionHasErrors('equipements');

        $this->get(route('technicien.interventions.index'))
            ->assertOk()
            ->assertSee('déjà réservé par '.$user->name);

        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)
            ->put(route('interventions.update', $firstIntervention), [
                'incident_id' => $firstIntervention->incident_id,
                'technician_id' => $technician->id,
                'team' => $firstIntervention->team,
                'scheduled_at' => $firstIntervention->scheduled_at->format('Y-m-d H:i:s'),
                'scheduled_duration_minutes' => 60,
                'priority' => 'medium',
                'status' => 'completed',
            ])
            ->assertRedirect(route('interventions.show', $firstIntervention));
        $this->assertSame('available', $equipment->fresh()->status);

        $this->actingAs($user);
        $this->post(route('technicien.interventions.respond', $secondIntervention), [
            'response' => 'confirmed',
            'equipements' => [$equipment->id],
        ])->assertRedirect(route('technicien.interventions.index'));

        $this->assertSame(
            [$equipment->id],
            $secondIntervention->fresh()->equipements()->pluck('equipements.id')->all()
        );
    }

    public function test_removing_technician_from_intervention_returns_it_to_planned_and_releases_equipment(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $technicianUser = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $technicianUser->id,
            'name' => $technicianUser->name,
            'email' => $technicianUser->email,
        ]);
        $equipment = Equipement::create([
            'name' => 'Détecteur acoustique',
            'type' => 'Détection',
            'status' => 'in_use',
        ]);
        $intervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'scheduled_duration_minutes' => 60,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(15),
            'technician_response' => 'confirmed',
            'technician_response_at' => now()->subMinutes(15),
        ]);
        $intervention->equipements()->attach($equipment);

        $this->actingAs($manager)
            ->put(route('interventions.update', $intervention), [
                'incident_id' => $intervention->incident_id,
                'technician_id' => '',
                'team' => $intervention->team,
                'scheduled_at' => $intervention->scheduled_at->format('Y-m-d H:i:s'),
                'scheduled_duration_minutes' => 60,
                'priority' => 'medium',
                'status' => 'in_progress',
                'started_at' => $intervention->started_at->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('interventions.show', $intervention));

        $this->assertDatabaseHas('interventions', [
            'id' => $intervention->id,
            'technician_id' => null,
            'status' => 'planned',
            'started_at' => null,
            'completed_at' => null,
            'technician_response' => 'pending',
            'technician_response_at' => null,
        ]);
        $this->assertDatabaseMissing('equipement_intervention', [
            'intervention_id' => $intervention->id,
            'equipement_id' => $equipment->id,
        ]);
        $this->assertSame('available', $equipment->fresh()->status);
    }

    public function test_technician_can_refuse_without_selecting_equipements(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'technician']);
        $technician = Technicien::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $intervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $technician->id,
            'team' => 'Équipe terrain',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $equipment = Equipement::create([
            'name' => 'Multimètre',
            'type' => 'Mesure',
        ]);

        $this->actingAs($user)
            ->post(route('technicien.interventions.respond', $intervention), [
                'response' => 'refused',
                'equipements' => [$equipment->id],
            ])
            ->assertRedirect(route('technicien.interventions.index'));

        $this->assertSame('refused', $intervention->fresh()->technician_response);
        $this->assertSame(0, $intervention->equipements()->count());
    }

    public function test_technician_can_select_shared_equipment_but_cannot_respond_to_another_intervention(): void
    {
        $this->withoutVite();
        $firstUser = User::factory()->create(['role' => 'technician']);
        $firstTechnician = Technicien::create([
            'user_id' => $firstUser->id,
            'name' => $firstUser->name,
            'email' => $firstUser->email,
        ]);
        $secondUser = User::factory()->create(['role' => 'technician']);
        $secondTechnician = Technicien::create([
            'user_id' => $secondUser->id,
            'name' => $secondUser->name,
            'email' => $secondUser->email,
        ]);
        $ownIntervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $firstTechnician->id,
            'team' => 'Équipe 1',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $otherIntervention = Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $secondTechnician->id,
            'team' => 'Équipe 2',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        $sharedEquipment = Equipement::create([
            'name' => 'Caméra thermique',
            'type' => 'Inspection',
        ]);

        $this->actingAs($firstUser)
            ->post(route('technicien.interventions.respond', $ownIntervention), [
                'response' => 'confirmed',
                'equipements' => [$sharedEquipment->id],
            ])
            ->assertRedirect(route('technicien.interventions.index'));
        $this->assertSame(
            [$sharedEquipment->id],
            $ownIntervention->fresh()->equipements()->pluck('equipements.id')->all()
        );

        $this->post(route('technicien.interventions.respond', $otherIntervention), [
            'response' => 'refused',
            'equipements' => [],
        ])->assertForbidden();
    }
}
