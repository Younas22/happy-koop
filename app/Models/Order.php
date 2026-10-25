<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_AUTHORIZED = 'authorized';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number', 'access_token', 'user_id', 'email', 'phone', 'currency',
        'subtotal_amount', 'discount_amount', 'shipping_amount', 'tax_amount',
        'total_amount', 'coupon_code', 'status', 'payment_status', 'payment_provider',
        'shipping_method_code', 'shipping_method_label', 'shipping_address',
        'billing_address', 'customer_note', 'admin_note', 'carrier',
        'tracking_number', 'tracking_url', 'refunded_amount',
        'placed_at', 'paid_at', 'cancelled_at', 'expires_at', 'inventory_committed_at',
        'placed_notified_at', 'delivered_at', 'delivery_notified_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'shipping_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'refunded_amount' => 'integer',
            'shipping_address' => 'json',
            'billing_address' => 'json',
            'placed_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'inventory_committed_at' => 'immutable_datetime',
            'placed_notified_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'delivery_notified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    protected function subtotal(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->subtotal_amount, $this->currency));
    }

    protected function discount(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->discount_amount, $this->currency));
    }

    protected function shipping(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->shipping_amount === null
            ? null
            : Money::of((int) $this->shipping_amount, $this->currency));
    }

    protected function tax(): Attribute
    {
        return Attribute::get(fn (): ?Money => $this->tax_amount === null
            ? null
            : Money::of((int) $this->tax_amount, $this->currency));
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->total_amount, $this->currency));
    }

    protected function refunded(): Attribute
    {
        return Attribute::get(fn (): Money => Money::of((int) $this->refunded_amount, $this->currency));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at < now()
            && $this->status === self::STATUS_PENDING
            && $this->payment_status === self::PAYMENT_UNPAID;
    }

    public function isPaidOrAuthorized(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_PAID, self::PAYMENT_AUTHORIZED], true);
    }

    public function refundableAmount(): Money
    {
        return $this->total->minus($this->refunded)->atLeastZero();
    }
}
