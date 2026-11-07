<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\PageRequest;
use App\Http\Requests\StatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(PageRequest $request)
    {
        return OrderResource::collection(Order::with('items.component')->when($request->user()->role !== 'admin', fn ($q) => $q->where('user_id', $request->user()->id))->latest('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load('items.component'));
    }

    public function store(OrderRequest $request)
    {
        $order = $this->orders->create($request->user(), $request->safe()->only(['build_id', 'expected_total']), $request->validated('idempotency_key'));

        return (new OrderResource($order->load('items.component')))->response()->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }

    public function status(StatusRequest $request, Order $order)
    {
        return new OrderResource($this->orders->updateStatus($order, OrderStatus::from($request->validated('status')))->load('items.component'));
    }
}
