<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use App\Models\Page;
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

            // Other languages; an empty field falls back to the default language.
            'translations' => ['sometimes', 'nullable', 'array:'.implode(',', Page::TRANSLATION_LOCALES)],
            'translations.*' => ['nullable', 'array:title,body,excerpt,seoTitle,seoDescription'],
            'translations.*.title' => ['nullable', 'string', 'max:200'],
            'translations.*.body' => ['nullable', 'string', 'max:200000'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:500'],
            'translations.*.seoTitle' => ['nullable', 'string', 'max:160'],
            'translations.*.seoDescription' => ['nullable', 'string', 'max:320'],
        ];
    }
}
