<?php

namespace App\Providers;

use App\Contracts\Geocoder;
use App\Contracts\RouteOptimizer;
use App\Contracts\SmsGateway;
use App\Services\CachedGeocoder;
use App\Services\Demo\DemoGeocoder;
use App\Services\Demo\DemoRouteOptimizer;
use App\Services\Google\GoogleGeocoder;
use App\Services\Google\GoogleRouteOptimizer;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\ModulusSmsGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $google = config('services.routing_provider') === 'google';
        $this->app->bind(Geocoder::class, function ($app) use ($google) {
            $provider = $google ? GoogleGeocoder::class : DemoGeocoder::class;

            return new CachedGeocoder($app->make($provider));
        });
        $this->app->bind(RouteOptimizer::class, $google ? GoogleRouteOptimizer::class : DemoRouteOptimizer::class);
        $this->app->bind(
            SmsGateway::class,
            config('services.sms.driver') === 'modulus' ? ModulusSmsGateway::class : FakeSmsGateway::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
