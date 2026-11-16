<?php

namespace ProductDiscounts\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use ProductDiscounts\Contracts\CatalogBrowser;

class CatalogController extends Controller
{
    public function __invoke(Request $request, CatalogBrowser $browser)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['products', 'brands', 'categories'])],
            'q' => ['nullable', 'string', 'max:100'],
            'parent' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $items = $browser->search($data['kind'], $data['q'] ?? '', $data['parent'] ?? null);
        } catch (\LogicException $e) {
            report($e);
            return response()->json(['message' => 'Поиск не настроен. Настройте источник каталога в конфигурации пакета.'], 503);
        }
        // Do not expose arbitrary model attributes from a custom adapter.
        $items = array_map(fn (array $item) => ['id' => (string) $item['id'], 'name' => (string) $item['name']], array_slice($items, 0, 50));
        return response()->json(['items' => array_values($items)]);
    }
}
