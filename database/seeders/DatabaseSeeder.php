<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Financement;
use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Intervention;
use App\Models\Project;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ZoneSeeder::class);

        // 1. Users
        $admin = User::updateOrCreate(
            ['email' => 'claire@aquasecure.fr'],
            [
                'name' => 'Claire Martin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        $manager = User::updateOrCreate(
            ['email' => 'thomas@aquasecure.fr'],
            [
                'name' => 'Thomas Bernard',
                'password' => Hash::make('password123'),
                'role' => 'manager',
            ]
        );

        $technician = User::updateOrCreate(
            ['email' => 'sarah@aquasecure.fr'],
            [
                'name' => 'Sarah Petit',
                'password' => Hash::make('password123'),
                'role' => 'technician',
            ]
        );

        $citizen = User::updateOrCreate(
            ['email' => 'citoyen@aquasecure.fr'],
            [
                'name' => 'Jean Dupont',
                'password' => Hash::make('password123'),
                'role' => 'citizen',
            ]
        );

        // 2. Zones
        $zoneOccitanie = Zone::firstOrCreate(
            ['name' => 'Occitanie'],
            [
                'address' => 'Montpellier, Occitanie',
                'description' => 'Bassin versant et littoral méditerranéen',
                'risk_level' => 'high',
            ]
        );

        $zoneRhone = Zone::firstOrCreate(
            ['name' => 'Auvergne-Rhône-Alpes'],
            [
                'address' => 'Lyon, Rhône',
                'description' => 'Vallée fluviale et zones inondables du Rhône',
                'risk_level' => 'medium',
            ]
        );

        $zoneAquitaine = Zone::firstOrCreate(
            ['name' => 'Nouvelle-Aquitaine'],
            [
                'address' => 'Bordeaux, Nouvelle-Aquitaine',
                'description' => 'Bassin Adour-Garonne et zones humides',
                'risk_level' => 'low',
            ]
        );

        // 3. Infrastructures
        $infraSete = Infrastructure::firstOrCreate(
            ['name' => 'Station Sète Nord'],
            [
                'zone_id' => $zoneOccitanie->id,
                'type' => 'Station de pompage',
                'description' => 'Station principale de pompage et de traitement primaire.',
                'status' => 'operational',
                'installation_date' => '2023-04-12',
                'last_maintenance_date' => '2026-08-15',
            ]
        );

        $infraBarage = Infrastructure::firstOrCreate(
            ['name' => 'Barrage de Pierre-Bénite'],
            [
                'zone_id' => $zoneRhone->id,
                'type' => 'Barrage',
                'description' => 'Ouvrage de régulation des flux et de retenue d eau.',
                'status' => 'operational',
                'installation_date' => '2021-09-01',
                'last_maintenance_date' => '2026-07-20',
            ]
        );

        $infraAdour = Infrastructure::firstOrCreate(
            ['name' => 'Réseau Adour 01'],
            [
                'zone_id' => $zoneAquitaine->id,
                'type' => 'Capteurs qualité',
                'description' => 'Réseau de capteurs télémétriques de turbidité et salinité.',
                'status' => 'maintenance',
                'installation_date' => '2024-02-18',
                'last_maintenance_date' => '2026-09-30',
            ]
        );

        // 4. Incidents
        $inc1 = Incident::firstOrCreate(
            ['description' => 'Niveau critique — station de pompage'],
            [
                'zone_id' => $zoneOccitanie->id,
                'infrastructure_id' => $infraSete->id,
                'type' => 'Équipement hors ligne',
                'status' => 'reported',
                'location' => 'Littoral Méditerranée — Sète',
                'reported_at' => now()->subHours(2),
            ]
        );

        $inc2 = Incident::firstOrCreate(
            ['description' => 'Capteur de turbidité hors ligne'],
            [
                'zone_id' => $zoneRhone->id,
                'infrastructure_id' => $infraBarage->id,
                'type' => 'Équipement hors ligne',
                'status' => 'in_progress',
                'location' => 'Vallée du Rhône — Pierre-Bénite',
                'reported_at' => now()->subDay(),
            ]
        );

        $inc3 = Incident::firstOrCreate(
            ['description' => 'Anomalie qualité de l eau résorbée après purge'],
            [
                'zone_id' => $zoneAquitaine->id,
                'infrastructure_id' => $infraAdour->id,
                'type' => 'Contamination',
                'status' => 'resolved',
                'location' => 'Bassin Adour-Garonne',
                'reported_at' => now()->subDays(3),
                'resolved_at' => now()->subDay(),
            ]
        );

        // 5. Projects
        $proj1 = Project::firstOrCreate(
            ['name' => 'Littoral Méditerranée'],
            [
                'type' => 'Surveillance côtière',
                'description' => 'Déploiement massif de balises de télémétrie en zone côtière.',
                'budget' => 1200000.00,
                'start_date' => '2025-01-01',
                'end_date' => '2026-12-31',
                'status' => 'in_progress',
                'progress' => 92,
            ]
        );

        $proj2 = Project::firstOrCreate(
            ['name' => 'Vallée du Rhône'],
            [
                'type' => 'Prévention inondation',
                'description' => 'Système d alerte précoce pour les crues saisonnières.',
                'budget' => 860000.00,
                'start_date' => '2025-06-01',
                'end_date' => '2027-06-30',
                'status' => 'in_progress',
                'progress' => 64,
            ]
        );

        $proj3 = Project::firstOrCreate(
            ['name' => 'Bassin Adour-Garonne'],
            [
                'type' => 'Qualité de l’eau',
                'description' => 'Analyse continue des rejets et surveillance des nappes.',
                'budget' => 540000.00,
                'start_date' => '2026-01-01',
                'end_date' => '2027-12-31',
                'status' => 'planned',
                'progress' => 28,
            ]
        );

        // 6. Financements
        Financement::firstOrCreate(
            ['source' => 'Union Européenne - Fonds bleu', 'project_id' => $proj1->id],
            [
                'amount' => 800000.00,
                'status' => 'received',
                'funded_at' => '2025-03-01',
            ]
        );

        Financement::firstOrCreate(
            ['source' => 'Agence de l Eau - Aqua Transition', 'project_id' => $proj2->id],
            [
                'amount' => 500000.00,
                'status' => 'received',
                'funded_at' => '2025-08-15',
            ]
        );

        Financement::firstOrCreate(
            ['source' => 'Région Nouvelle-Aquitaine - Résilience', 'project_id' => $proj3->id],
            [
                'amount' => 300000.00,
                'status' => 'planned',
                'funded_at' => '2026-02-01',
            ]
        );

        // 7. Interventions
        Intervention::firstOrCreate(
            ['incident_id' => $inc1->id, 'team' => 'Équipe d urgence Littoral'],
            [
                'scheduled_at' => now()->addHours(2),
                'status' => 'planned',
                'result' => null,
            ]
        );

        Intervention::firstOrCreate(
            ['incident_id' => $inc2->id, 'team' => 'Techniciens Capteurs Rhône'],
            [
                'scheduled_at' => now()->subHours(4),
                'status' => 'in_progress',
                'started_at' => now()->subHours(2),
                'result' => 'Diagnostic en cours sur le bus de communication.',
            ]
        );

        // 8. Alerts
        Alert::firstOrCreate(
            ['incident_id' => $inc1->id],
            [
                'zone_id' => $zoneOccitanie->id,
                'type' => 'Capteur critique',
                'severity' => 'critical',
                'message' => 'Niveau d eau critique détecté à la station Sète Nord',
            ]
        );

        Alert::firstOrCreate(
            ['incident_id' => $inc2->id],
            [
                'zone_id' => $zoneRhone->id,
                'type' => 'Déconnexion équipement',
                'severity' => 'medium',
                'message' => 'Capteur #A-204 hors ligne depuis 42 min',
            ]
        );
    }
}
