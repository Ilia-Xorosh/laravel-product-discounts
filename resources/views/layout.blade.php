<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Скидки на товары</title>
    <style>
        [hidden]{display:none!important}
        body{font:16px system-ui,sans-serif;max-width:1000px;margin:32px auto;padding:0 20px;color:#172033;background:#f7f8fb}
        a{color:#2456ba}nav{display:flex;gap:24px;margin-bottom:32px}table{width:100%;border-collapse:collapse;background:white}
        td,th{padding:12px;text-align:left;border-bottom:1px solid #ddd}label{display:block;margin:16px 0}
        input:not([type=checkbox]),select,textarea{display:block;box-sizing:border-box;width:100%;max-width:620px;padding:8px;margin-top:6px}
        button{padding:8px 16px;cursor:pointer}form.inline{display:inline}fieldset{border:1px solid #ccd1dd}.notice{padding:16px;background:#e2f3e7}.errors{color:#9b1429}
    </style>
</head>
<body>
<nav><a href="{{ route('product-discounts.actions.index') }}">Акции</a><a href="{{ route('product-discounts.doings.index') }}">Действия</a></nav>
@if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
@if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@yield('content')
</body>
</html>
