<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RetailRecommendationRequest;
use App\Http\Requests\ShoppingPlanRequest;
use App\Models\Build;
use App\Services\RetailCatalogService;
use App\Services\RetailRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RetailCatalogController extends Controller
{
    public function __construct(private readonly RetailCatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $input = $request->validate(['category' => 'sometimes|in:kit,switch,keycap', 'search' => 'sometimes|string|max:100', 'in_stock' => 'sometimes|boolean']);
        $catalog = $this->catalog->catalog();
        $products = collect($catalog['products'])
            ->when($input['category'] ?? null, fn ($items, $category) => $items->where('category', $category))
            ->when($input['search'] ?? null, fn ($items, $search) => $items->filter(fn ($product) => str_contains(mb_strtolower($product['name'].' '.$product['brand'].' '.$product['description']), mb_strtolower($search))))
            ->when($request->boolean('in_stock'), fn ($items) => $items->filter(fn ($product) => $product['best_offer']['availability'] === 'in_stock'));

        return response()->json(['data' => $products->values(), 'meta' => ['checked_at' => $catalog['checked_at'], 'notice' => $catalog['notice']]]);
    }

    public function quote(ShoppingPlanRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->catalog->quote($request->validated())]);
    }

    public function recommend(RetailRecommendationRequest $request, RetailRecommendationService $recommendations): JsonResponse
    {
        return response()->json(['data' => $recommendations->recommend($request->validated())]);
    }

    public function showPlan(Build $build): JsonResponse
    {
        Gate::authorize('view', $build);

        return response()->json(['data' => $this->catalog->quote($build->shopping_plan ?? [])]);
    }

    public function savePlan(ShoppingPlanRequest $request, Build $build): JsonResponse
    {
        Gate::authorize('update', $build);
        $selection = $request->validated();
        $quote = $this->catalog->quote($selection);
        DB::transaction(function () use ($build, $selection): void {
            $locked = Build::query()->whereKey($build->id)->lockForUpdate()->firstOrFail();
            if ($locked->items()->exists()) {
                throw new BusinessException('Для реального списка покупок создайте отдельную сборку без учебных складских позиций.', 409);
            }
            $locked->forceFill(['shopping_plan' => $selection])->save();
        });

        return response()->json(['data' => $quote]);
    }
}
