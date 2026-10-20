<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountReviewController extends ApiController
{
    /**
     * The customer's own reviews, including those still awaiting moderation.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = Review::query()
            ->where('user_id', $user->id)
            ->with('product')
            ->orderByDesc('id')
            ->paginate($this->perPage($request, 10, 60));

        return $this->paginated(
            $paginator,
            static fn (Review $review): array => ReviewResource::own($review)
        );
    }
}
