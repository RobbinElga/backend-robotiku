<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_code'      => 'ROBO-' . strtoupper(Str::random(6)),
            'name'              => fake()->name(),
            'birth_date'        => fake()->dateTimeBetween('-12 years', '-6 years')->format('Y-m-d'),
            'gender'            => fake()->randomElement(['L', 'P']),
            'shirt_size'        => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'school_origin'     => 'SD ' . fake()->lastName(),
            'school_grade'      => fake()->numberBetween(1, 6) . fake()->randomElement(['A', 'B', 'C', 'D']),
            'address'           => fake()->address(),
            'allergy_notes'     => fake()->optional()->sentence(),
            'photo_permission'  => fake()->boolean(80),
            'status'            => 'aktif',
            'registration_type' => 'mandiri',
        ];
    }
}
