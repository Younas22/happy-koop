<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Ratings derived ONLY from approved, store-owned reviews.
 *
 * Returns null — never 0, never a seeded default — when a product has no
 * approved reviews (api-contract.md 4).
 */
class RatingResolver
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, array{average: float, count: int}> keyed by product id; missing = no reviews
     */
    public function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = Review::query()
            ->approved()
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->select([
                'product_id',
                DB::raw('COUNT(*) as reviews_count'),
                DB::raw('AVG(rating) as reviews_average'),
            ])
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $count = (int) $row->reviews_count;

            if ($count === 0) {
                continue;
            }

            $out[(int) $row->product_id] = [
                'average' => round((float) $row->reviews_average, 1),
                'count' => $count,
            ];
        }

        return $out;
    }

    /**
     * @return array{average: float, count: int}|null
     */
    public function forProduct(int $productId): ?array
    {
        return $this->forProducts([$productId])[$productId] ?? null;
    }

    /**
     * Review-list summary, including the star distribution.
     *
     * @return array{average: float, count: int, distribution: array<string, int>}|null
     */
    public function summary(int $productId): ?array
    {
        $rows = Review::query()
            ->approved()
            ->where('product_id', $productId)
            ->groupBy('rating')
            ->select(['rating', DB::raw('COUNT(*) as ratings_count')])
            ->get();

        $distribution = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
        $total = 0;
        $weighted = 0;

        foreach ($rows as $row) {
            $rating = (int) $row->rating;
            $count = (int) $row->ratings_count;

            if ($rating < 1 || $rating > 5) {
                continue;
            }

            $distribution[(string) $rating] = $count;
            $total += $count;
            $weighted += $rating * $count;
        }

        if ($total === 0) {
            return null;
        }

        return [
            'average' => round($weighted / $total, 1),
            'count' => $total,
            'distribution' => $distribution,
        ];
    }
}
