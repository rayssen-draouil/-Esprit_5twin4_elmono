<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Positions de démonstration (Tunisie) — utilisées pour géolocaliser
     * les zones créées par le factory et, par sécurité, toute zone
     * existante encore dépourvue de coordonnées (aucune donnée écrasée).
     *
     * @var array<int, array{latitude: float, longitude: float, radius_m: int}>
     */
    private const COORDINATES = [
        ['latitude' => 36.8065, 'longitude' => 10.1815, 'radius_m' => 3000], // Tunis
        ['latitude' => 34.7406, 'longitude' => 10.7603, 'radius_m' => 4000], // Sfax
        ['latitude' => 35.8256, 'longitude' => 10.6084, 'radius_m' => 3500], // Sousse
        ['latitude' => 36.4561, 'longitude' => 10.4362, 'radius_m' => 2500], // Hammamet
        ['latitude' => 33.8815, 'longitude' => 10.0982, 'radius_m' => 3500], // Gabès
        ['latitude' => 36.5000, 'longitude' => 10.8000, 'radius_m' => 3000], // Nabeul
        ['latitude' => 36.7210, 'longitude' => 10.1770, 'radius_m' => 2000], // Ariana
        ['latitude' => 35.6781, 'longitude' => 10.8262, 'radius_m' => 2800], // Mahdia
    ];

    public function run(): void
    {
        // 6 zones, chacune avec 2 infrastructures et 3 incidents (relations 1-N).
        Zone::factory()->count(6)->create()->each(function (Zone $zone, int $index) {
            // Géolocalisation de démonstration (additif : colonnes NULLABLE).
            $zone->update(self::COORDINATES[$index % count(self::COORDINATES)]);

            $infras = collect(range(1, 2))->map(fn ($i) => Infrastructure::create([
                'zone_id' => $zone->id,
                'name' => "Infrastructure {$zone->id}-{$i}",
                'type' => fake()->randomElement(['Station de pompage', 'Barrage', 'Capteurs qualité']),
                'status' => 'operational',
            ]));

            Incident::factory()->count(3)->create([
                'zone_id' => $zone->id,
                'infrastructure_id' => $infras->random()->id,
            ]);
        });

        // Zones déjà présentes en base : position manquante uniquement (jamais d'écrasement).
        Zone::query()
            ->whereNull('latitude')
            ->whereNull('longitude')
            ->orderBy('id')
            ->get()
            ->each(function (Zone $zone, int $index) {
                $zone->update(self::COORDINATES[$index % count(self::COORDINATES)]);
            });
    }
}
