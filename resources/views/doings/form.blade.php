@extends('product-discounts::layout')
@section('content')
<h1>{{ $doing->exists ? 'Изменить действие' : 'Новое действие' }}</h1>
<form id="discount-doing-form" data-catalog-url="{{ route('product-discounts.catalog') }}" method="post" action="{{ $doing->exists ? route('product-discounts.doings.update', $doing) : route('product-discounts.doings.store') }}">
@csrf @if($doing->exists) @method('PUT') @endif
<label>Название<input name="name" required maxlength="255" value="{{ old('name', $doing->name) }}"></label>
@foreach(['type' => ['Тип', ['Скидка', 'Наценка', 'Фиксированная цена']], 'applie' => ['Применить', ['На товар', 'На корзину']], 'comparison' => ['Сравнение', ['Равно', 'Не равно', 'Больше или равно', 'Меньше или равно']], 'type_discount' => ['Единица изменения', ['Процент', 'Сумма']]] as $field => [$label, $options])
<label>{{ $label }}<select name="{{ $field }}">@foreach($options as $option)<option value="{{ $option }}" @selected(old($field, $doing->$field) === $option)>{{ $option }}</option>@endforeach</select></label>
@endforeach
<label>Условие<select name="condition_id">@foreach(\ProductDiscounts\Models\Doing::CONDITIONS as $id => $name)<option value="{{ $id }}" @selected((string)old('condition_id', $doing->condition_id) === (string)$id)>{{ $name }}</option>@endforeach</select></label>
<fieldset id="catalog-picker">
<legend id="catalog-title">Выбор из каталога</legend>
<label><span id="catalog-id-label">ID товара / бренда / категории</span><input name="product_id" maxlength="255" value="{{ old('product_id', $doing->product_id) }}"></label>
<p id="catalog-selection" aria-live="polite"></p>
<div id="catalog-categories">
    <p id="catalog-path"></p>
    <button type="button" id="catalog-root">В корень</button>
    <button type="button" id="catalog-choose-category" hidden>Выбрать этот раздел</button>
    <div id="catalog-sections"></div>
    <p id="catalog-category-status" role="status"></p>
</div>
<div id="catalog-alphabet" aria-label="Поиск бренда по букве" hidden></div>
<label>Поиск по названию или ID<input type="search" id="catalog-query" maxlength="100" autocomplete="off"></label>
<button type="button" id="catalog-search">Найти</button>
<p id="catalog-status" role="status"></p>
<div id="catalog-results"></div>
<p>Показано до 50 результатов. Уточните поиск, если нужной записи нет. ID также можно ввести вручную.</p>
</fieldset>
<label id="condition-value-label"><span>Значение условия</span><input name="value" maxlength="255" value="{{ old('value', $doing->value) }}"></label>
<label>Размер изменения<input type="number" min="0" max="1000000000" name="size" required value="{{ old('size', $doing->size) }}"></label>
<p>Денежные значения — в копейках/центах, процент — целое число от 0 до 100. Фиксированная цена в процентах задаёт долю текущей цены.</p>
<button>Сохранить</button>
</form>
@include('product-discounts::doings.script')
@endsection
