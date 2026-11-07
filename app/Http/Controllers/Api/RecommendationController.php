<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendationRequest;
use App\Services\RecommendationService;

class RecommendationController extends Controller
{
    public function __invoke(RecommendationRequest $request, RecommendationService $recommendations)
    {
        return response()->json(['data' => $recommendations->recommend($request->validated())]);
    }
}
