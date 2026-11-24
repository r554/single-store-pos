<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'product_id', 'name',
        'price', 'qty', 'discount', 'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'qty' => 'integer',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
