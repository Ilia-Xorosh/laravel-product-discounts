<?php

namespace ProductDiscounts\Tests;

use ProductDiscounts\DiscountServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [DiscountServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('product-discounts.api.enabled', true);
        $app['config']->set('product-discounts.api.middleware', ['api']);
        $app['config']->set('product-discounts.admin.enabled', true);
        $app['config']->set('product-discounts.admin.middleware', ['web']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
