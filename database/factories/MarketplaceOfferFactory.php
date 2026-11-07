<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\MarketplaceOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketplaceOffer> */
class MarketplaceOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'marketplace' => 'Example Marketplace',
            'url' => 'https://example.com/components/'.fake()->uuid(),
            'price' => 1800000,
            'price_updated_at' => now(),
        ];
    }
}
