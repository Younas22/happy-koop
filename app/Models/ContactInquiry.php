<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A message sent through the storefront contact form.
 *
 * The record is the source of truth, not the email: it is written inside the
 * request's transaction, and the two notification emails are queued only after
 * that transaction commits. If mail delivery fails, the inquiry is still here.
 */
class ContactInquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_HANDLED = 'handled';

    protected $fillable = [
        'reference', 'name', 'email', 'subject', 'message',
        'ip_address', 'user_agent', 'user_id', 'status', 'handled_at', 'notified_at',
    ];

    /**
     * The IP address and user agent are kept for abuse handling only. They are
     * hidden so they cannot leak into an API response by accident.
     */
    protected $hidden = ['ip_address', 'user_agent'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A short, unambiguous reference the visitor can quote back.
     *
     * Crockford-style alphabet: no I, O, 1 or 0, so a reference read off a
     * screen and typed into a reply cannot turn into a different one.
     */
    public static function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'HK-'.$code;
    }

    /** The message, collapsed to a single line for list views. */
    public function excerpt(int $length = 120): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', (string) $this->message) ?? '', $length);
    }
}
