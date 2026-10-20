<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    public const STATUS_REQUIRES_PAYMENT = 'requires_payment';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id', 'provider', 'mode', 'status', 'provider_reference',
        'amount', 'currency', 'error_code', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function money(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->amount, $this->currency));
    }
}
