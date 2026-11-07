<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\CourseworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_coursework_data_can_be_seeded_twice_without_duplicate_orders_or_inventory_changes(): void
    {
        $oldBuyer = getenv('DEMO_BUYER_PASSWORD');
        $oldAdmin = getenv('DEMO_ADMIN_PASSWORD');
        putenv('DEMO_BUYER_PASSWORD=local-test-buyer-123');
        putenv('DEMO_ADMIN_PASSWORD=local-test-admin-123');
        $_ENV['DEMO_BUYER_PASSWORD'] = 'local-test-buyer-123';
        $_ENV['DEMO_ADMIN_PASSWORD'] = 'local-test-admin-123';
        try {
            $this->seed(CourseworkSeeder::class);
            $this->assertSame(6, User::count());
            $this->assertSame(12, Component::count());
            $this->assertSame(5, Order::count());
            $this->assertSame(6, Build::count());
            $stock = Component::orderBy('id')->pluck('stock', 'id')->all();
            $this->seed(CourseworkSeeder::class);
            $this->assertSame(5, Order::count());
            $this->assertSame($stock, Component::orderBy('id')->pluck('stock', 'id')->all());
        } finally {
            unset($_ENV['DEMO_BUYER_PASSWORD'],$_ENV['DEMO_ADMIN_PASSWORD']);
            $oldBuyer === false ? putenv('DEMO_BUYER_PASSWORD') : putenv('DEMO_BUYER_PASSWORD='.$oldBuyer);
            $oldAdmin === false ? putenv('DEMO_ADMIN_PASSWORD') : putenv('DEMO_ADMIN_PASSWORD='.$oldAdmin);
        }
    }
}
