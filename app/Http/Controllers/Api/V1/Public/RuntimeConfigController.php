<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Settings\RuntimeConfigService;
use Illuminate\Http\JsonResponse;

class RuntimeConfigController extends ApiController
{
    public function __construct(private readonly RuntimeConfigService $runtimeConfig) {}

    /**
     * The bootstrap endpoint (api-contract.md 3). Built from an explicit
     * allowlist; no secret can be reached from here.
     */
    public function show(): JsonResponse
    {
        return $this->data($this->runtimeConfig->cached(), 200, [
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300',
        ]);
    }
}
