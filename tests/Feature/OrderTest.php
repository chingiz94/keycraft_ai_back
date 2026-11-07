<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Component;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $build = Build::create(['user_id' => $buyer->id, 'name' => 'My keyboard', 'budget' => 50000]);
        $definitions = [
            'case' => [10000, '65', ['mount' => 'gasket'], 1],
            'pcb' => [7000, '65', ['mount' => 'gasket', 'switch_type' => 'mx', 'layout' => 'ansi', 'key_count' => 68, 'stabilizers' => ['2u' => 3, '6.25u' => 1]], 1],
            'plate' => [4000, '65', ['mount' => 'gasket', 'switch_type' => 'mx'], 1],
            'stabilizer' => [2000, null, ['stabilizers' => ['2u' => 4, '6.25u' => 1]], 1],
            'switch' => [100, null, ['switch_type' => 'mx', 'stem' => 'mx'], 68],
            'keycap' => [5000, null, ['stem' => 'mx', 'layout' => 'ansi', 'key_count' => 104], 1],
        ];
        $components = [];
        foreach ($definitions as $type => [$price, $format, $specs, $quantity]) {
            $component = Component::create(compact('type', 'price', 'format', 'specs') + ['name' => $type, 'stock' => 1000]);
            $build->items()->create(['component_id' => $component->id, 'quantity' => $quantity]);
            $components[$type] = $component;
        }

        return [$buyer, $build, $components];
    }

    public function test_checkout_snapshots_server_prices_and_replay_does_not_decrement_stock_twice(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $payload = ['build_id' => $build->id, 'expected_total' => 34800];
        $response = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => 'checkout-1'])
            ->assertCreated()->assertJsonPath('data.total', 34800)->assertJsonPath('data.status', 'new');
        $id = $response->json('data.id');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 6);
        $this->assertSame(932, $components['switch']->fresh()->stock);
        $this->assertSame(999, $components['case']->fresh()->stock);

        // A retry must remain a replay even after the original build is removed.
        $components['switch']->update(['price' => 200]);
        $build->delete();
        $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => 'checkout-1'])
            ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.total', 34800);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(932, $components['switch']->fresh()->stock);
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'component_id' => $components['switch']->id, 'unit_price' => 100]);
    }

    public function test_reusing_idempotency_key_with_changed_payload_is_a_conflict(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'same-key'])->assertCreated();
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34801], ['Idempotency-Key' => 'same-key'])
            ->assertConflict()->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(932, $components['switch']->fresh()->stock);
    }

    public function test_stale_expected_total_returns_current_total_without_mutating_inventory(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $components['case']->update(['price' => 11000]);
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'stale-price'])
            ->assertConflict()->assertJsonPath('errors.current_total.0', 35800);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1000, $components['case']->fresh()->stock);
        $this->assertSame(1000, $components['switch']->fresh()->stock);
    }

    public function test_insufficient_stock_does_not_partially_create_order(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $components['switch']->update(['stock' => 67]);
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'no-stock'])->assertConflict();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1000, $components['case']->fresh()->stock);
        $this->assertSame(67, $components['switch']->fresh()->stock);
    }

    public function test_missing_and_unverified_parts_are_not_orderable(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $components['plate']->update(['specs' => ['switch_type' => 'mx']]);
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'unknown-spec'])
            ->assertUnprocessable()->assertJsonStructure(['errors' => ['build']]);
        $build->items()->where('component_id', $components['case']->id)->delete();
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 24800], ['Idempotency-Key' => 'incomplete'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1000, $components['case']->fresh()->stock);
    }

    public function test_another_buyers_build_cannot_be_ordered_or_order_read(): void
    {
        [$buyer, $build] = $this->fixture();
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'owner-order'])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs(User::factory()->create(['role' => 'buyer']));
        $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'foreign-build'])->assertForbidden();
        $this->getJson('/api/v1/orders/'.$id)->assertForbidden();
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cancellation_returns_stock_once_and_cannot_be_repeated(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'cancel-order'])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->patchJson('/api/v1/admin/orders/'.$id.'/status', ['status' => 'confirmed'])->assertOk();
        $this->patchJson('/api/v1/admin/orders/'.$id.'/status', ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(1000, $components['switch']->fresh()->stock);
        $this->patchJson('/api/v1/admin/orders/'.$id.'/status', ['status' => 'cancelled'])->assertConflict();
        $this->assertSame(1000, $components['switch']->fresh()->stock);
        $this->assertSame(1000, $components['case']->fresh()->stock);
    }

    public function test_statuses_cannot_skip_steps_or_cancel_an_assembled_order(): void
    {
        [$buyer, $build] = $this->fixture();
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'status-order'])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $url = '/api/v1/admin/orders/'.$id.'/status';
        $this->patchJson($url, ['status' => 'issued'])->assertConflict();
        $this->patchJson($url, ['status' => 'confirmed'])->assertOk();
        $this->patchJson($url, ['status' => 'assembled'])->assertOk();
        $this->patchJson($url, ['status' => 'cancelled'])->assertConflict();
        $this->patchJson($url, ['status' => 'issued'])->assertOk()->assertJsonPath('data.status', 'issued');
        $this->patchJson($url, ['status' => 'confirmed'])->assertConflict();
    }

    public function test_a_write_failure_rolls_back_order_items_and_stock_already_decremented(): void
    {
        [$buyer, $build, $components] = $this->fixture();
        Sanctum::actingAs($buyer);
        $dispatcher = OrderItem::getEventDispatcher();
        OrderItem::setEventDispatcher(clone $dispatcher);
        $count = 0;
        OrderItem::creating(function () use (&$count): void {
            if (++$count === 2) {
                throw new RuntimeException('Injected second-line write failure');
            }
        });
        try {
            $this->postJson('/api/v1/orders', ['build_id' => $build->id, 'expected_total' => 34800], ['Idempotency-Key' => 'rollback-order'])->assertStatus(500);
        } finally {
            OrderItem::setEventDispatcher($dispatcher);
        }
        $this->assertSame(2, $count);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        foreach ($components as $component) {
            $this->assertSame(1000, $component->fresh()->stock);
        }
    }
}
