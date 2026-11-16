<?php

namespace ProductDiscounts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ProductDiscounts\ActionResolver;
use ProductDiscounts\DiscountCalculator;

class DiscountController extends Controller
{
    public function __invoke(Request $request, ActionResolver $resolver, DiscountCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.configurationId' => ['required', 'string', 'max:255', 'distinct:strict'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'promocode' => ['nullable', 'string', 'max:255'],
            'basket_guid' => ['nullable', 'string', 'max:255'],
            'order_from' => ['nullable', 'string', 'max:100'],
        ]);
        return response()->json($calculator->calculate($data['items'], $resolver->resolve(), $data['promocode'] ?? null,
            $data['basket_guid'] ?? null, $data['order_from'] ?? null));
    }
}
