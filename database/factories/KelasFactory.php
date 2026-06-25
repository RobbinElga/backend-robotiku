<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KelasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => 'Robotiku ' . fake()->randomElement(['Basic', 'Intermediate', 'Advanced']),
            'schedule' => fake()->randomElement(['Sabtu 09:00', 'Sabtu 13:00', 'Minggu 09:00']),
            'capacity' => fake()->numberBetween(10, 20),
        ];
    }
}
