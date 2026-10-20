<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per provider webhook event. The unique index on `event_id` is the
 * idempotency guarantee: a replayed event cannot be inserted twice.
 */
class PaymentEvent extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider', 'event_id', 'type', 'order_id', 'payload',
        'status', 'processed_at', 'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'json',
            'attempts' => 'integer',
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
