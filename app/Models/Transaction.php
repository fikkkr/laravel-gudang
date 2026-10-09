<?php

namespace App\Models;

use App\Support\TransactionMoney;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'trx_no',
        'type',
        'warehouse_id',
        'destination_warehouse_id',
        'customer_id',
        'transaction_date',
        'status',
        'notes',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn (): string => TransactionMoney::sum(
            ($this->relationLoaded('items') ? $this->items : $this->items()->get(['subtotal']))
                ->pluck('subtotal')
        ));
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
