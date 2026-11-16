<?php

namespace ProductDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Doing extends Model
{
    public const CONDITIONS = [2 => 'Бренд', 3 => 'Товар', 4 => 'Количество', 5 => 'Сумма', 7 => 'Категория', 8 => 'Промокод'];
    protected $table = 'product_discount_doings';
    protected $fillable = ['name', 'type', 'applie', 'product_id', 'type_discount', 'size', 'condition_id', 'value', 'comparison'];
    protected $casts = ['condition_id' => 'integer', 'size' => 'integer'];

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(Action::class, 'product_discount_action_doing', 'doing_id', 'action_id');
    }
}
