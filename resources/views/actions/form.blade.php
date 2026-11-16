@extends('product-discounts::layout')
@section('content')
<h1>{{ $action->exists ? 'Изменить акцию' : 'Новая акция' }}</h1>
<form method="post" action="{{ $action->exists ? route('product-discounts.actions.update', $action) : route('product-discounts.actions.store') }}">
@csrf @if($action->exists) @method('PUT') @endif
<label>Название<input name="name" required maxlength="255" value="{{ old('name', $action->name) }}"></label>
<label>Описание<textarea name="content">{{ old('content', $action->content) }}</textarea></label>
<input type="hidden" name="actively" value="0">
<label><input type="checkbox" name="actively" value="1" @checked(old('actively', $action->actively))> Активна</label>
<label>Начало<input type="datetime-local" name="start" required value="{{ old('start', $action->start?->format('Y-m-d\TH:i')) }}"></label>
<label>Окончание<input type="datetime-local" name="end" required value="{{ old('end', $action->end?->format('Y-m-d\TH:i')) }}"></label>
@foreach(['priority' => 'Приоритет (больший первым)', 'sort' => 'Порядок (меньший первым)', 'stop_sort' => 'Отключить следующие акции того же приоритета с порядком до', 'stop_priority' => 'Отключить следующие акции с приоритетом до'] as $field => $label)
<label>{{ $label }}<input type="number" name="{{ $field }}" min="0" max="1000000" value="{{ old($field, $action->$field) }}" @required(in_array($field, ['priority', 'sort']))></label>
@endforeach
<fieldset><legend>Действия (применяются по ID)</legend>
@foreach($doings as $doing)
<label><input type="checkbox" name="doings[]" value="{{ $doing->id }}" @checked(in_array($doing->id, session()->hasOldInput() ? old('doings', []) : ($action->exists ? $action->doings->modelKeys() : [])))> #{{ $doing->id }} {{ $doing->name }}</label>
@endforeach
</fieldset><p><button>Сохранить</button></p>
</form>
@endsection
