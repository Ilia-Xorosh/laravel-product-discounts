<?php

namespace ProductDiscounts\Http\Controllers;

use Illuminate\Routing\Controller;
use ProductDiscounts\Http\Requests\DoingRequest;
use ProductDiscounts\Models\Doing;

class DoingController extends Controller
{
    public function index()
    {
        return view('product-discounts::doings.index', ['doings' => Doing::orderBy('id')->paginate(20)]);
    }

    public function create()
    {
        return view('product-discounts::doings.form', ['doing' => new Doing()]);
    }

    public function edit(Doing $doing)
    {
        return view('product-discounts::doings.form', compact('doing'));
    }

    public function store(DoingRequest $request)
    {
        Doing::create($request->validated());
        return redirect()->route('product-discounts.doings.index')->with('status', 'Действие сохранено.');
    }

    public function update(DoingRequest $request, Doing $doing)
    {
        $doing->update(array_replace(['product_id' => null, 'value' => null], $request->validated()));
        return redirect()->route('product-discounts.doings.index')->with('status', 'Действие сохранено.');
    }

    public function destroy(Doing $doing)
    {
        $doing->delete();
        return redirect()->route('product-discounts.doings.index')->with('status', 'Действие удалено.');
    }
}
