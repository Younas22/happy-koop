<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ReviewModerationRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\AuditLogger;
use App\Support\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Review::class);

        $this->validateQuery($request, [
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'product' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $query = Review::query()->with('product')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($productId = $request->query('product')) {
            $query->where('product_id', (int) $productId);
        }

        return $this->paginated(
            $query->paginate($this->perPage($request)),
            static fn (Review $review): array => ReviewResource::admin($review)
        );
    }

    public function update(ReviewModerationRequest $request, int $id): JsonResponse
    {
        /** @var Review|null $review */
        $review = Review::query()->with('product')->find($id);

        abort_if($review === null, 404);

        $this->authorize('update', $review);

        $attributes = [];

        if ($request->has('status')) {
            $attributes['status'] = (string) $request->input('status');
            $attributes['moderated_by'] = $request->user()?->id;
            $attributes['moderated_at'] = now();
        }

        if ($request->has('storeReply')) {
            $attributes['store_reply'] = Html::text($request->input('storeReply'), 2000);
        }

        $review->forceFill($attributes)->save();

        $this->audit->log('review.moderated', $request->user()?->id, $review, array_keys($attributes), $request);

        return $this->data(ReviewResource::admin($review->refresh()->load('product')));
    }
}
