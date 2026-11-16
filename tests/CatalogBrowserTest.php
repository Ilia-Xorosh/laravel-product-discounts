<?php

namespace ProductDiscounts\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ProductDiscounts\Contracts\CatalogBrowser;

class CatalogBrowserTest extends TestCase
{
    public function test_custom_adapter_receives_filters_and_response_is_limited(): void
    {
        $this->app->bind(CatalogBrowser::class, fn () => new class implements CatalogBrowser {
            public function search(string $kind, string $query, ?string $parentId): array
            {
                if ([$kind, $query, $parentId] !== ['products', 'needle', 'cat-1']) {
                    throw new \RuntimeException('Wrong filters');
                }
                return array_fill(0, 60, ['id' => 'sku', 'name' => '<script>alert(1)</script>', 'secret' => 'hidden']);
            }
        });
        $response = $this->getJson('/admin/product-discounts/catalog?kind=products&q=needle&parent=cat-1')
            ->assertOk()->assertJsonCount(50, 'items')->assertJsonPath('items.0.id', 'sku');
        $this->assertArrayNotHasKey('secret', $response->json('items.0'));
    }

    public function test_invalid_search_kind_and_unconfigured_source(): void
    {
        $this->getJson('/admin/product-discounts/catalog?kind=unknown')->assertUnprocessable();
        $this->getJson('/admin/product-discounts/catalog?kind=products')->assertStatus(503);
    }

    public function test_eloquent_category_navigation_and_product_filtering(): void
    {
        Schema::create('browser_categories', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->unsignedBigInteger('parent_id')->nullable();
        });
        Schema::create('browser_products', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->unsignedBigInteger('category_id');
        });
        BrowserCategory::insert([
            ['id' => 1, 'name' => 'Root', 'parent_id' => null], ['id' => 2, 'name' => 'Child', 'parent_id' => 1],
        ]);
        BrowserProduct::insert([['id' => 1, 'name' => 'Needle', 'category_id' => 2], ['id' => 2, 'name' => 'Other', 'category_id' => 1]]);
        config(['product-discounts.product_model' => BrowserProduct::class,
            'product-discounts.categories_relation' => 'categories',
            'product-discounts.browser_models.categories.model' => BrowserCategory::class,
            'product-discounts.browser_models.brands.model' => BrowserCategory::class]);
        $this->getJson('/admin/product-discounts/catalog?kind=categories')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', '1');
        $this->getJson('/admin/product-discounts/catalog?kind=categories&parent=1')->assertOk()->assertJsonPath('items.0.id', '2');
        $this->getJson('/admin/product-discounts/catalog?kind=products&parent=2&q=Need')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Needle');
        $this->getJson('/admin/product-discounts/catalog?kind=brands&q=Child')->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_catalog_route_has_same_access_control_as_admin(): void
    {
        $route = app('router')->getRoutes()->getByName('product-discounts.catalog');
        $this->assertSame(['web'], $route->middleware());
        // The test environment replaces production middleware. Verify production defaults separately.
        $defaults = require __DIR__.'/../config/product-discounts.php';
        $this->assertContains('can:manage-product-discounts', $defaults['admin']['middleware']);
    }
}

class BrowserCategory extends Model
{
    protected $table = 'browser_categories';
    public $timestamps = false;
}

class BrowserProduct extends Model
{
    protected $table = 'browser_products';
    public $timestamps = false;
    public function categories() { return $this->belongsTo(BrowserCategory::class, 'category_id'); }
}
