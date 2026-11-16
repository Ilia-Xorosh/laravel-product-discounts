<?php

use ProductDiscounts\Catalog\EloquentProductCatalog;
use ProductDiscounts\Catalog\EloquentCatalogBrowser;

return [
    'min_total_percent' => 60,
    'catalog' => EloquentProductCatalog::class,
    // Set your product model and its database column names before using the catalog.
    'product_model' => null,
    'columns' => [
        'id' => 'id', 'name' => 'name', 'price' => 'price',
        'stock' => 'quantity', 'brand' => 'brand_id',
    ],
    // Optional Eloquent relation, each category must expose getKey().
    'categories_relation' => null,
    'browser' => EloquentCatalogBrowser::class,
    'browser_models' => [
        'brands' => ['model' => null, 'id' => 'id', 'name' => 'name'],
        // Root categories have NULL parent_id. Supply a custom browser for other schemas.
        'categories' => ['model' => null, 'id' => 'id', 'name' => 'name', 'parent' => 'parent_id'],
    ],
    // Explicitly enable routes after configuring authentication/authorization.
    'api' => ['enabled' => false, 'prefix' => 'api/product-discounts', 'middleware' => ['api', 'auth:sanctum']],
    'admin' => ['enabled' => false, 'prefix' => 'admin/product-discounts', 'middleware' => ['web', 'auth', 'can:manage-product-discounts']],
];
