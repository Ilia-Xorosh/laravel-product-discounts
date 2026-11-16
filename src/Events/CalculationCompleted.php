<?php

declare(strict_types=1);

namespace ProductDiscounts\Events;

final readonly class CalculationCompleted
{
    /** Trace contains snapshots and actual per-step changes in minor units. */
    public function __construct(
        public string $basketGuid,
        public ?string $orderFrom,
        public int $startTotal,
        public array $result,
        public array $actions,
    ) {}
}
