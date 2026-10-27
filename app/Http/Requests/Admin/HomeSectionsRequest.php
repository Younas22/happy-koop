<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\HomeSection;

class HomeSectionsRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['present', 'array', 'max:20'],
            'sections.*.type' => ['required', 'string', 'in:'.implode(',', HomeSection::types())],
            'sections.*.heading' => ['sometimes', 'nullable', 'string', 'max:160'],
            'sections.*.config' => ['sometimes', 'array'],
            'sections.*.config.imageMediaIds' => ['sometimes', 'nullable', 'array', 'max:10'],
            'sections.*.config.imageMediaIds.*' => ['integer', 'exists:media,id'],
            'sections.*.isActive' => ['sometimes', 'boolean'],
        ];
    }
}
