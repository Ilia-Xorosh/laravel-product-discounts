<?php

declare(strict_types=1);

namespace ProductDiscounts\Catalog;

use Illuminate\Database\Eloquent\Model;
use ProductDiscounts\Contracts\CatalogBrowser;

final class EloquentCatalogBrowser implements CatalogBrowser
{
    public function search(string $kind, string $query, ?string $parentId): array
    {
        $settings = config('product-discounts.browser_models.'.$kind);
        $model = $kind === 'products' ? config('product-discounts.product_model') : ($settings['model'] ?? null);
        if (!is_string($model) || !is_subclass_of($model, Model::class)) {
            throw new \LogicException('Configure the catalog browser model or supply a custom CatalogBrowser.');
        }
        $id = $kind === 'products' ? config('product-discounts.columns.id') : $settings['id'];
        $name = $kind === 'products' ? config('product-discounts.columns.name') : $settings['name'];
        $builder = $model::query();
        if ($query !== '') {
            $pattern = $kind === 'brands' && mb_strlen($query) === 1 ? $query.'%' : '%'.$query.'%';
            $builder->where(function ($builder) use ($query, $id, $name, $pattern) {
                $builder->where($name, 'like', $pattern)->orWhere($id, $query);
            });
        }
        if ($kind === 'categories') {
            $parentColumn = $settings['parent'];
            $parentId === null ? $builder->whereNull($parentColumn) : $builder->where($parentColumn, $parentId);
        }
        if ($kind === 'products' && $parentId !== null) {
            $relation = config('product-discounts.categories_relation');
            if (!$relation) {
                throw new \LogicException('Configure categories_relation to filter products by category.');
            }
            $builder->whereHas($relation, fn ($categories) => $categories->whereKey($parentId));
        }
        return $builder->orderBy($name)->orderBy($id)->limit(50)->get()->map(fn (Model $row) => [
            'id' => (string) $row->getAttribute($id), 'name' => (string) $row->getAttribute($name),
        ])->all();
    }
}
