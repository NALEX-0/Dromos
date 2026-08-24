<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoutePlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [RoutePlanController::class, 'create'])->name('route-plans.create');
    Route::post('/routes', [RoutePlanController::class, 'store'])->name('route-plans.store');
    Route::get('/ordered-routes/create', [RoutePlanController::class, 'createOrdered'])->name('ordered-route-plans.create');
    Route::post('/ordered-routes', [RoutePlanController::class, 'storeOrdered'])->name('ordered-route-plans.store');
    Route::get('/routes/{routePlan}', [RoutePlanController::class, 'show'])->name('route-plans.show');
    Route::post('/routes/{routePlan}/stops', [RoutePlanController::class, 'storeStop'])->name('route-plans.stops.store');
    Route::patch('/routes/{routePlan}/order', [RoutePlanController::class, 'reorder'])->name('route-plans.reorder');
    Route::patch('/routes/{routePlan}/stops/{stop}', [RoutePlanController::class, 'updateStop'])->name('route-plans.stops.update');
    Route::delete('/routes/{routePlan}/stops/{stop}', [RoutePlanController::class, 'destroyStop'])->name('route-plans.stops.destroy');
});
