<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Review;

class ReviewModerationRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:'.implode(',', [
                Review::STATUS_PENDING, Review::STATUS_APPROVED, Review::STATUS_REJECTED,
            ])],
            'storeReply' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
