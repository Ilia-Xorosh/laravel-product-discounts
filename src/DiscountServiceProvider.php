<?php

namespace ProductDiscounts;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ProductDiscounts\Contracts\ProductCatalog;
use ProductDiscounts\Contracts\CatalogBrowser;
use ProductDiscounts\Http\Controllers\CatalogController;
use ProductDiscounts\Http\Controllers\ActionController;
use ProductDiscounts\Http\Controllers\DiscountController;
use ProductDiscounts\Http\Controllers\DoingController;

class DiscountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/product-discounts.php', 'product-discounts');
        $this->app->bind(ProductCatalog::class, fn ($app) => $app->make(config('product-discounts.catalog')));
        $this->app->bind(CatalogBrowser::class, fn ($app) => $app->make(config('product-discounts.browser')));
        $this->app->bind(DiscountCalculator::class, fn ($app) => new DiscountCalculator(
            $app->make(ProductCatalog::class), (int) config('product-discounts.min_total_percent', 60),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'product-discounts');
        $this->publishes([__DIR__.'/../config/product-discounts.php' => config_path('product-discounts.php')], 'product-discounts-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'product-discounts-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/product-discounts')], 'product-discounts-views');
        if ($this->app->routesAreCached()) {
            return;
        }
        if (config('product-discounts.api.enabled')) {
            Route::middleware(config('product-discounts.api.middleware'))->prefix(config('product-discounts.api.prefix'))
                ->post('calculate', DiscountController::class)->name('product-discounts.calculate');
        }
        if (config('product-discounts.admin.enabled')) {
            Route::middleware(config('product-discounts.admin.middleware'))->prefix(config('product-discounts.admin.prefix'))
                ->name('product-discounts.')->group(function () {
                    Route::get('catalog', CatalogController::class)->name('catalog');
                    Route::resource('actions', ActionController::class)->except('show');
                    Route::resource('doings', DoingController::class)->except('show');
                });
        }
    }
}
