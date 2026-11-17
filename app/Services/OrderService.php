<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Build;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(private readonly CompatibilityService $compatibility) {}

    public function create(User $user, array $input, string $key): Order
    {
        abort_unless($user->role === 'buyer', 403, 'Оформление заказа доступно покупателю.');
        $requestHash = hash('sha256', json_encode([
            'build_id' => (int) $input['build_id'],
            'expected_total' => (int) $input['expected_total'],
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $input, $key, $requestHash): Order {
            // Serialize this buyer's checkout attempts before checking the unique
            // user/key pair, including attempts against different builds.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Order::query()->where('user_id', $user->id)
                ->where('idempotency_key', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new BusinessException('Ключ повтора уже использован с другим телом запроса.', 409, [
                        'idempotency_key' => ['Используйте новый ключ для изменённого запроса.'],
                    ]);
                }

                return $existing->load('items.component');
            }

            $build = Build::query()->whereKey($input['build_id'])->lockForUpdate()->firstOrFail();
            abort_unless($build->user_id === $user->id, 403, 'Нет доступа к этой сборке.');
            if ($build->shopping_plan !== null) {
                throw new BusinessException('Покупка выбранных товаров оформляется на сайтах магазинов из списка покупок.', 409);
            }
            $items = $build->items()->orderBy('component_id')->lockForUpdate()->get();
            $components = Component::query()->whereIn('id', $items->pluck('component_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $item->setRelation('component', $components->get($item->component_id));
            }

            $analysis = $this->compatibility->analyze($items);
            if (! $analysis['compatible'] || ! $analysis['complete']) {
                throw new BusinessException('Сборка не прошла проверку совместимости.', 422, [
                    'build' => [...$analysis['issues'], ...$analysis['unverified']],
                ]);
            }
            if ($analysis['total'] !== (int) $input['expected_total']) {
                throw new BusinessException('Стоимость изменилась. Подтвердите актуальную сумму.', 409, [
                    'expected_total' => ['Текущая сумма: '.$analysis['total'].'.'],
                    'current_total' => [$analysis['total']],
                ]);
            }
            foreach ($items as $item) {
                if ($item->component->stock < $item->quantity) {
                    throw new BusinessException('Недостаточно товара на складе.', 409, [
                        'stock' => ["Компонент {$item->component_id}: доступно {$item->component->stock}, требуется {$item->quantity}."],
                    ]);
                }
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'build_id' => $build->id,
                'status' => OrderStatus::New->value,
                'total' => $analysis['total'],
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
            ]);
            foreach ($items as $item) {
                $order->items()->create([
                    'component_id' => $item->component_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->component->price,
                ]);
                $item->component->decrement('stock', $item->quantity);
            }

            DB::afterCommit(fn () => Log::channel('api')->info('order.created', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'build_id' => $build->id,
                'total' => $order->total,
            ]));

            return $order->load('items.component');
        }, 3);
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        return DB::transaction(function () use ($order, $status): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status instanceof OrderStatus
                ? $locked->status : OrderStatus::from($locked->status);
            if (! $current->canTransitionTo($status)) {
                throw new BusinessException('Недопустимый переход статуса заказа.', 409, [
                    'status' => ["Переход {$current->value} → {$status->value} запрещён."],
                ]);
            }

            if ($status === OrderStatus::Cancelled) {
                $items = $locked->items()->orderBy('component_id')->get();
                $components = Component::query()->whereIn('id', $items->pluck('component_id'))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($items as $item) {
                    $component = $components->get($item->component_id);
                    if (! $component) {
                        throw new BusinessException('Невозможно вернуть остаток: компонент отсутствует.');
                    }
                    $component->increment('stock', $item->quantity);
                }
            }

            $locked->update(['status' => $status->value]);

            DB::afterCommit(fn () => Log::channel('api')->info('order.status_changed', [
                'order_id' => $locked->id,
                'from' => $current->value,
                'to' => $status->value,
                'stock_restored' => $status === OrderStatus::Cancelled,
            ]));

            return $locked->load('items.component');
        }, 3);
    }
}
