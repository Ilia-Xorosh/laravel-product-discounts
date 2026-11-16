<?php

declare(strict_types=1);

namespace ProductDiscounts\Contracts;

use ProductDiscounts\Data\Product;

interface ProductCatalog
{
    /** @param list<string> $ids @return array<string|int, Product> Indexed by product ID. */
    public function find(array $ids): array;
}
