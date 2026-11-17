<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['name' => 'KeyCraft AI Backend', 'api' => '/api/v1', 'health' => '/api/v1/health', 'openapi' => '/openapi.json', 'documentation' => '/docs']);
});
Route::get('/openapi.json', fn () => response()->file(base_path('docs/openapi.json'), ['Content-Type' => 'application/json']));
Route::get('/docs', fn () => response()->file(public_path('api-docs.html'), ['Content-Type' => 'text/html; charset=UTF-8']));
