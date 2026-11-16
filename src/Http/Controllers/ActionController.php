<?php

namespace ProductDiscounts\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use ProductDiscounts\Models\Action;
use ProductDiscounts\Models\Doing;

class ActionController extends Controller
{
    public function index()
    {
        return view('product-discounts::actions.index', ['actions' => Action::orderByDesc('priority')->orderBy('sort')->paginate(20)]);
    }

    public function create()
    {
        return view('product-discounts::actions.form', ['action' => new Action(['priority' => 0, 'sort' => 0]), 'doings' => Doing::orderBy('id')->get()]);
    }

    public function edit(Action $action)
    {
        $action->load('doings');
        return view('product-discounts::actions.form', ['action' => $action, 'doings' => Doing::orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Action());
    }

    public function update(Request $request, Action $action)
    {
        return $this->save($request, $action);
    }

    private function save(Request $request, Action $action)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'content' => ['nullable', 'string', 'max:10000'],
            'actively' => ['sometimes', 'boolean'], 'start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000000'], 'sort' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stop_sort' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'stop_priority' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'doings' => ['sometimes', 'array'], 'doings.*' => ['integer', 'distinct', Rule::exists('product_discount_doings', 'id')],
        ]);
        DB::transaction(function () use ($request, $action, $data) {
            $ids = $data['doings'] ?? [];
            unset($data['doings']);
            $data['actively'] = $request->boolean('actively');
            $action->fill($data)->save();
            $action->doings()->sync($ids);
        });
        return redirect()->route('product-discounts.actions.index')->with('status', 'Акция сохранена.');
    }

    public function destroy(Action $action)
    {
        $action->delete();
        return redirect()->route('product-discounts.actions.index')->with('status', 'Акция удалена.');
    }
}
