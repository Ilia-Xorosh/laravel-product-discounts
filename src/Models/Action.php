<?php

namespace ProductDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Action extends Model
{
    protected $table = 'product_discount_actions';
    protected $fillable = ['name', 'content', 'actively', 'start', 'end', 'priority', 'sort', 'stop_sort', 'stop_priority'];
    protected $casts = ['actively' => 'boolean', 'start' => 'datetime', 'end' => 'datetime', 'priority' => 'integer', 'sort' => 'integer', 'stop_sort' => 'integer', 'stop_priority' => 'integer'];

    public function doings(): BelongsToMany
    {
        return $this->belongsToMany(Doing::class, 'product_discount_action_doing', 'action_id', 'doing_id')->orderBy('product_discount_doings.id');
    }
}
