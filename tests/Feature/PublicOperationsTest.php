<?php

namespace Tests\Feature;

use App\Models\Equipement;
use App\Models\Incident;
use App\Models\Intervention;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_operations_page_shows_aggregated_status_and_approved_categories_only(): void
    {
        $approvedUser = User::factory()->create([
            'name' => 'Private Technician Name',
            'email' => 'private-technician@example.com',
            'role' => 'technician',
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_APPROVED,
        ]);
        $approvedTechnician = Technicien::create([
            'user_id' => $approvedUser->id,
            'name' => $approvedUser->name,
            'email' => $approvedUser->email,
            'phone' => '+216 20 123 456',
            'speciality' => 'Détection de fuites',
            'skills' => 'Compétence confidentielle détaillée',
        ]);
        Equipement::create([
            'name' => 'Équipement confidentiel',
            'type' => 'Détection acoustique',
            'serial_number' => 'SERIAL-PRIVATE-001',
            'status' => 'available',
        ]);
        Equipement::create([
            'name' => 'Équipement de canalisation',
            'type' => 'Coupure et raccordement de canalisation',
            'status' => 'available',
        ]);
        $incident = Incident::factory()->create([
            'description' => 'Description privée de l’incident',
            'location' => 'Adresse privée complète',
        ]);
        Intervention::create([
            'incident_id' => $incident->id,
            'technician_id' => $approvedTechnician->id,
            'team' => 'Nom d’équipe privée',
            'scheduled_at' => now()->addDay(),
            'status' => 'planned',
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $approvedTechnician->id,
            'team' => 'Autre équipe privée',
            'scheduled_at' => now()->addDays(3),
            'status' => 'planned',
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $approvedTechnician->id,
            'team' => 'Équipe en cours privée',
            'scheduled_at' => now()->addDays(2),
            'status' => 'in_progress',
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $approvedTechnician->id,
            'team' => 'Équipe terminée privée',
            'scheduled_at' => now()->subDay(),
            'status' => 'completed',
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $approvedTechnician->id,
            'team' => 'Équipe annulée privée',
            'scheduled_at' => now()->subDays(2),
            'status' => 'cancelled',
        ]);
        Intervention::create([
            'incident_id' => Incident::factory()->create()->id,
            'technician_id' => $approvedTechnician->id,
            'team' => 'Équipe ancienne en cours privée',
            'scheduled_at' => now()->subDays(40),
            'status' => 'in_progress',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $pendingUser = User::factory()->create([
            'role' => 'technician',
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_PENDING,
        ]);
        $pendingTechnician = Technicien::create([
            'user_id' => $pendingUser->id,
            'name' => $pendingUser->name,
            'email' => $pendingUser->email,
            'speciality' => 'Spécialité en attente',
        ]);
        Equipement::create([
            'name' => 'Matériel en attente',
            'type' => 'Catégorie en attente',
            'serial_number' => 'SERIAL-PENDING-001',
        ]);

        $this->get(route('front.operations'))
            ->assertOk()
            ->assertSee('Détection de fuites')
            ->assertSee('Détection acoustique')
            ->assertSee('PlanifiÃ©es')
            ->assertSee('En cours')
            ->assertSee('Terminées')
            ->assertSee('Activité des 30 derniers jours')
            ->assertSee('Données actualisées le')
            ->assertSee('data-status="planned"', false)
            ->assertSee('>2</h3>', false)
            ->assertSee('aria-label="2 interventions en cours"', false)
            ->assertSee('Consulter les signalements')
            ->assertSee('Voir les zones')
            ->assertSee('operations-category-card', false)
            ->assertSee('Catégorie en attente')
            ->assertSee('src="'.asset('Détecteur acoustique de fuites.webp').'"', false)
            ->assertSee('alt="Détection acoustique"', false)
            ->assertSee('src="'.asset('Vanne de coupure DN50.webp').'"', false)
            ->assertSee('.operations-specialty-icon svg', false)
            ->assertSee('position: absolute', false)
            ->assertDontSee('Spécialité en attente')
            ->assertDontSee('Private Technician Name')
            ->assertDontSee('private-technician@example.com')
            ->assertDontSee('+216 20 123 456')
            ->assertDontSee('Compétence confidentielle détaillée')
            ->assertDontSee('Équipement confidentiel')
            ->assertDontSee('SERIAL-PRIVATE-001')
            ->assertDontSee('Adresse privée complète')
            ->assertDontSee('Description privée de l’incident')
            ->assertDontSee('Nom d’équipe privée')
            ->assertDontSee('Équipe annulée privée')
            ->assertDontSee('Équipe ancienne en cours privée');
    }
}
