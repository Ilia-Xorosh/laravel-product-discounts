<?php

declare(strict_types=1);

namespace ProductDiscounts\Catalog;

use Illuminate\Database\Eloquent\Model;
use ProductDiscounts\Contracts\ProductCatalog;
use ProductDiscounts\Data\Product;

final class EloquentProductCatalog implements ProductCatalog
{
    public function find(array $ids): array
    {
        $model = config('product-discounts.product_model');
        if (!is_string($model) || !is_subclass_of($model, Model::class)) {
            throw new \LogicException('Configure product-discounts.product_model or provide your own ProductCatalog.');
        }
        $columns = config('product-discounts.columns');
        $relation = config('product-discounts.categories_relation');
        $query = $model::query()->whereIn($columns['id'], $ids);
        if ($relation) {
            $query->with($relation);
        }
        $products = [];
        foreach ($query->get() as $row) {
            // Stored price must already be in integer minor units. No float conversion.
            $price = (string) $row->getAttribute($columns['price']);
            $stock = (string) $row->getAttribute($columns['stock']);
            if (!ctype_digit($price) || !ctype_digit($stock)) {
                throw new \UnexpectedValueException('Catalog price and stock must be nonnegative integers. Use a custom catalog for decimal prices.');
            }
            $id = (string) $row->getAttribute($columns['id']);
            $brand = $row->getAttribute($columns['brand']);
            $products[$id] = new Product(
                $id, (string) $row->getAttribute($columns['name']), (int) $price, (int) $stock,
                $brand === null ? null : (string) $brand,
                $relation ? $row->getRelation($relation)->map(fn (Model $category) => (string) $category->getKey())->all() : [],
            );
        }
        return $products;
    }
}
