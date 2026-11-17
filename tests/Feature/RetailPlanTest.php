<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Component;
use App\Models\User;
use App\Services\RetailCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RetailPlanTest extends TestCase
{
    use RefreshDatabase;

    private function selection(string $switch = 'lynx-36'): array
    {
        return ['kit_id' => 'gmmk-pro-black', 'switch_id' => $switch, 'keycap_id' => 'gpbt-black-ash'];
    }

    public function test_real_catalog_has_direct_https_product_links_and_correct_stock_filter(): void
    {
        $products = $this->getJson('/api/v1/retail/products')->assertOk()->assertJsonCount(20, 'data')->json('data');
        foreach ($products as $product) {
            foreach ($product['offers'] as $offer) {
                $this->assertStringStartsWith('https://', $offer['url']);
                $this->assertNotNull($offer['checked_at']);
                $this->assertGreaterThan(0, $offer['price']);
            }
        }
        $this->getJson('/api/v1/retail/products?category=switch&in_stock=1')->assertOk()->assertJsonCount(7, 'data');
        $this->getJson('/api/v1/retail/products?category=other')->assertUnprocessable();
    }

    public function test_switch_packs_round_up_for_the_actual_82_key_ansi_base(): void
    {
        $quote = $this->postJson('/api/v1/retail/quote', $this->selection())->assertOk()
            ->assertJsonPath('data.complete', true)->assertJsonPath('data.compatible', true)->json('data');
        $row = collect($quote['items'])->firstWhere('product.category', 'switch');
        $this->assertSame(82, $row['quantity']);
        $this->assertSame(3, $row['packs']);
        $this->assertSame(26, $row['spare']);
        $this->assertSame($row['offer']['price'] * 3, $row['subtotal']);
        $this->assertSame(array_sum(array_column($quote['items'], 'subtotal')), $quote['total']);
    }

    public function test_offer_chooses_available_seller_and_incomplete_selection_does_not_invent_a_kit(): void
    {
        $quote = $this->postJson('/api/v1/retail/quote', $this->selection('gateron-red'))->assertOk()->json('data');
        $row = collect($quote['items'])->firstWhere('product.category', 'switch');
        $this->assertSame('Vendara', $row['offer']['merchant']);
        $this->postJson('/api/v1/retail/quote', ['kit_id' => null, 'switch_id' => 'lynx-36', 'keycap_id' => null])
            ->assertOk()->assertJsonPath('data.complete', false)->assertJsonPath('data.items.0.packs', 0);
    }

    public function test_tampered_prices_ids_and_categories_are_rejected(): void
    {
        $this->postJson('/api/v1/retail/quote', $this->selection() + ['total' => 1])->assertUnprocessable();
        $selection = $this->selection();
        $selection['kit_id'] = 'unknown';
        $this->postJson('/api/v1/retail/quote', $selection)->assertUnprocessable()->assertJsonValidationErrors('kit_id');
        $selection['kit_id'] = 'gateron-red';
        $this->postJson('/api/v1/retail/quote', $selection)->assertUnprocessable();
    }

    public function test_saved_plan_is_owned_and_recomputed_without_creating_a_store_order(): void
    {
        $owner = User::factory()->create();
        $build = Build::factory()->for($owner)->create();
        $url = '/api/v1/builds/'.$build->id.'/shopping-plan';
        $this->putJson($url, $this->selection())->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->putJson($url, $this->selection())->assertForbidden();
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($owner);
        $this->putJson($url, $this->selection())->assertOk();
        $this->assertSame($this->selection(), $build->fresh()->shopping_plan);
        $this->getJson($url)->assertOk()->assertJsonPath('data.complete', true);
        Build::factory()->for($owner)->create();
        $this->getJson('/api/v1/builds?source=retail')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $build->id);
        $this->assertDatabaseCount('orders', 0);
        $this->withHeader('Idempotency-Key', 'retail-plan-no-sale')->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 1])->assertConflict();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_retail_and_legacy_inventory_cannot_be_mixed_in_either_direction(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $component = Component::factory()->create();
        $build = Build::factory()->for($owner)->create();
        $build->items()->create(['component_id' => $component->id, 'quantity' => 1]);
        $this->putJson('/api/v1/builds/'.$build->id.'/shopping-plan', $this->selection())->assertConflict();
        $clean = Build::factory()->for($owner)->create();
        $this->putJson('/api/v1/builds/'.$clean->id.'/shopping-plan', $this->selection())->assertOk();
        $this->postJson('/api/v1/builds/'.$clean->id.'/items', ['component_id' => $component->id, 'quantity' => 1])->assertConflict();
    }

    public function test_compact_and_full_size_bases_use_their_actual_key_counts_and_hyperx_pack_size(): void
    {
        foreach (['gmmk2-65-black' => [67, 2, 23], 'gmmk2-96-white' => [99, 3, 36]] as $kit => [$count, $packs, $spare]) {
            $quote = $this->postJson('/api/v1/retail/quote', ['kit_id' => $kit, 'switch_id' => 'hyperx-red-45', 'keycap_id' => 'gpbt-black-ash'])
                ->assertOk()->assertJsonPath('data.compatible', true)->assertJsonPath('data.key_count', $count)->json('data');
            $row = collect($quote['items'])->firstWhere('product.category', 'switch');
            $this->assertSame($packs, $row['packs']);
            $this->assertSame($spare, $row['spare']);
            $this->assertSame($packs * 518200, $row['subtotal']);
        }
    }

    public function test_unverified_keycap_coverage_is_never_reported_as_compatible(): void
    {
        $selection = $this->selection();
        $selection['keycap_id'] = 'razer-quartz';
        $this->postJson('/api/v1/retail/quote', $selection)->assertOk()
            ->assertJsonPath('data.complete', true)->assertJsonPath('data.compatible', false)
            ->assertJsonCount(1, 'data.issues');
    }

    public function test_old_stock_snapshot_remains_explicitly_marked_as_stale(): void
    {
        $this->travelTo(now()->addYears(2));
        $quote = app(RetailCatalogService::class)->quote($this->selection());
        $this->assertTrue($quote['items'][0]['product']['stale']);
        $this->assertNotEmpty($quote['warnings']);
        $this->travelBack();
    }
}
