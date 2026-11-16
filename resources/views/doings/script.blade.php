<script>
(() => {
    'use strict';
    const form = document.getElementById('discount-doing-form');
    const field = name => form.elements.namedItem(name);
    const el = id => document.getElementById(id);
    const condition = field('condition_id');
    const target = field('product_id');
    const value = field('value');
    const comparison = field('comparison');
    const query = el('catalog-query');
    let kind = null;
    let path = [];
    let searchRequest;
    let categoryRequest;
    let timer;
    const categoryId = () => path.length ? path[path.length - 1].id : null;

    function button(label, onClick) {
        const element = document.createElement('button');
        element.type = 'button';
        element.textContent = label;
        element.addEventListener('click', onClick);
        return element;
    }

    async function load(requestKind, term, parent, signal) {
        const url = new URL(form.dataset.catalogUrl, window.location.href);
        url.searchParams.set('kind', requestKind);
        url.searchParams.set('q', term);
        if (parent !== null) url.searchParams.set('parent', parent);
        const response = await fetch(url, {signal, credentials: 'same-origin', headers: {Accept: 'application/json'}});
        if (!response.ok) {
            if ([401, 403].includes(response.status)) throw new Error('Нет доступа к каталогу. Проверьте авторизацию.');
            if (response.status === 503) throw new Error('Источник поиска не настроен. Можно указать ID вручную.');
            throw new Error('Не удалось загрузить каталог. Повторите поиск.');
        }
        const data = await response.json();
        if (!Array.isArray(data.items)) throw new Error('Некорректный ответ каталога.');
        return data.items;
    }

    function choose(item) {
        target.value = item.id;
        el('catalog-selection').textContent = 'Выбрано: ' + item.name + ' (ID: ' + item.id + ')';
    }

    async function search() {
        clearTimeout(timer);
        searchRequest?.abort();
        if (!kind) return;
        const controller = new AbortController();
        searchRequest = controller;
        el('catalog-results').replaceChildren();
        el('catalog-status').textContent = 'Загрузка…';
        try {
            const items = await load(kind, query.value.trim(), kind === 'brands' ? null : categoryId(), controller.signal);
            if (controller.signal.aborted) return;
            el('catalog-status').textContent = items.length ? 'Выберите запись:' : 'Ничего не найдено.';
            items.forEach(item => el('catalog-results').append(button(item.name + ' — ' + item.id, () => choose(item))));
        } catch (error) {
            if (!controller.signal.aborted) el('catalog-status').textContent = error.message;
        }
    }

    function renderPath() {
        el('catalog-path').replaceChildren();
        path.forEach((item, index) => {
            el('catalog-path').append(button(item.name, () => {
                path = path.slice(0, index + 1);
                refreshCategory();
            }));
        });
        el('catalog-choose-category').hidden = kind !== 'categories' || !path.length;
    }

    async function sections() {
        categoryRequest?.abort();
        const controller = new AbortController();
        categoryRequest = controller;
        el('catalog-sections').replaceChildren();
        el('catalog-category-status').textContent = 'Загрузка разделов…';
        try {
            const items = await load('categories', '', categoryId(), controller.signal);
            if (controller.signal.aborted) return;
            el('catalog-category-status').textContent = items.length ? 'Откройте подраздел:' : 'Подразделов нет.';
            items.forEach(item => el('catalog-sections').append(button(item.name, () => {
                path.push(item);
                refreshCategory();
            })));
        } catch (error) {
            if (!controller.signal.aborted) el('catalog-category-status').textContent = error.message;
        }
    }

    function refreshCategory() {
        renderPath();
        sections();
        search();
    }

    function update(resetValue = false) {
        const id = Number(condition.value);
        const nextKind = id === 2 ? 'brands' : id === 7 ? 'categories' : (id === 3 || field('applie').value === 'На товар') ? 'products' : null;
        if (nextKind !== kind) {
            searchRequest?.abort();
            categoryRequest?.abort();
            clearTimeout(timer);
            // Keep saved ID during initialization; clear incompatible entity on an actual change.
            if (initialized) {
                target.value = '';
                el('catalog-selection').textContent = '';
            }
            kind = nextKind;
            path = [];
            query.value = '';
            el('catalog-results').replaceChildren();
            el('catalog-status').textContent = '';
            if (kind === 'brands') search();
            else if (kind) refreshCategory();
        }
        el('catalog-picker').hidden = !kind;
        target.disabled = !kind;
        target.required = !!kind;
        el('catalog-categories').hidden = !kind || kind === 'brands';
        el('catalog-alphabet').hidden = kind !== 'brands';
        const title = {products: 'Товар', brands: 'Бренд', categories: 'Категория'}[kind] || '';
        el('catalog-title').textContent = title;
        el('catalog-id-label').textContent = 'ID: ' + title;
        const numeric = [4, 5].includes(id);
        const needsValue = numeric || id === 8;
        const savedComparison = comparison.value;
        comparison.replaceChildren();
        (numeric ? ['Больше или равно', 'Меньше или равно'] : id === 8 ? ['Равно'] : ['Равно', 'Не равно']).forEach(text => {
            const option = document.createElement('option');
            option.value = option.textContent = text;
            comparison.append(option);
        });
        if ([...comparison.options].some(option => option.value === savedComparison)) comparison.value = savedComparison;
        el('condition-value-label').hidden = !needsValue;
        value.disabled = !needsValue;
        value.required = needsValue;
        if (resetValue) value.value = '';
        value.type = numeric ? 'number' : 'text';
        if (numeric) { value.min = '0'; value.max = '1000000000000'; value.step = '1'; }
        else { value.removeAttribute('min'); value.removeAttribute('max'); value.removeAttribute('step'); }
        el('condition-value-label').querySelector('span').textContent = id === 4 ? 'Количество' : id === 5 ? 'Сумма в копейках/центах' : 'Промокод';
        field('size').max = field('type_discount').value === 'Процент' ? '100' : '1000000000';
    }

    let initialized = false;
    condition.addEventListener('change', () => update(true));
    field('applie').addEventListener('change', () => update());
    field('type_discount').addEventListener('change', () => update());
    target.addEventListener('input', () => { el('catalog-selection').textContent = ''; });
    el('catalog-search').addEventListener('click', search);
    query.addEventListener('input', () => {
        searchRequest?.abort();
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });
    query.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); search(); }
    });
    el('catalog-root').addEventListener('click', () => { path = []; refreshCategory(); });
    el('catalog-choose-category').addEventListener('click', () => { if (path.length) choose(path[path.length - 1]); });
    for (const letter of 'ABCDEFGHIJKLMNOPQRSTUVWXYZАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ') {
        el('catalog-alphabet').append(button(letter, () => { query.value = letter; search(); }));
    }
    el('catalog-alphabet').append(button('Все', () => { query.value = ''; search(); }));
    update();
    initialized = true;
})();
</script>
