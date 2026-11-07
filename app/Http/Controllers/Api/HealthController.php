<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            return response()->json(['message' => 'База данных недоступна', 'errors' => (object) []], 503);
        }

        return response()->json(['data' => ['name' => config('app.name'), 'time' => now()->toIso8601String(), 'database' => 'ok']]);
    }
}
