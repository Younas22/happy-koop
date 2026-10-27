<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Exceptions\ApiException;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Secure media upload (api-contract.md 9).
 *
 * The real MIME type is sniffed server-side; the client-supplied
 * Content-Type and the filename extension are never trusted. Filenames are
 * generated, SVG is rejected by default, and executable extensions are
 * refused outright.
 */
class MediaUploadService
{
    /**
     * Extensions that must never be accepted, whatever the sniffed type says.
     *
     * @var list<string>
     */
    private const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
        'htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe',
        'dll', 'so', 'jsp', 'asp', 'aspx', 'js', 'html', 'htm', 'svg', 'svgz',
    ];

    /**
     * @var array<string, string> sniffed mime => canonical extension
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'video/mp4' => 'mp4',
        'image/svg+xml' => 'svg',
    ];

    public function store(UploadedFile $file, ?string $alt, ?string $folder, ?User $actor): Media
    {
        $this->assertSafeFilename($file->getClientOriginalName());

        $mime = $this->sniffMime($file);
        $type = $this->resolveType($mime);

        $this->assertSize($file, $type);

        $folder = $this->normalizeFolder($folder);
        $disk = (string) config('happykoop.media.disk', 'uploads');

        $extension = self::EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw $this->rejectType();
        }

        $width = null;
        $height = null;
        $thumbnailPath = null;

        $filename = Str::lower(Str::random(40)).'.'.$extension;
        $path = trim($folder.'/'.$filename, '/');

        if ($type === 'image' && $mime !== 'image/svg+xml') {
            [$width, $height] = $this->imageDimensions($file);
            $thumbnailPath = $this->makeThumbnail($file, $disk, $folder, $filename);
        }

        Storage::disk($disk)->putFileAs($folder, $file, $filename);

        return Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'type' => $type,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt' => $alt === null ? null : mb_substr(strip_tags($alt), 0, 255),
            // Kept as metadata only; never used to build the stored path.
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'folder' => $folder === '' ? null : $folder,
            'thumbnail_path' => $thumbnailPath,
            'uploaded_by' => $actor?->id,
        ]);
    }

    public function delete(Media $media): void
    {
        $disk = Storage::disk($media->disk ?: (string) config('happykoop.media.disk', 'uploads'));

        if ($media->thumbnail_path !== null) {
            $disk->delete($media->thumbnail_path);
        }

        $disk->delete($media->path);

        $media->delete();
    }

    /**
     * Sniffs the real type from the file contents.
     */
    private function sniffMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            throw ApiException::badRequest('The uploaded file could not be read.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if ($mime === false) {
            throw $this->rejectType();
        }

        $mime = strtolower($mime);

        $allowed = array_merge(
            (array) config('happykoop.media.allowed_image_mimes', []),
            (array) config('happykoop.media.allowed_video_mimes', [])
        );

        if ((bool) config('happykoop.media.allow_svg', false)) {
            $allowed[] = 'image/svg+xml';
        }

        if (! in_array($mime, $allowed, true)) {
            throw $this->rejectType();
        }

        return $mime;
    }

    private function resolveType(string $mime): string
    {
        return in_array($mime, (array) config('happykoop.media.allowed_video_mimes', []), true)
            ? 'video'
            : 'image';
    }

    private function assertSize(UploadedFile $file, string $type): void
    {
        $max = $type === 'video'
            ? (int) config('happykoop.media.max_video_bytes')
            : (int) config('happykoop.media.max_image_bytes');

        if ((int) $file->getSize() > $max) {
            $mb = (int) round($max / 1048576);

            throw ApiException::validation([
                'file' => ["This file is larger than the {$mb} MB limit for this media type."],
            ]);
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensions(UploadedFile $file): array
    {
        $info = @getimagesize((string) $file->getRealPath());

        if ($info === false) {
            throw ApiException::validation([
                'file' => ['This image could not be read.'],
            ]);
        }

        $max = (int) config('happykoop.media.max_dimension', 8000);

        if ((int) $info[0] > $max || (int) $info[1] > $max) {
            throw ApiException::validation([
                'file' => ["Images may be at most {$max} by {$max} pixels."],
            ]);
        }

        return [(int) $info[0], (int) $info[1]];
    }

    private function makeThumbnail(UploadedFile $file, string $disk, string $folder, string $filename): ?string
    {
        try {
            $manager = new ImageManager(new GdDriver);
            $image = $manager->read((string) $file->getRealPath());
            $image->scaleDown(width: (int) config('happykoop.media.thumbnail_width', 480));

            $thumbName = 'thumb_'.pathinfo($filename, PATHINFO_FILENAME).'.webp';
            $thumbPath = trim($folder.'/'.$thumbName, '/');

            Storage::disk($disk)->put($thumbPath, (string) $image->toWebp(82));

            return $thumbPath;
        } catch (Throwable $e) {
            // A missing thumbnail is not a reason to fail the upload.
            report($e);

            return null;
        }
    }

    private function assertSafeFilename(string $name): void
    {
        $name = strtolower(trim($name));

        if ($name === '' || str_contains($name, "\0")) {
            throw ApiException::validation(['file' => ['This filename is not allowed.']]);
        }

        if (str_starts_with(basename($name), '.')) {
            throw ApiException::validation(['file' => ['Dotfiles cannot be uploaded.']]);
        }

        $parts = explode('.', $name);
        array_shift($parts); // drop the base name

        // Every extension segment is checked, so "image.php.png" is rejected.
        foreach ($parts as $segment) {
            if (in_array($segment, self::FORBIDDEN_EXTENSIONS, true)) {
                throw ApiException::validation([
                    'file' => ['This file type is not allowed.'],
                ]);
            }
        }

        if (count($parts) > 1) {
            throw ApiException::validation([
                'file' => ['Filenames with multiple extensions are not allowed.'],
            ]);
        }
    }

    private function normalizeFolder(?string $folder): string
    {
        if ($folder === null) {
            return 'media';
        }

        $folder = preg_replace('/[^a-z0-9\-_\/]/', '', strtolower($folder)) ?? '';
        $folder = trim(str_replace(['..', '//'], '', $folder), '/');

        return $folder === '' ? 'media' : mb_substr($folder, 0, 80);
    }

    private function rejectType(): ApiException
    {
        return ApiException::validation([
            'file' => ['Only JPEG, PNG, WebP, AVIF images and MP4 video are accepted.'],
        ]);
    }
}
