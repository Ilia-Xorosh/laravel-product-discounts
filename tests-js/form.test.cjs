const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {JSDOM} = require('jsdom');
const script = fs.readFileSync('resources/views/doings/script.blade.php', 'utf8').replace(/^<script>\s*/, '').replace(/<\/script>\s*$/, '');
const tick = () => new Promise(resolve => setImmediate(resolve));

function setup(fetcher) {
    const dom = new JSDOM(`<form id="discount-doing-form" data-catalog-url="/custom/catalog">
        <select name="condition_id">${[2,3,4,5,7,8].map(id => `<option>${id}</option>`).join('')}</select>
        <select name="applie"><option>На товар</option><option>На корзину</option></select>
        <select name="comparison"><option>Равно</option></select>
        <select name="type_discount"><option>Процент</option><option>Сумма</option></select>
        <input name="size"><input name="product_id" value="saved-id">
        <label id="condition-value-label"><span></span><input name="value" value="saved-value"></label>
        ${['catalog-picker','catalog-title','catalog-id-label','catalog-selection','catalog-categories','catalog-path','catalog-root','catalog-choose-category','catalog-sections','catalog-category-status','catalog-alphabet','catalog-search','catalog-status','catalog-results'].map(id => `<div id="${id}"></div>`).join('')}
        <input id="catalog-query"></form>`, {url:'https://example.test/custom/form', runScripts:'outside-only'});
    dom.window.fetch = fetcher || (async () => ({ok:true,json:async()=>({items:[]})}));
    dom.window.eval(script);
    const document = dom.window.document;
    const field = name => document.querySelector(`[name="${name}"]`);
    function change(name, value) {field(name).value = value; field(name).dispatchEvent(new dom.window.Event('change'));}
    return {dom, document, field, change};
}

test('dynamic conditions preserve initial ID, clear incompatible selections and constrain comparisons', async () => {
    const {dom,document,field,change} = setup();
    assert.equal(field('product_id').value, 'saved-id');
    change('condition_id','4');
    assert.equal(field('product_id').value, '');
    assert.equal(field('value').type,'number');
    assert.equal(field('value').value,'');
    assert.deepEqual([...field('comparison').options].map(o=>o.value),['Больше или равно','Меньше или равно']);
    change('applie','На корзину');
    assert.equal(field('product_id').disabled,true);
    change('condition_id','8');
    assert.equal(field('value').type,'text');
    assert.equal(field('comparison').value,'Равно');
    change('condition_id','7');
    assert.equal(field('value').disabled,true);
    assert.equal(document.getElementById('catalog-categories').hidden,false);
    await tick(); dom.window.close();
});

test('search escapes remote labels, chooses IDs and uses configured route', async () => {
    const calls = [];
    const {dom,document,field} = setup(async url => {
        calls.push(new URL(url));
        return {ok:true,json:async()=>({items:[{id:'brand-9',name:'<img src=x onerror=alert(1)>'}]})};
    });
    await tick();
    const result = document.querySelector('#catalog-results button');
    assert.equal(result.querySelector('img'),null);
    result.click();
    assert.equal(field('product_id').value,'brand-9');
    assert.equal(calls[0].pathname,'/custom/catalog');
    assert.equal(calls[0].searchParams.get('kind'),'brands');
    dom.window.close();
});

test('category navigation filters products and root resets the filter', async () => {
    const calls = [];
    const {dom,document,change} = setup(async url => {
        const parsed = new URL(url); calls.push(parsed);
        return {ok:true,json:async()=>({items: parsed.searchParams.get('kind') === 'categories' && !parsed.searchParams.has('parent') ? [{id:'cat',name:'Section'}] : []})};
    });
    change('condition_id','3'); await tick();
    document.querySelector('#catalog-sections button').click(); await tick();
    assert.ok(calls.some(url=>url.searchParams.get('kind')==='products' && url.searchParams.get('parent')==='cat'));
    document.getElementById('catalog-root').click(); await tick();
    assert.equal(calls.at(-1).searchParams.has('parent'),false);
    dom.window.close();
});

test('late search response cannot replace newer results and failures are visible', async () => {
    let resolveFirst;
    let count=0;
    const {dom,document} = setup(async () => {
        if (++count === 1) return new Promise(resolve => {resolveFirst=resolve;});
        return {ok:false,status:503};
    });
    document.getElementById('catalog-search').click(); await tick();
    resolveFirst({ok:true,json:async()=>({items:[{id:'stale',name:'Stale'}]})}); await tick();
    assert.equal(document.querySelectorAll('#catalog-results button').length,0);
    assert.match(document.getElementById('catalog-status').textContent,/не настроен/);
    dom.window.close();
});
