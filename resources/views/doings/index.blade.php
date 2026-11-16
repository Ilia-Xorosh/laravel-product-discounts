@extends('product-discounts::layout')
@section('content')
<h1>Действия</h1>
<p><a href="{{ route('product-discounts.doings.create') }}">Создать действие</a></p>
<table><thead><tr><th>ID / название</th><th>Условие</th><th>Изменение цены</th><th>Управление</th></tr></thead><tbody>
@forelse($doings as $doing)
<tr><td>#{{ $doing->id }} {{ $doing->name }}</td><td>{{ \ProductDiscounts\Models\Doing::CONDITIONS[$doing->condition_id] ?? 'Неизвестно' }}</td><td>{{ $doing->type }}: {{ $doing->size }} {{ $doing->type_discount }}</td><td>
<a href="{{ route('product-discounts.doings.edit', $doing) }}">Изменить</a>
<form class="inline" method="post" action="{{ route('product-discounts.doings.destroy', $doing) }}">@csrf @method('DELETE')<button>Удалить</button></form>
</td></tr>
@empty<tr><td colspan="4">Действий пока нет.</td></tr>@endforelse
</tbody></table>{{ $doings->links() }}
@endsection
