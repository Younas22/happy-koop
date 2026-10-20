<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Review;

final class ReviewResource
{
    /**
     * Public review. Reviewer email and user id are never exposed.
     *
     * @return array<string, mixed>
     */
    public static function make(Review $review): array
    {
        return [
            'id' => (int) $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'authorName' => $review->author_name,
            'isVerifiedPurchase' => (bool) $review->is_verified_purchase,
            'createdAt' => $review->created_at?->toIso8601String(),
            'helpfulCount' => (int) $review->helpful_count,
            'storeReply' => $review->store_reply,
        ];
    }

    /**
     * The customer's own review, including its moderation status.
     *
     * @return array<string, mixed>
     */
    public static function own(Review $review): array
    {
        return self::make($review) + [
            'status' => $review->status,
            'product' => $review->product === null ? null : [
                'id' => (int) $review->product->id,
                'name' => $review->product->name,
                'slug' => $review->product->slug,
            ],
        ];
    }

    /**
     * Admin moderation view.
     *
     * @return array<string, mixed>
     */
    public static function admin(Review $review): array
    {
        return self::own($review) + [
            'userId' => $review->user_id === null ? null : (int) $review->user_id,
            'orderItemId' => $review->order_item_id === null ? null : (int) $review->order_item_id,
            'moderatedBy' => $review->moderated_by === null ? null : (int) $review->moderated_by,
            'moderatedAt' => $review->moderated_at?->toIso8601String(),
            'updatedAt' => $review->updated_at?->toIso8601String(),
        ];
    }
}
