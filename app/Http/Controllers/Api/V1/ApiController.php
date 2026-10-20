<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;

    /**
     * Single-resource / action envelope (api-contract.md 1.2).
     */
    protected function data(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json(['data' => $data], $status, $headers);
    }

    protected function ok(): JsonResponse
    {
        return $this->data(['ok' => true]);
    }

    /**
     * Paginated envelope with the contract's exact `meta` shape.
     *
     * @param  callable(mixed): mixed  $map
     */
    protected function paginated(LengthAwarePaginator $paginator, callable $map): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map($map, $paginator->items())),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Validates query-string parameters. Error keys stay snake_case because
     * query parameters are snake_case (api-contract.md 1.1), while request
     * bodies are camelCase and validated through a FormRequest.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validateQuery(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->query(), $rules, $messages);

        if ($validator->fails()) {
            throw ApiException::validation($validator->errors()->messages());
        }

        return $validator->validated();
    }

    protected function perPage(Request $request, int $default = 24, int $max = 60): int
    {
        $value = (int) $request->query('per_page', (string) $default);

        return max(1, min($max, $value === 0 ? $default : $value));
    }
}
