<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Infrastructure;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        // 6 zones, chacune avec 2 infrastructures et 3 incidents (relations 1-N).
        Zone::factory()->count(6)->create()->each(function (Zone $zone) {
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
    }
}
