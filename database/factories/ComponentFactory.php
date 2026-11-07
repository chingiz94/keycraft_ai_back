<?php

namespace Database\Factories;

use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Component> */
class ComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Test Gasket Case '.fake()->unique()->uuid(),
            'type' => 'case',
            'price' => 1800000,
            'stock' => 20,
            'format' => '65',
            'specs' => ['mount' => 'gasket'],
        ];
    }
}
