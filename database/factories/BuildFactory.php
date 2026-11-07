<?php

namespace Database\Factories;

use App\Models\Build;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Build> */
class BuildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Тестовая сборка '.fake()->unique()->uuid(),
            'budget' => 10000000,
        ];
    }
}
