<?php

declare(strict_types=1);

namespace ProductDiscounts\Data;

final readonly class Product
{
    /** Prices are integer minor units (kopecks/cents). @param list<string> $categoryIds */
    public function __construct(
        public string $id,
        public string $name,
        public int $price,
        public int $stock,
        public ?string $brandId = null,
        public array $categoryIds = [],
    ) {
        if ($id === '' || $price < 0 || $stock < 0) {
            throw new \InvalidArgumentException('Invalid product ID, price or stock.');
        }
    }
}
