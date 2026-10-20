<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Services\Catalog\RatingResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends ApiController
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'highest' => ['rating', 'desc'],
        'lowest' => ['rating', 'asc'],
        'helpful' => ['helpful_count', 'desc'],
    ];

    public function __construct(private readonly RatingResolver $ratings) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        $this->validateQuery($request, [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ], [
            'sort.in' => 'The selected sort option is not supported.',
        ]);

        /** @var Product|null $product */
        $product = Product::query()->published()->where('slug', $slug)->first();

        abort_if($product === null, 404);

        $query = Review::query()->approved()->where('product_id', $product->id);

        if (($rating = $request->query('rating')) !== null && $rating !== '') {
            $query->where('rating', (int) $rating);
        }

        [$column, $direction] = self::SORTS[(string) $request->query('sort', 'newest')] ?? self::SORTS['newest'];

        $paginator = $query->orderBy($column, $direction)
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request, 10, 20))
            ->withQueryString();

        $response = $this->paginated(
            $paginator,
            static fn (Review $review): array => ReviewResource::make($review)
        );

        $payload = $response->getData(true);
        // null, not a zero-filled object, when there are no approved reviews.
        $payload['summary'] = $this->ratings->summary((int) $product->id);

        return response()->json($payload);
    }
}
