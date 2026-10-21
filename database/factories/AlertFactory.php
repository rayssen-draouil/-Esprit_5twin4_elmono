<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'incident_id' => null,
            'type' => fake()->randomElement(['Risque élevé', 'Fuite', 'Contamination', "Coupure d'eau", 'Capteur critique']),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'message' => fake()->sentence(10),
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(fn () => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
