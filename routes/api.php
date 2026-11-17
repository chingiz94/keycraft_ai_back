<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RetailCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');
    Route::get('retail/products', [RetailCatalogController::class, 'index'])->name('retail.index');
    Route::post('retail/quote', [RetailCatalogController::class, 'quote'])->name('retail.quote');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('auth.login');
    Route::apiResource('components', ComponentController::class)->only(['index', 'show']);
    Route::get('components/{component}/offers', [OfferController::class, 'forComponent'])->name('components.offers');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::middleware('role:buyer')->group(function () {
            Route::apiResource('builds', BuildController::class);
            Route::get('builds/{build}/shopping-plan', [RetailCatalogController::class, 'showPlan'])->name('builds.shopping-plan.show');
            Route::post('retail/recommendations', [RetailCatalogController::class, 'recommend'])->middleware('throttle:ai')->name('retail.recommendations');
            Route::put('builds/{build}/shopping-plan', [RetailCatalogController::class, 'savePlan'])->name('builds.shopping-plan.save');
            Route::post('builds/{build}/items', [BuildController::class, 'addItem'])->name('builds.items.store');
            Route::patch('builds/{build}/items/{item}', [BuildController::class, 'updateItem'])->name('builds.items.update');
            Route::delete('builds/{build}/items/{item}', [BuildController::class, 'deleteItem'])->name('builds.items.destroy');
            Route::post('builds/{build}/validate', [BuildController::class, 'validateBuild'])->name('builds.validate');
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
            Route::post('ai/recommendations', RecommendationController::class)->middleware('throttle:ai')->name('ai.recommendations');
        });
        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
            Route::apiResource('components', ComponentController::class);
            Route::apiResource('offers', OfferController::class);
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::patch('orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
            Route::get('users', [AdminController::class, 'users'])->name('users.index');
            Route::patch('users/{user}/role', [AdminController::class, 'role'])->name('users.role');
            Route::get('statistics', [AdminController::class, 'statistics'])->name('statistics');
        });
    });
});
