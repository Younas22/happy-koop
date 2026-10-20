<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Catalog\StoreReviewRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class ReviewController extends ApiController
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function store(StoreReviewRequest $request): JsonResponse
    {
        if (! $this->settings->getBool('features.reviews', true)) {
            throw ApiException::forbidden('Reviews are not enabled for this store.');
        }

        /** @var User $user */
        $user = $request->user();

        $this->authorize('create', Review::class);

        /** @var Product|null $product */
        $product = Product::query()->published()->find($request->integer('productId'));

        abort_if($product === null, 404);

        $exists = Review::query()
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            throw ApiException::conflict('You have already reviewed this product.');
        }

        // Verified-purchase status is derived server-side from paid orders.
        // The client cannot set it.
        $orderItem = $this->paidOrderItemFor($user, (int) $product->id);

        $review = Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_item_id' => $orderItem?->id,
            'rating' => $request->integer('rating'),
            'title' => $request->input('title') === null
                ? null
                : strip_tags((string) $request->input('title')),
            'body' => Purifier::clean(strip_tags((string) $request->input('body'))),
            'author_name' => $this->authorName($user),
            'status' => Review::STATUS_PENDING,
            'is_verified_purchase' => $orderItem !== null,
        ]);

        return $this->data([
            'id' => (int) $review->id,
            'status' => $review->status,
        ], 201);
    }

    private function paidOrderItemFor(User $user, int $productId): ?OrderItem
    {
        return OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', static fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereIn('payment_status', [Order::PAYMENT_PAID, Order::PAYMENT_PARTIALLY_REFUNDED]))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Public display name: given name plus a last-name initial, so a full name
     * is never published without need.
     */
    private function authorName(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        $first = $parts[0] ?? 'Customer';
        $last = count($parts) > 1 ? Str::upper(Str::substr((string) end($parts), 0, 1)).'.' : null;

        return trim($first.($last === null ? '' : ' '.$last));
    }
}
