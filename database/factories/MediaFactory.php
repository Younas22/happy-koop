<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $name = Str::lower(Str::random(20)).'.webp';

        return [
            'disk' => 'public',
            'path' => 'media/'.$name,
            'type' => 'image',
            'mime_type' => 'image/webp',
            'size_bytes' => 184320,
            'width' => 1200,
            'height' => 1200,
            'alt' => 'Fixture image',
            'original_filename' => 'fixture.webp',
            'folder' => 'media',
            'thumbnail_path' => 'media/thumb_'.$name,
        ];
    }
}
