<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PageRequest;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\UserResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function users(PageRequest $request)
    {
        return UserResource::collection(User::orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function role(RoleRequest $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            // Shared lock order serializes simultaneous administrator demotions.
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->role === 'admin' && $request->validated('role') === 'buyer' && $admins->count() <= 1) {
                throw new BusinessException('Нельзя понизить последнего администратора');
            }
            $locked->role = $request->validated('role');
            $locked->save();
        });

        return new UserResource($user->refresh());
    }

    public function statistics()
    {
        return response()->json(['data' => ['orders_by_status' => Order::selectRaw('status, COUNT(*) as count, SUM(total) as total')->groupBy('status')->get(), 'issued_revenue' => (int) Order::where('status', 'issued')->sum('total')]]);
    }
}
