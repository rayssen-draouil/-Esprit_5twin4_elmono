<?php

namespace Database\Seeders;

use App\Models\Financement;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RealProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin', 'name' => 'Admin AquaSecure', 'email' => 'admin@aquasecure.fr', 'password' => Hash::make('password'), 'email_verified_at' => now()]);

        $manager = User::where('role', 'manager')->first()
            ?? User::factory()->create(['role' => 'manager', 'name' => 'Manager AquaSecure', 'email' => 'manager@aquasecure.fr', 'password' => Hash::make('password'), 'email_verified_at' => now()]);

        $projects = [
            [
                'name' => 'Réseau d\'eau potable Sfax',
                'type' => 'Infrastructure hydraulique',
                'description' => 'Construction et mise en service d\'un réseau de distribution d\'eau potable pour la région de Sfax, incluant 45 km de canalisations, 3 stations de pompage et 2 réservoirs de stockage.',
                'budget' => 2500000.00,
                'start_date' => '2025-03-01',
                'end_date' => '2027-09-30',
                'status' => 'in_progress',
                'progress' => 45,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Mondiale - Projet Eau Tunisie', 'amount' => 1200000.00, 'status' => 'received', 'funded_at' => '2025-04-15', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Fonds National de l\'Eau', 'amount' => 800000.00, 'status' => 'received', 'funded_at' => '2025-06-01', 'validation_status' => 'approved'],
                    ['source' => 'Union Européenne - Facilité d\'Investissement pour le Voisinage', 'amount' => 300000.00, 'status' => 'planned', 'funded_at' => '2026-01-15', 'validation_status' => 'pending_approval'],
                ],
            ],
            [
                'name' => 'Station d\'épuration Gabès',
                'type' => 'Traitement des eaux usées',
                'description' => 'Construction d\'une station d\'épuration des eaux usées pour la zone industrielle de Gabès, capacité 15 000 m³/jour, avec traitement tertiaire et réutilisation des eaux traitées pour l\'irrigation.',
                'budget' => 1800000.00,
                'start_date' => '2025-06-15',
                'end_date' => '2027-03-31',
                'status' => 'in_progress',
                'progress' => 30,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Africaine de Développement - Eau et Assainissement', 'amount' => 900000.00, 'status' => 'received', 'funded_at' => '2025-07-01', 'validation_status' => 'approved'],
                    ['source' => 'Fonds Vert pour le Climat', 'amount' => 600000.00, 'status' => 'received', 'funded_at' => '2025-09-15', 'validation_status' => 'approved'],
                    ['source' => 'Ministère de l\'Environnement Tunisien', 'amount' => 200000.00, 'status' => 'planned', 'funded_at' => '2026-03-01', 'validation_status' => 'pending_approval'],
                ],
            ],
            [
                'name' => 'Barrage de retenue Bizerte',
                'type' => 'Ouvrage hydraulique',
                'description' => 'Construction d\'un barrage de retenue d\'eau sur l\'oued Tine pour sécuriser l\'approvisionnement en eau potable de la région de Bizerte, capacité 12 millions de m³.',
                'budget' => 4200000.00,
                'start_date' => '2024-09-01',
                'end_date' => '2027-12-31',
                'status' => 'in_progress',
                'progress' => 65,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Européenne d\'Investissement - Infrastructure Eau', 'amount' => 2000000.00, 'status' => 'received', 'funded_at' => '2024-10-15', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Fonds National de l\'Eau', 'amount' => 1500000.00, 'status' => 'received', 'funded_at' => '2025-01-10', 'validation_status' => 'approved'],
                    ['source' => 'Fonds Arabe pour le Développement Économique et Social', 'amount' => 500000.00, 'status' => 'received', 'funded_at' => '2025-03-20', 'validation_status' => 'approved'],
                ],
            ],
            [
                'name' => 'Réseau d\'irrigation Kairouan',
                'type' => 'Irrigation agricole',
                'description' => 'Modernisation du réseau d\'irrigation pour 2 500 hectares de terres agricoles dans la région de Kairouan, incluant 80 km de canalisations, 12 stations de pompage solaire et système de télémétrie.',
                'budget' => 3200000.00,
                'start_date' => '2025-01-15',
                'end_date' => '2026-12-31',
                'status' => 'in_progress',
                'progress' => 70,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Mondiale - Agriculture et Eau', 'amount' => 1600000.00, 'status' => 'received', 'funded_at' => '2025-02-01', 'validation_status' => 'approved'],
                    ['source' => 'Fonds International de Développement Agricole (FIDA)', 'amount' => 1000000.00, 'status' => 'received', 'funded_at' => '2025-04-10', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Ministère de l\'Agriculture', 'amount' => 400000.00, 'status' => 'planned', 'funded_at' => '2025-08-01', 'validation_status' => 'pending_approval'],
                ],
            ],
            [
                'name' => 'Dessalement d\'eau de mer Djerba',
                'type' => 'Dessalement',
                'description' => 'Construction d\'une usine de dessalement d\'eau de mer à Djerba, capacité 50 000 m³/jour, alimentée par énergie solaire, pour sécuriser l\'approvisionnement en eau potable de l\'île.',
                'budget' => 5500000.00,
                'start_date' => '2025-09-01',
                'end_date' => '2028-06-30',
                'status' => 'planned',
                'progress' => 15,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Européenne d\'Investissement - Energie et Eau', 'amount' => 2500000.00, 'status' => 'received', 'funded_at' => '2025-10-01', 'validation_status' => 'approved'],
                    ['source' => 'Fonds Vert pour le Climat - Énergie Renouvelable', 'amount' => 1800000.00, 'status' => 'received', 'funded_at' => '2025-11-15', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Fonds National de l\'Eau', 'amount' => 900000.00, 'status' => 'planned', 'funded_at' => '2026-06-01', 'validation_status' => 'pending_approval'],
                ],
            ],
            [
                'name' => 'Réhabilitation réseau eau Sousse',
                'type' => 'Réhabilitation infrastructure',
                'description' => 'Réhabilitation complète du réseau d\'eau potable de Sousse : remplacement de 60 km de canalisations vétustes, rénovation de 5 stations de pompage et mise en place d\'un système de détection de fuites.',
                'budget' => 1500000.00,
                'start_date' => '2024-06-01',
                'end_date' => '2026-03-31',
                'status' => 'in_progress',
                'progress' => 85,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Union Européenne - Fonds de Cohésion', 'amount' => 700000.00, 'status' => 'received', 'funded_at' => '2024-07-15', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Fonds National de l\'Eau', 'amount' => 500000.00, 'status' => 'received', 'funded_at' => '2024-09-01', 'validation_status' => 'approved'],
                    ['source' => 'Agence Française de Développement - Eau et Assainissement', 'amount' => 200000.00, 'status' => 'received', 'funded_at' => '2024-11-10', 'validation_status' => 'approved'],
                ],
            ],
            [
                'name' => 'Télémétrie et capteurs qualité Monastir',
                'type' => 'Surveillance et monitoring',
                'description' => 'Déploiement d\'un réseau de 200 capteurs de qualité d\'eau (pH, turbidité, chlore, température) avec transmission en temps réel et tableau de bord de supervision pour la région de Monastir.',
                'budget' => 650000.00,
                'start_date' => '2025-11-01',
                'end_date' => '2026-10-31',
                'status' => 'in_progress',
                'progress' => 55,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Mondiale - Innovation Eau', 'amount' => 350000.00, 'status' => 'received', 'funded_at' => '2025-12-01', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Fonds National de l\'Eau', 'amount' => 200000.00, 'status' => 'received', 'funded_at' => '2026-01-15', 'validation_status' => 'approved'],
                    ['source' => 'Union Européenne - Programme Horizon Eau', 'amount' => 80000.00, 'status' => 'planned', 'funded_at' => '2026-05-01', 'validation_status' => 'pending_approval'],
                ],
            ],
            [
                'name' => 'Protection des nappes phréatiques Nabeul',
                'type' => 'Protection environnementale',
                'description' => 'Programme de protection et de recharge des nappes phréatiques de la région de Nabeul : 15 bassins d\'infiltration, 8 forages de contrôle, et système de gestion intégrée des ressources en eau.',
                'budget' => 2800000.00,
                'start_date' => '2025-02-01',
                'end_date' => '2027-08-31',
                'status' => 'in_progress',
                'progress' => 40,
                'validation_status' => 'approved',
                'validated_at' => now(),
                'validated_by' => $admin->id,
                'financements' => [
                    ['source' => 'Banque Européenne d\'Investissement - Environnement', 'amount' => 1400000.00, 'status' => 'received', 'funded_at' => '2025-03-15', 'validation_status' => 'approved'],
                    ['source' => 'Fonds pour l\'Environnement Mondial (FEM)', 'amount' => 900000.00, 'status' => 'received', 'funded_at' => '2025-05-20', 'validation_status' => 'approved'],
                    ['source' => 'Gouvernement Tunisien - Ministère de l\'Environnement', 'amount' => 300000.00, 'status' => 'planned', 'funded_at' => '2026-02-01', 'validation_status' => 'pending_approval'],
                ],
            ],
        ];

        foreach ($projects as $data) {
            $financements = $data['financements'];
            unset($data['financements']);

            $project = Project::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['user_id' => $admin->id])
            );

            foreach ($financements as $f) {
                Financement::firstOrCreate(
                    ['source' => $f['source'], 'project_id' => $project->id],
                    array_merge($f, ['user_id' => $admin->id])
                );
            }
        }

        $this->command->info('Real projects and approved financings seeded successfully.');
    }
}
