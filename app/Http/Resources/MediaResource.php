<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;

final class MediaResource
{
    /**
     * Full media object used in the product gallery.
     *
     * @return array<string, mixed>
     */
    public static function make(Media $media, int $sortOrder = 0): array
    {
        return [
            'id' => (int) $media->id,
            'type' => $media->type,
            'url' => $media->url(),
            'thumbnailUrl' => $media->thumbnailUrl(),
            'alt' => $media->alt,
            'width' => $media->width === null ? null : (int) $media->width,
            'height' => $media->height === null ? null : (int) $media->height,
            'sortOrder' => $sortOrder,
        ];
    }

    /**
     * Compact image reference used by product cards.
     *
     * @return array<string, mixed>|null
     */
    public static function imageRef(?Media $media): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'id' => (int) $media->id,
            'url' => $media->url(),
            'alt' => $media->alt,
            'width' => $media->width === null ? null : (int) $media->width,
            'height' => $media->height === null ? null : (int) $media->height,
        ];
    }

    /**
     * Upload response (api-contract.md 9).
     *
     * @return array<string, mixed>
     */
    public static function upload(Media $media): array
    {
        return [
            'id' => (int) $media->id,
            'url' => $media->url(),
            'thumbnailUrl' => $media->thumbnailUrl(),
            'type' => $media->type,
            'mimeType' => $media->mime_type,
            'width' => $media->width === null ? null : (int) $media->width,
            'height' => $media->height === null ? null : (int) $media->height,
            'sizeBytes' => (int) $media->size_bytes,
            'alt' => $media->alt,
            'folder' => $media->folder,
            'originalFilename' => $media->original_filename,
            'createdAt' => $media->created_at?->toIso8601String(),
        ];
    }
}
