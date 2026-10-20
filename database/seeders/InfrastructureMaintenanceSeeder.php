<?php

namespace Database\Seeders;

use App\Models\Infrastructure;
use App\Models\Maintenance;
use App\Models\Technicien;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class InfrastructureMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tunisian Zones (SONEDE & DGBGTH districts)
        $zoneGrandTunis = Zone::firstOrCreate(
            ['name' => 'Grand Tunis (SONEDE District Tunis)'],
            [
                'address' => 'Avenue Slimane Ben Slimane, Manouba, Tunis',
                'description' => 'Zone métropolitaine du Grand Tunis englobant les stations de traitement de Ghdir El Golla et réservoirs de charge.',
                'risk_level' => 'high',
            ]
        );

        $zoneNordOuest = Zone::firstOrCreate(
            ['name' => 'Nord-Ouest (Bassin Medjerda - DGBGTH Béja)'],
            [
                'address' => 'Route de Tabarka, Béja / Testour',
                'description' => 'Bassin versant majeur de la Medjerda, abritant le grand barrage hydrotechnique de Sidi Salem.',
                'risk_level' => 'high',
            ]
        );

        $zoneCapBon = Zone::firstOrCreate(
            ['name' => 'Cap Bon (SONEDE District Nabeul)'],
            [
                'address' => 'Avenue Habib Bourguiba, Nabeul / Grombalia',
                'description' => 'Péninsule du Cap Bon alimentée par le canal Medjerda-Cap Bon et la station de surpression de Belli.',
                'risk_level' => 'medium',
            ]
        );

        $zoneSudLittoral = Zone::firstOrCreate(
            ['name' => 'Sud & Littoral (SONEDE Gabès & Zarat)'],
            [
                'address' => 'Zarat, Golfe de Gabès',
                'description' => 'Zone littorale du Sud tunisien desservie par la station de dessalement d’eau de mer de Zarat.',
                'risk_level' => 'critical',
            ]
        );

        // 2. Tunisian Technicians (SONEDE & DGBGTH engineers & field technicians)
        $techTrabelsi = Technicien::firstOrCreate(
            ['email' => 'm.trabelsi@sonede.tn'],
            [
                'name' => 'Ing. Mohamed Trabelsi',
                'phone' => '+216 71 888 101',
                'speciality' => 'Électromécanique & Groupes Motopompes (SONEDE)',
                'status' => 'available',
            ]
        );

        $techBenSalem = Technicien::firstOrCreate(
            ['email' => 'a.bensalem@agriculture.gov.tn'],
            [
                'name' => 'Ing. Ahmed Ben Salem',
                'phone' => '+216 78 450 200',
                'speciality' => 'Génie Civil & Auscultation Barrages (DGBGTH)',
                'status' => 'available',
            ]
        );

        $techMansouri = Technicien::firstOrCreate(
            ['email' => 'y.mansouri@sonede.tn'],
            [
                'name' => 'Techn. Youssef Mansouri',
                'phone' => '+216 75 290 300',
                'speciality' => 'Dessalement d\'Eau de Mer & Osmose Inverse (SONEDE Zarat)',
                'status' => 'busy',
            ]
        );

        $techZouari = Technicien::firstOrCreate(
            ['email' => 'k.zouari@sonede.tn'],
            [
                'name' => 'Techn. Karim Zouari',
                'phone' => '+216 72 285 400',
                'speciality' => 'Réseaux de Distribution & Détection de Fuites (SONEDE Cap Bon)',
                'status' => 'available',
            ]
        );

        $techKhemiri = Technicien::firstOrCreate(
            ['email' => 'f.khemiri@sonede.tn'],
            [
                'name' => 'Ing. Fatma Khemiri',
                'phone' => '+216 71 500 600',
                'speciality' => 'Automatisme SCADA & Télémétrie Qualité (SONEDE Ghdir El Golla)',
                'status' => 'available',
            ]
        );

        // 3. Tunisian Water Infrastructures (SONEDE & DGBGTH)
        $infraGhdir = Infrastructure::updateOrCreate(
            ['reference_code' => 'INF-TUN-001'],
            [
                'name' => 'Station de Traitement de Ghdir El Golla (SONEDE)',
                'zone_id' => $zoneGrandTunis->id,
                'type' => 'Station de traitement d\'eau potable',
                'location' => 'Ghdir El Golla, Manouba / Grand Tunis',
                'latitude' => 36.793600,
                'longitude' => 10.057300,
                'capacity' => '650 000 m³/jour (Grand Tunis)',
                'condition' => 'good',
                'criticality' => 'vital',
                'status' => 'operational',
                'description' => 'Complexe névralgique de potabilisation de la SONEDE alimentant plus de 2.8 millions d’habitants. Traitement physico-chimique, décantation pulsator, filtration sur sable et chloration de l’eau brute en provenance du bassin Medjerda.',
                'installation_date' => '2018-05-15',
                'commissioning_date' => '2018-07-01',
                'last_maintenance_date' => now()->subMonths(1)->format('Y-m-d'),
                'next_maintenance_date' => now()->addDays(15)->format('Y-m-d'),
            ]
        );

        $infraSidiSalem = Infrastructure::updateOrCreate(
            ['reference_code' => 'INF-TUN-002'],
            [
                'name' => 'Grand Barrage de Sidi Salem (DGBGTH - Medjerda)',
                'zone_id' => $zoneNordOuest->id,
                'type' => 'Barrage hydrotechnique',
                'location' => 'Testour, Gouvernorat de Béja',
                'latitude' => 36.591900,
                'longitude' => 9.394200,
                'capacity' => '580 000 000 m³ (Retenue maximale)',
                'condition' => 'good',
                'criticality' => 'vital',
                'status' => 'operational',
                'description' => 'Plus grand ouvrage hydraulique de Tunisie sous la tutelle de la DGBGTH. Régule les crues de l’Oued Medjerda, alimente le canal de transfert Medjerda-Cap Bon et garantit la réserve stratégique nationale en eau douce.',
                'installation_date' => '2010-03-20',
                'commissioning_date' => '2011-01-01',
                'last_maintenance_date' => now()->subMonths(3)->format('Y-m-d'),
                'next_maintenance_date' => now()->addMonths(2)->format('Y-m-d'),
            ]
        );

        $infraZarat = Infrastructure::updateOrCreate(
            ['reference_code' => 'INF-TUN-003'],
            [
                'name' => 'Station de Dessalement d’Eau de Mer de Zarat (SONEDE Gabès)',
                'zone_id' => $zoneSudLittoral->id,
                'type' => 'Station de dessalement d\'eau de mer',
                'location' => 'Zarat, Golfe de Gabès',
                'latitude' => 33.687200,
                'longitude' => 10.354100,
                'capacity' => '50 000 m³/jour (Extensible à 100 000 m³/j)',
                'condition' => 'excellent',
                'criticality' => 'vital',
                'status' => 'operational',
                'description' => 'Mégaprojet moderne de la SONEDE utilisant l’osmose inverse haute pression pour alimenter en eau potable les gouvernorats de Gabès, Médenine et Tataouine, réduisant la surexploitation des nappes profondes du Sud.',
                'installation_date' => '2023-11-10',
                'commissioning_date' => '2024-04-15',
                'last_maintenance_date' => now()->subMonths(1)->format('Y-m-d'),
                'next_maintenance_date' => now()->addDays(28)->format('Y-m-d'),
            ]
        );

        $infraBelli = Infrastructure::updateOrCreate(
            ['reference_code' => 'INF-TUN-004'],
            [
                'name' => 'Station de Pompage & Prélèvement de Belli (SONEDE Cap Bon)',
                'zone_id' => $zoneCapBon->id,
                'type' => 'Station de pompage',
                'location' => 'Belli, Grombalia, Gouvernorat de Nabeul',
                'latitude' => 36.602500,
                'longitude' => 10.536900,
                'capacity' => '120 000 m³/jour',
                'condition' => 'fair',
                'criticality' => 'high',
                'status' => 'maintenance',
                'description' => 'Nœud stratégique de surpression de la SONEDE sur le canal d’adduction Medjerda-Cap Bon, desservant les réseaux d’eau potable de Nabeul, Hammamet et les plaines agricoles de Grombalia.',
                'installation_date' => '2019-06-12',
                'commissioning_date' => '2019-09-01',
                'last_maintenance_date' => now()->subWeeks(3)->format('Y-m-d'),
                'next_maintenance_date' => now()->subDays(2)->format('Y-m-d'), // En retard !
            ]
        );

        $infraRasTabia = Infrastructure::updateOrCreate(
            ['reference_code' => 'INF-TUN-005'],
            [
                'name' => 'Réservoir Principal de Ras Tabia (SONEDE Grand Tunis)',
                'zone_id' => $zoneGrandTunis->id,
                'type' => 'Réservoir de stockage',
                'location' => 'Colline de Ras Tabia, Le Bardo, Tunis',
                'latitude' => 36.818800,
                'longitude' => 10.138200,
                'capacity' => '80 000 m³ (2 cuves de distribution)',
                'condition' => 'poor',
                'criticality' => 'high',
                'status' => 'critical',
                'description' => 'Réservoir de charge et d’équilibrage alimentant les secteurs de Tunis-Ouest, Le Bardo, Manouba et Montfleury. Sous surveillance étroite suite à des micro-fissures constatées et des baisses de pression.',
                'installation_date' => '2015-02-10',
                'commissioning_date' => '2015-05-01',
                'last_maintenance_date' => now()->subMonths(5)->format('Y-m-d'),
                'next_maintenance_date' => now()->subDays(8)->format('Y-m-d'), // En retard !
            ]
        );

        $infraCanal = Infrastructure::updateOrCreate(
            ['reference_code' => 'INF-TUN-006'],
            [
                'name' => 'Canal d’Adduction Medjerda - Cap Bon (SECADENORD / DGBGTH)',
                'zone_id' => $zoneCapBon->id,
                'type' => 'Canal d\'adduction & Transfert',
                'location' => 'Tronçon Mornag - Grombalia, Ben Arous',
                'latitude' => 36.678100,
                'longitude' => 10.291700,
                'capacity' => '470 000 000 m³/an',
                'condition' => 'fair',
                'criticality' => 'vital',
                'status' => 'operational',
                'description' => 'Artère hydraulique majeure de 120 kilomètres assurant le transfert gravitaire des eaux du Nord tunisien vers le Grand Tunis, le Cap Bon, le Sahel et Sfax.',
                'installation_date' => '2012-04-01',
                'commissioning_date' => '2012-08-01',
                'last_maintenance_date' => now()->subMonths(2)->format('Y-m-d'),
                'next_maintenance_date' => now()->addDays(40)->format('Y-m-d'),
            ]
        );

        // 4. Tunisian Maintenances (Technical operations & Citizen Malfunction Reports)
        
        // Operation 1 - Completed (Ghdir El Golla)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-001'],
            [
                'infrastructure_id' => $infraGhdir->id,
                'technician_id' => $techTrabelsi->id,
                'team' => 'Équipe Électromécanique SONEDE Tunis',
                'type' => 'Préventive',
                'priority' => 'medium',
                'status' => 'completed',
                'cost' => 3200.00,
                'duration_hours' => 6.0,
                'description' => 'Contrôle semestriel des décanteurs pulsateurs et révision des pompes doseuses de coagulant à Ghdir El Golla.',
                'result' => 'Remplacement des joints d’étanchéité et étalonnage des analyseurs de turbidité en ligne. Rendement nominal validé à 100%.',
                'scheduled_at' => now()->subMonths(1)->subDays(3)->setTime(8, 30),
                'started_at' => now()->subMonths(1)->subDays(3)->setTime(8, 45),
                'completed_at' => now()->subMonths(1)->subDays(3)->setTime(14, 45),
                'performed_at' => now()->subMonths(1)->subDays(3)->setTime(14, 45),
                'next_maintenance_date' => now()->addDays(15),
            ]
        );

        // Operation 2 - Planned (Ghdir El Golla)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-002'],
            [
                'infrastructure_id' => $infraGhdir->id,
                'technician_id' => $techKhemiri->id,
                'team' => 'Service Télémétrie SCADA SONEDE',
                'type' => 'Inspection',
                'priority' => 'low',
                'status' => 'planned',
                'cost' => 950.00,
                'duration_hours' => 2.5,
                'description' => 'Audit périodique de l’automate centralisé de télégestion et vérification des sondes de chlore résiduel libre.',
                'scheduled_at' => now()->addDays(15)->setTime(10, 0),
                'next_maintenance_date' => now()->addMonths(6),
            ]
        );

        // Operation 3 - Completed (Barrage Sidi Salem)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-003'],
            [
                'infrastructure_id' => $infraSidiSalem->id,
                'technician_id' => $techBenSalem->id,
                'team' => 'Direction Auscultation des Grands Barrages (DGBGTH)',
                'type' => 'Réglementaire',
                'priority' => 'high',
                'status' => 'completed',
                'cost' => 7500.00,
                'duration_hours' => 9.0,
                'description' => 'Auscultation topographique et piézométrique du corps de digue du barrage de Sidi Salem, avec inspection des vannes de décharge de fond.',
                'result' => 'Stabilité géotechnique conforme aux normes de sûreté hydraulique. Pression hydrostatique normale.',
                'scheduled_at' => now()->subMonths(3)->setTime(7, 30),
                'started_at' => now()->subMonths(3)->setTime(8, 0),
                'completed_at' => now()->subMonths(3)->setTime(17, 0),
                'performed_at' => now()->subMonths(3)->setTime(17, 0),
                'next_maintenance_date' => now()->addMonths(2),
            ]
        );

        // Operation 4 - In Progress (Station Belli)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-004'],
            [
                'infrastructure_id' => $infraBelli->id,
                'technician_id' => $techTrabelsi->id,
                'team' => 'Brigade Mobile Pompage SONEDE Nabeul',
                'type' => 'Corrective',
                'priority' => 'high',
                'status' => 'in_progress',
                'cost' => 2800.00,
                'duration_hours' => 5.0,
                'description' => 'Remplacement d’urgence du clapet anti-retour DN500 sur le groupe motopompe n°2 suite à des coups de bélier anormaux sur la conduite d’adduction.',
                'scheduled_at' => now()->setTime(8, 0),
                'started_at' => now()->subHours(3),
            ]
        );

        // Operation 5 - Planned & Overdue (Station Belli)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-005'],
            [
                'infrastructure_id' => $infraBelli->id,
                'technician_id' => $techTrabelsi->id,
                'team' => 'Équipe Maintenance Haute Tension SONEDE',
                'type' => 'Préventive',
                'priority' => 'critical',
                'status' => 'planned', // En retard de 2 jours
                'cost' => 1600.00,
                'duration_hours' => 3.5,
                'description' => 'Contrôle thermographique infrarouge des armoires 30 kV et resserrage des connexions des variateurs de vitesse.',
                'scheduled_at' => now()->subDays(2)->setTime(14, 0),
            ]
        );

        // Operation 6 - Planned Soon (Dessalement Zarat)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-006'],
            [
                'infrastructure_id' => $infraZarat->id,
                'technician_id' => $techMansouri->id,
                'team' => 'Service Osmose & Chimie SONEDE Zarat',
                'type' => 'Préventive',
                'priority' => 'medium',
                'status' => 'planned',
                'cost' => 4500.00,
                'duration_hours' => 6.0,
                'description' => 'Cycle de nettoyage chimique (CIP) des modules membranaires d’osmose inverse et contrôle de la salinité du perméat.',
                'scheduled_at' => now()->addDays(28)->setTime(9, 0),
            ]
        );

        // Operation 7 - Planned & Overdue (Réservoir Ras Tabia)
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-TUN-007'],
            [
                'infrastructure_id' => $infraRasTabia->id,
                'technician_id' => $techBenSalem->id,
                'team' => 'Génie Civil Ouvrages d’Art SONEDE',
                'type' => 'Corrective',
                'priority' => 'critical',
                'status' => 'planned', // En retard de 8 jours
                'cost' => 5200.00,
                'duration_hours' => 8.0,
                'description' => 'Injection de résine polyuréthane sous pression pour étanchéifier la paroi latérale ouest de la cuve de stockage n°2.',
                'scheduled_at' => now()->subDays(8)->setTime(8, 0),
            ]
        );

        // 5. CITIZEN MALFUNCTION REPORTS (Signalements Usagers) -> Ready for Admin to Validate & Start!
        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-SIG-001'],
            [
                'infrastructure_id' => $infraRasTabia->id,
                'technician_id' => $techTrabelsi->id,
                'team' => 'Équipe d’Intervention Rapide - SONEDE Tunis Ouest',
                'type' => 'Corrective',
                'priority' => 'critical',
                'status' => 'reported', // Signalement citoyen en attente de validation admin !
                'cost' => 1450.00,
                'duration_hours' => 4.0,
                'description' => '[Signalement Usager - Fuite d\'eau / Débordement] Fuite importante d\'eau constatée le long du talus du réservoir de Ras Tabia avec chute notable de pression dans le quartier Le Bardo et cité Ibn Khaldoun. Signalé par le citoyen : Wissem Ayari (+216 98 123 456). Intervention d’urgence requise.',
                'scheduled_at' => now()->setTime(10, 0),
            ]
        );

        Maintenance::updateOrCreate(
            ['reference_code' => 'MNT-2026-SIG-002'],
            [
                'infrastructure_id' => $infraCanal->id,
                'technician_id' => $techZouari->id,
                'team' => 'Brigade Surveillance Canaux - SONEDE & DGBGTH',
                'type' => 'Corrective',
                'priority' => 'high',
                'status' => 'reported', // Deuxième signalement citoyen en attente !
                'cost' => 2200.00,
                'duration_hours' => 5.5,
                'description' => '[Signalement Usager - Dégradation structurelle] Fissuration et affaissement partiel de la berge en béton du canal Medjerda-Cap Bon près du pont de Mornag, avec accumulation de débris végétaux obstruant l’écoulement. Signalé par : Mounir Dridi (+216 24 555 789).',
                'scheduled_at' => now()->setTime(11, 30),
            ]
        );
    }
}
