<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'            => 'SD ' . fake()->lastName(),
            'address'         => fake()->address(),
            'pic_name'        => fake()->name(),
            'contact'         => '08' . fake()->numerify('##########'),
            'bank_account'    => fake()->bankAccountNumber(),
            'pipeline_status' => fake()->randomElement(['prospek', 'dalam_proses', 'sudah_mou', 'tidak_lanjut']),
            'is_mou'          => fake()->boolean(40),
        ];
    }

    public function mou(): static
    {
        return $this->state(fn() => ['pipeline_status' => 'sudah_mou', 'is_mou' => true]);
    }
}
