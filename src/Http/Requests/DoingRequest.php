<?php

namespace ProductDiscounts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ProductDiscounts\Models\Doing;

class DoingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Access is enforced by configured route middleware.
    }

    public function rules(): array
    {
        return self::rulesForDoing();
    }

    public static function rulesForDoing(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['Скидка', 'Наценка', 'Фиксированная цена'])],
            'applie' => ['required', Rule::in(['На товар', 'На корзину'])],
            'condition_id' => ['required', 'integer', Rule::in(array_keys(Doing::CONDITIONS))],
            'comparison' => ['required', function ($attribute, $value, $fail) {
                // Conditional comparisons are additionally checked in after().
                if (!in_array($value, ['Равно', 'Не равно', 'Больше или равно', 'Меньше или равно'], true)) {
                    $fail('Недопустимое сравнение.');
                }
            }],
            'type_discount' => ['required', Rule::in(['Процент', 'Сумма'])],
            'size' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            foreach (self::semanticErrors($this->all()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }

    public static function semanticErrors(array $data): array
    {
        $errors = [];
        $condition = (int) ($data['condition_id'] ?? 0);
        if ((in_array($condition, [2, 3, 7], true) || ($data['applie'] ?? '') === 'На товар') && empty($data['product_id']) && ($data['product_id'] ?? null) !== '0') {
            $errors['product_id'] = 'Укажите ID товара, бренда или категории.';
        }
        $comparisons = in_array($condition, [4, 5], true) ? ['Больше или равно', 'Меньше или равно'] : ($condition === 8 ? ['Равно'] : ['Равно', 'Не равно']);
        if (!in_array($data['comparison'] ?? '', $comparisons, true)) {
            $errors['comparison'] = 'Сравнение не соответствует условию.';
        }
        if (in_array($condition, [4, 5], true) && (!ctype_digit((string) ($data['value'] ?? '')) || (float) $data['value'] > 1000000000000)) {
            $errors['value'] = 'Введите целое неотрицательное число до 1000000000000.';
        }
        if ($condition === 8 && trim((string) ($data['value'] ?? '')) === '') {
            $errors['value'] = 'Укажите промокод.';
        }
        if (($data['type_discount'] ?? '') === 'Процент' && (int) ($data['size'] ?? 0) > 100) {
            $errors['size'] = 'Процент должен быть от 0 до 100.';
        }
        return $errors;
    }
}
