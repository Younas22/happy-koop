<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

/**
 * Body of the admin bulk endpoints: the ids the action applies to. Ids that
 * no longer exist are skipped by the controller rather than rejected here, so
 * a list built from a slightly stale page still works for everything else.
 */
class BulkIdsRequest extends ApiRequest
{
    public const MAX_IDS = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('ids'))));
    }
}
