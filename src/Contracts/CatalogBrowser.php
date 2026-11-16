<?php

declare(strict_types=1);

namespace ProductDiscounts\Contracts;

interface CatalogBrowser
{
    /**
     * Only return entities accessible to the current shop/user. Limit to 50 results.
     * parentId: category parent for categories; category filter for products; ignored for brands.
     * @return list<array{id:string,name:string}>
     */
    public function search(string $kind, string $query, ?string $parentId): array;
}
