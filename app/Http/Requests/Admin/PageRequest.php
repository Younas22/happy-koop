<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class PageRequest extends ApiRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'slug' => [
                $required, 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($id),
            ],
            'title' => [$required, 'string', 'min:1', 'max:200'],
            'body' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'seoTitle' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seoDescription' => ['sometimes', 'nullable', 'string', 'max:320'],
            'seoNoindex' => ['sometimes', 'boolean'],
            'isPublished' => ['sometimes', 'boolean'],
            'publishedAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
