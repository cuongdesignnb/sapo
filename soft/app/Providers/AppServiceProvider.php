<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\WarehouseProduct;
use App\Observers\WarehouseProductObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        WarehouseProduct::observe(WarehouseProductObserver::class);
    }
}