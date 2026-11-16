@extends('product-discounts::layout')
@section('content')
<h1>Акции для товаров</h1>
<p><a href="{{ route('product-discounts.actions.create') }}">Создать акцию</a></p>
<table><thead><tr><th>Название</th><th>Приоритет / порядок</th><th>Активна</th><th>Управление</th></tr></thead><tbody>
@forelse($actions as $action)
<tr><td>{{ $action->name }}</td><td>{{ $action->priority }} / {{ $action->sort }}</td><td>{{ $action->actively ? 'Да' : 'Нет' }}</td><td>
<a href="{{ route('product-discounts.actions.edit', $action) }}">Изменить</a>
<form class="inline" method="post" action="{{ route('product-discounts.actions.destroy', $action) }}">@csrf @method('DELETE')<button>Удалить</button></form>
</td></tr>
@empty<tr><td colspan="4">Акций пока нет. Сначала создайте действие, затем добавьте его в акцию.</td></tr>@endforelse
</tbody></table>
{{ $actions->links() }}
@endsection
