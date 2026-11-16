<?php

namespace ProductDiscounts\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ProductDiscounts\ActionResolver;
use ProductDiscounts\Contracts\ProductCatalog;
use ProductDiscounts\Data\Product;
use ProductDiscounts\DiscountCalculator;
use ProductDiscounts\Models\Action;
use ProductDiscounts\Models\Doing;

class DiscountTest extends TestCase
{
    private function catalog(): ProductCatalog
    {
        return new class implements ProductCatalog {
            public function find(array $ids): array
            {
                return [
                    'one' => new Product('one', 'First', 10000, 100, 'brand-a', ['cat-a']),
                    'two' => new Product('two', 'Second', 20000, 100, 'brand-b', ['cat-b']),
                    'free' => new Product('free', 'Free', 0, 10),
                ];
            }
        };
    }

    private function action(array $overrides = []): Action
    {
        return (new Action())->setRelation('doings', collect([new Doing(array_replace([
            'name' => 'Test', 'condition_id' => 3, 'product_id' => 'one', 'type' => 'Скидка',
            'applie' => 'На товар', 'comparison' => 'Равно', 'type_discount' => 'Процент', 'size' => 10,
        ], $overrides))]));
    }

    private function items(): array
    {
        return [['configurationId' => 'one', 'quantity' => 2], ['configurationId' => 'two', 'quantity' => 1]];
    }

    public function test_product_discount_only_changes_target_and_totals_match(): void
    {
        $result = (new DiscountCalculator($this->catalog()))->calculate($this->items(), [$this->action()]);
        $this->assertSame(38000, $result['totalPrice']);
        $this->assertSame(18000, $result['items'][0]['subtotal']);
        $this->assertSame(20000, $result['items'][1]['subtotal']);
        $this->assertSame($result['discount'], array_sum(array_column($result['items'], 'discount')));
    }

    public function test_floor_caps_both_line_and_basket_discounts(): void
    {
        $calculator = new DiscountCalculator($this->catalog());
        $result = $calculator->calculate($this->items(), [$this->action(['size' => 100]), $this->action(['applie' => 'На корзину', 'size' => 100])]);
        $this->assertSame(24000, $result['totalPrice']);
        $this->assertSame(16000, $result['discount']);
        $this->assertSame(24000, array_sum(array_column($result['items'], 'subtotal')));
        $this->assertSame(4000, $result['items'][0]['subtotal']);
    }

    public function test_promocode_is_applied_once_without_type_error(): void
    {
        $action = $this->action(['condition_id' => 8, 'value' => 'SALE', 'applie' => 'На корзину']);
        $result = (new DiscountCalculator($this->catalog()))->calculate($this->items(), [$action, $action], 'SALE');
        $this->assertSame(36000, $result['totalPrice']);
        $this->assertSame('SALE', $result['appliedPromocode']);
    }

    public static function adjustments(): array
    {
        return [
            'brand' => [['condition_id' => 2, 'product_id' => 'brand-a'], 38000],
            'category' => [['condition_id' => 7, 'product_id' => 'cat-b'], 38000],
            'category not equal' => [['condition_id' => 7, 'product_id' => 'missing', 'comparison' => 'Не равно'], 36000],
            'quantity' => [['condition_id' => 4, 'value' => '3', 'comparison' => 'Больше или равно', 'applie' => 'На корзину'], 36000],
            'sum' => [['condition_id' => 5, 'value' => '20000', 'comparison' => 'Больше или равно'], 38000],
            'markup per unit' => [['type' => 'Наценка', 'type_discount' => 'Сумма', 'size' => 500], 41000],
            'fixed per unit' => [['type' => 'Фиксированная цена', 'type_discount' => 'Сумма', 'size' => 8000], 36000],
            'basket markup' => [['type' => 'Наценка', 'applie' => 'На корзину'], 44000],
            'basket fixed' => [['type' => 'Фиксированная цена', 'applie' => 'На корзину', 'type_discount' => 'Сумма', 'size' => 30000], 30000],
        ];
    }

    #[DataProvider('adjustments')]
    public function test_conditions_and_price_adjustments(array $rule, int $expected): void
    {
        $result = (new DiscountCalculator($this->catalog()))->calculate($this->items(), [$this->action($rule)]);
        $this->assertSame($expected, $result['totalPrice']);
        $this->assertSame($expected, array_sum(array_column($result['items'], 'subtotal')));
        $this->assertSame(40000 - $expected, array_sum(array_column($result['items'], 'discount')));
    }

    public function test_calculator_does_not_keep_state_between_requests(): void
    {
        $calculator = new DiscountCalculator($this->catalog());
        $calculator->calculate($this->items(), [$this->action()]);
        $this->assertSame(40000, $calculator->calculate($this->items(), [])['totalPrice']);
    }

    public function test_free_product_and_basket_markup_do_not_divide_by_zero(): void
    {
        $result = (new DiscountCalculator($this->catalog()))->calculate([['configurationId' => 'free', 'quantity' => 1]], [
            $this->action(['condition_id' => 8, 'value' => 'ADD', 'applie' => 'На корзину', 'type' => 'Наценка', 'type_discount' => 'Сумма', 'size' => 100]),
        ], 'ADD');
        $this->assertSame(100, $result['totalPrice']);
        $this->assertSame(100, $result['items'][0]['subtotal']);
    }

    public static function invalidItems(): array
    {
        return [
            'missing' => [[['configurationId' => 'missing', 'quantity' => 1]]],
            'stock' => [[['configurationId' => 'one', 'quantity' => 101]]],
            'negative' => [[['configurationId' => 'one', 'quantity' => -1]]],
            'duplicate' => [[['configurationId' => 'one', 'quantity' => 1], ['configurationId' => 'one', 'quantity' => 1]]],
        ];
    }

    #[DataProvider('invalidItems')]
    public function test_invalid_items_are_rejected(array $items): void
    {
        $this->expectException(ValidationException::class);
        (new DiscountCalculator($this->catalog()))->calculate($items, []);
    }

    public function test_type_condition_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new DiscountCalculator($this->catalog()))->calculate($this->items(), [$this->action(['condition_id' => 6])]);
    }

    public function test_resolver_skips_disabled_and_expired_actions_and_honors_stops(): void
    {
        $base = ['name' => 'Action', 'actively' => true, 'start' => now()->subDay(), 'end' => now()->addDay(), 'priority' => 10, 'sort' => 1];
        $first = Action::create($base + ['stop_sort' => 5, 'stop_priority' => 1]);
        Action::create(array_replace($base, ['sort' => 2]));
        $third = Action::create(array_replace($base, ['sort' => 6]));
        Action::create(array_replace($base, ['priority' => 1]));
        Action::create(array_replace($base, ['actively' => false]));
        Action::create(array_replace($base, ['end' => now()->subHour()]));
        $this->assertSame([$first->id, $third->id], (new ActionResolver())->resolve()->pluck('id')->all());
    }

    public function test_api_uses_catalog_price_and_validation_returns_422(): void
    {
        $this->app->bind(ProductCatalog::class, fn () => $this->catalog());
        $this->postJson('/api/product-discounts/calculate', ['items' => [['configurationId' => 'one', 'quantity' => 1, 'price' => 1]]])
            ->assertOk()->assertJsonPath('totalPrice', 10000);
        $this->postJson('/api/product-discounts/calculate', ['items' => [['configurationId' => 'missing', 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.configurationId');
    }

    public function test_admin_crud_views_and_pivot_cleanup(): void
    {
        foreach (['actions', 'actions/create', 'doings', 'doings/create'] as $page) {
            $this->get('/admin/product-discounts/'.$page)->assertOk();
        }
        $rule = $this->action()->doings->first()->toArray();
        $this->post('/admin/product-discounts/doings', $rule)->assertSessionHasNoErrors()->assertRedirect();
        $doing = Doing::firstOrFail();
        $data = ['name' => 'Offer', 'start' => '2026-01-01', 'end' => '2027-01-01', 'priority' => 1, 'sort' => 1, 'actively' => 1, 'doings' => [$doing->id]];
        $this->post('/admin/product-discounts/actions', $data)->assertSessionHasNoErrors()->assertRedirect();
        $action = Action::firstOrFail();
        $this->get('/admin/product-discounts/actions/'.$action->id.'/edit')->assertOk()->assertSee('Offer');
        $this->get('/admin/product-discounts/doings/'.$doing->id.'/edit')->assertOk();
        $this->put('/admin/product-discounts/actions/'.$action->id, array_replace($data, ['name' => 'Updated']))->assertSessionHasNoErrors()->assertRedirect();
        $this->delete('/admin/product-discounts/doings/'.$doing->id)->assertRedirect();
        $this->assertDatabaseCount('product_discount_action_doing', 0);
        $this->delete('/admin/product-discounts/actions/'.$action->id)->assertRedirect();
        $this->assertDatabaseCount('product_discount_actions', 0);
    }

    public function test_admin_rejects_invalid_combinations(): void
    {
        $rule = $this->action(['condition_id' => 4, 'value' => '-10', 'size' => 101])->doings->first()->toArray();
        $this->post('/admin/product-discounts/doings', $rule)->assertSessionHasErrors(['comparison', 'size', 'value']);
        $this->assertDatabaseCount('product_discount_doings', 0);
    }
}
