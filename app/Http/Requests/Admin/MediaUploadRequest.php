<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;

class MediaUploadRequest extends ApiRequest
{
    /**
     * Only coarse checks here: the real MIME type is sniffed server-side by
     * MediaUploadService, which ignores the client Content-Type entirely.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) (((int) config('happykoop.media.max_video_bytes')) / 1024)],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'folder' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
