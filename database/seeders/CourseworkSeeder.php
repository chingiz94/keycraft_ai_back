<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Build;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use App\Services\CompatibilityService;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

/** Opt-in local coursework fixtures. Real checkout rules create all five orders. */
class CourseworkSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_BUYER_PASSWORD');
        if (! is_string($password) || strlen($password) < 8) {
            throw new \RuntimeException('Задайте DEMO_BUYER_PASSWORD длиной от 8 символов для учебного наполнения.');
        }
        $this->call(DatabaseSeeder::class);
        $checkout = app(OrderService::class);
        $compatibility = app(CompatibilityService::class);

        for ($i = 1; $i <= 5; $i++) {
            $email = ($i === 1 ? 'buyer' : 'buyer'.$i).'@keycraft.test';
            $user = User::firstOrCreate(['email' => $email], ['name' => 'Демо покупатель '.$i, 'password' => $password]);
            if ($user->role !== 'buyer') {
                throw new \RuntimeException('Учебный email уже принадлежит пользователю с другой ролью: '.$email);
            }
            $format = $i % 2 === 1 ? '65' : '75';
            $keys = $format === '65' ? 67 : 82;
            $build = Build::firstOrCreate(['user_id' => $user->id, 'name' => "Демо сборка {$format}%"], ['budget' => 10000000]);
            $items = Component::query()->where('name', 'like', 'KeyCraft '.$format.' %')->get()
                ->mapWithKeys(fn (Component $part) => [$part->id => ['quantity' => $part->type === 'switch' ? $keys : 1]])->all();
            $build->components()->syncWithoutDetaching($items);

            $key = 'coursework-demo-order-'.$i;
            if (Order::query()->where('user_id', $user->id)->where('idempotency_key', $key)->exists()) {
                continue;
            }
            $analysis = $compatibility->analyze($build->load('items.component')->items);
            $order = $checkout->create($user, ['build_id' => $build->id, 'expected_total' => $analysis['total']], $key);
            if ($i >= 2 && $i <= 4) {
                $order = $checkout->updateStatus($order, OrderStatus::Confirmed);
            }
            if ($i >= 3 && $i <= 4) {
                $order = $checkout->updateStatus($order, OrderStatus::Assembled);
            }
            if ($i === 4) {
                $checkout->updateStatus($order, OrderStatus::Issued);
            }
            if ($i === 5) {
                $checkout->updateStatus($order, OrderStatus::Cancelled);
            }
        }
    }
}
