<?php

declare(strict_types=1);

namespace ProductDiscounts;

use Illuminate\Validation\ValidationException;
use ProductDiscounts\Contracts\ProductCatalog;
use ProductDiscounts\Http\Requests\DoingRequest;
use ProductDiscounts\Models\Action;
use ProductDiscounts\Events\CalculationCompleted;
use Illuminate\Support\Facades\Event;

final class DiscountCalculator
{
    public function __construct(private readonly ProductCatalog $catalog, private readonly int $minTotalPercent = 60)
    {
        if ($minTotalPercent < 0 || $minTotalPercent > 100) {
            throw new \InvalidArgumentException('min_total_percent must be between 0 and 100.');
        }
    }

    /**
     * All money is integer minor units. Catalog supplies authoritative prices and availability.
     * @param list<array{configurationId:string,name?:string,quantity:int}> $items
     * @param iterable<Action> $actions Ordered active actions from ActionResolver.
     */
    public function calculate(array $items, iterable $actions, ?string $promocode = null, ?string $basketGuid = null, ?string $orderFrom = null): array
    {
        validator(['basket_guid' => $basketGuid, 'order_from' => $orderFrom], [
            'basket_guid' => ['nullable', 'string', 'min:1', 'max:255'],
            'order_from' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $record = $basketGuid !== null && $basketGuid !== '' && Event::hasListeners(CalculationCompleted::class);
        $trace = [];
        validator(['items' => $items], [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.configurationId' => ['required', 'string', 'max:255', 'distinct:strict'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ])->validate();
        $catalog = $this->catalog->find(array_column($items, 'configurationId'));
        $lines = [];
        $errors = [];
        foreach ($items as $index => $item) {
            $id = $item['configurationId'];
            $product = $catalog[$id] ?? null;
            if (!$product || $product->id !== $id) {
                $errors["items.$index.configurationId"] = 'Товар не найден.';
                continue;
            }
            if ($item['quantity'] > $product->stock) {
                $errors["items.$index.quantity"] = 'Недостаточно товара на складе.';
            }
            // Bounds keep integer multiplication and percentage calculations below overflow.
            if ($product->price > 1000000000) {
                throw new \UnexpectedValueException('Product price exceeds supported limit (1000000000 minor units).');
            }
            $lines[] = [
                'id' => $id, 'name' => $product->name, 'quantity' => (int) $item['quantity'],
                'price' => $product->price, 'subtotal' => $product->price * (int) $item['quantity'], 'discount' => 0,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $startTotal = array_sum(array_column($lines, 'subtotal'));
        if ($startTotal > 1000000000000) {
            throw ValidationException::withMessages(['items' => 'Превышена максимальная сумма корзины.']);
        }
        $floor = intdiv($startTotal * $this->minTotalPercent + 99, 100);
        $appliedPromocode = null;
        foreach ($actions as $action) {
            $actionBefore = array_sum(array_column($lines, 'subtotal'));
            $steps = [];
            foreach ($action->doings as $doing) {
                $d = validator($doing->toArray(), DoingRequest::rulesForDoing())->validate();
                if ($errors = DoingRequest::semanticErrors($d)) {
                    throw ValidationException::withMessages($errors);
                }
                $condition = (int) $d['condition_id'];
                $targets = [];
                $matches = false;
                foreach ($lines as $index => $line) {
                    $product = $catalog[$line['id']];
                    $equal = match ($condition) {
                        2 => $product->brandId !== null && $product->brandId === (string) $d['product_id'],
                        3 => $line['id'] === (string) $d['product_id'],
                        7 => in_array((string) $d['product_id'], $product->categoryIds, true),
                        default => false,
                    };
                    if (in_array($condition, [2, 3, 7], true)) {
                        if ($d['comparison'] === 'Не равно') {
                            $equal = !$equal;
                        }
                        if ($equal) {
                            $targets[] = $index;
                            $matches = true;
                        }
                    } elseif ($d['applie'] === 'На корзину' || $line['id'] === (string) ($d['product_id'] ?? '')) {
                        $targets[] = $index;
                    }
                }
                if (in_array($condition, [4, 5], true)) {
                    $value = $d['applie'] === 'На корзину'
                        ? array_sum(array_column($lines, $condition === 4 ? 'quantity' : 'subtotal'))
                        : array_sum(array_map(fn ($index) => $lines[$index][$condition === 4 ? 'quantity' : 'subtotal'], $targets));
                    $matches = $targets && ($d['comparison'] === 'Больше или равно' ? $value >= (int) $d['value'] : $value <= (int) $d['value']);
                }
                if ($condition === 8) {
                    $matches = $targets && $promocode !== null && $appliedPromocode === null && hash_equals((string) $d['value'], $promocode);
                }
                if (!$matches) {
                    continue;
                }
                $before = array_sum(array_column($lines, 'subtotal'));
                $beforeLines = $record ? $lines : [];
                if ($d['applie'] === 'На корзину') {
                    $targets = array_keys($lines);
                    $delta = $this->delta($before, (int) $d['size'], $d['type_discount'], $d['type']);
                    $delta = max($floor - $before, $delta);
                    $this->distribute($lines, $delta);
                } else {
                    foreach ($targets as $index) {
                        $base = $lines[$index]['subtotal'];
                        $size = $d['type_discount'] === 'Процент' ? (int) $d['size'] : (int) $d['size'] * $lines[$index]['quantity'];
                        $delta = $this->delta($base, $size, $d['type_discount'], $d['type']);
                        $total = array_sum(array_column($lines, 'subtotal'));
                        $delta = max(-$base, $floor - $total, $delta);
                        $lines[$index]['subtotal'] += $delta;
                    }
                }
                $after = array_sum(array_column($lines, 'subtotal'));
                if ($after > 1000000000000) {
                    throw new \OverflowException('Promotion increased basket beyond supported limit.');
                }
                if ($condition === 8 && $after !== $before) {
                    $appliedPromocode = $promocode;
                }
                if ($record) {
                    $products = [];
                    foreach ($lines as $index => $line) {
                        if ($line['subtotal'] !== $beforeLines[$index]['subtotal']) {
                            $products[] = [
                                'product_id' => $line['id'], 'product_name' => $line['name'],
                                'quantity' => $line['quantity'], 'start_subtotal' => $beforeLines[$index]['subtotal'],
                                'final_subtotal' => $line['subtotal'], 'discount' => $beforeLines[$index]['subtotal'] - $line['subtotal'],
                            ];
                        }
                    }
                    if ($products) {
                        $steps[] = ['snapshot' => $doing->attributesToArray(), 'start_price' => $before,
                            'final_price' => $after, 'discount' => $before - $after, 'products' => $products];
                    }
                }
            }
            if ($record && $steps) {
                $actionAfter = array_sum(array_column($lines, 'subtotal'));
                $trace[] = ['snapshot' => $action->attributesToArray(), 'start_price' => $actionBefore,
                    'final_price' => $actionAfter, 'discount' => $actionBefore - $actionAfter, 'doings' => $steps];
            }
        }
        foreach ($lines as &$line) {
            // Negative discount denotes markup. price is the original catalog unit price.
            $line['discount'] = $line['price'] * $line['quantity'] - $line['subtotal'];
        }
        unset($line);
        $total = array_sum(array_column($lines, 'subtotal'));
        $result = ['totalPrice' => $total, 'discount' => $startTotal - $total, 'items' => $lines, 'appliedPromocode' => $appliedPromocode];
        if ($record) {
            Event::dispatch(new CalculationCompleted($basketGuid, $orderFrom, $startTotal, $result, $trace));
        }
        return $result;
    }

    private function delta(int $base, int $size, string $unit, string $type): int
    {
        $amount = $unit === 'Процент' ? intdiv($base * $size + 50, 100) : $size;
        return match ($type) {
            'Скидка' => -$amount,
            'Наценка' => $amount,
            'Фиксированная цена' => $amount - $base,
        };
    }

    /** Deterministic proportional distribution, preserving the exact basket total. */
    private function distribute(array &$lines, int $delta): void
    {
        $total = array_sum(array_column($lines, 'subtotal'));
        $remaining = $delta;
        $remainingBase = $total;
        foreach ($lines as $index => $line) {
            // Sequential allocation avoids a large base * delta integer product.
            $part = $remainingBase === 0 ? $remaining : (int) round($remaining * ($line['subtotal'] / $remainingBase));
            if ($remaining < 0) {
                $part = max(-$line['subtotal'], $part);
            }
            $lines[$index]['subtotal'] += $part;
            $remaining -= $part;
            $remainingBase -= $line['subtotal'];
        }
    }
}
