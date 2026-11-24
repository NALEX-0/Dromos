<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ColoredRouteController;
use App\Http\Controllers\ModulusWebhookController;
use App\Http\Controllers\RoutePlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/shared/routes/{shareToken}', [RoutePlanController::class, 'shared'])->name('route-plans.shared');
if (config('services.sms.enabled')) {
    Route::post('/webhooks/modulus/delivery-reports/{token}', [ModulusWebhookController::class, 'deliveryReport'])
        ->name('webhooks.modulus.dlr');
    Route::post('/shared/routes/{shareToken}/stops/{stop}/notifications', [RoutePlanController::class, 'notifySharedStop'])
        ->middleware('throttle:6,1')
        ->name('route-plans.shared.stops.notifications.store');
}

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [RoutePlanController::class, 'createOrdered'])->name('route-plans.create');
    Route::get('/optimized-routes/create', [RoutePlanController::class, 'create'])->name('optimized-route-plans.create');
    Route::get('/routes', [RoutePlanController::class, 'index'])->name('route-plans.index');
    Route::post('/routes', [RoutePlanController::class, 'store'])->name('route-plans.store');
    Route::get('/ordered-routes/create', [RoutePlanController::class, 'createOrdered'])->name('ordered-route-plans.create');
    Route::post('/ordered-routes', [RoutePlanController::class, 'storeOrdered'])->name('ordered-route-plans.store');
    Route::get('/routes/{routePlan}', [RoutePlanController::class, 'show'])->name('route-plans.show');
    Route::patch('/routes/{routePlan}', [RoutePlanController::class, 'update'])->name('route-plans.update');
    Route::delete('/routes/{routePlan}', [RoutePlanController::class, 'destroy'])->name('route-plans.destroy');
    Route::post('/routes/{routePlan}/stops', [RoutePlanController::class, 'storeStop'])->name('route-plans.stops.store');
    Route::patch('/routes/{routePlan}/order', [RoutePlanController::class, 'reorder'])->name('route-plans.reorder');
    Route::patch('/routes/{routePlan}/options', [RoutePlanController::class, 'updateOptions'])->name('route-plans.options.update');
    Route::patch('/routes/{routePlan}/stops/{stop}', [RoutePlanController::class, 'updateStop'])->name('route-plans.stops.update');
    if (config('services.sms.enabled')) {
        Route::post('/routes/{routePlan}/stops/{stop}/notifications', [RoutePlanController::class, 'notifyStop'])->name('route-plans.stops.notifications.store');
    }
    Route::delete('/routes/{routePlan}/stops/{stop}', [RoutePlanController::class, 'destroyStop'])->name('route-plans.stops.destroy');

    Route::middleware('admin')->group(function () {
        Route::get('/colored-routes', [ColoredRouteController::class, 'index'])->name('colored-routes.index');
        Route::post('/colored-routes', [ColoredRouteController::class, 'store'])->name('colored-routes.store');
        Route::patch('/colored-routes/{coloredRoute}', [ColoredRouteController::class, 'update'])->name('colored-routes.update');
        Route::patch('/colored-routes/{coloredRoute}/toggle', [ColoredRouteController::class, 'toggle'])->name('colored-routes.toggle');
        Route::delete('/colored-routes/{coloredRoute}', [ColoredRouteController::class, 'destroy'])->name('colored-routes.destroy');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/routes', [AdminController::class, 'routes'])->name('routes');
        if (config('services.sms.enabled')) {
            Route::get('/sms', [AdminController::class, 'sms'])->name('sms');
        }
        Route::get('/api-usage', [AdminController::class, 'apiUsage'])->name('api-usage');
    });
});
