<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;

class PageController extends ApiController
{
    public function show(string $slug): JsonResponse
    {
        /** @var Page|null $page */
        $page = Page::query()->published()->where('slug', $slug)->first();

        abort_if($page === null, 404);

        return $this->data(PageResource::make($page));
    }
}
