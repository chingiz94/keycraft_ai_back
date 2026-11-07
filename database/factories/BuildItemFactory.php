<?php

namespace Database\Factories;

use App\Models\Build;
use App\Models\BuildItem;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BuildItem> */
class BuildItemFactory extends Factory
{
    public function definition(): array
    {
        return ['build_id' => Build::factory(), 'component_id' => Component::factory(), 'quantity' => 1];
    }
}
