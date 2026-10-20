<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports the bundled demo images in `database/seeders/assets` into the media
 * library.
 *
 * The files live in the repository rather than being read from a developer's
 * Downloads folder, so a fresh clone seeds identically.
 *
 * Idempotent: an asset already imported into the same folder is reused, so
 * re-running never duplicates rows or re-copies files. Stored filenames are
 * generated (never taken from the source name), matching MediaUploadService.
 */
class MediaLibrarySeeder extends Seeder
{
    /** Images shipped with the repo, keyed by the alt text they carry. */
    private const ASSETS = [
        'catalog' => [
            'divider-set-of-4.jpg' => 'Four bamboo drawer dividers shown against a white background, labelled "Set of 4"',
            'divider-install-anti-slip.jpg' => 'Hands fitting a spring-loaded bamboo divider into a drawer, with a close-up of the anti-slip end pad',
            'divider-kitchen-drawer.jpg' => 'A person placing a utensil into a kitchen drawer split into sections by bamboo dividers',
            'divider-utensil-before-after.jpg' => 'Before and after comparison of a kitchen utensil drawer, unsorted above and divided into sections below',
            'divider-clothing-drawer.jpg' => 'A bedroom drawer separated into four compartments of folded clothing by bamboo dividers',
            'divider-vanity-drawer.jpg' => 'A vanity drawer with cosmetics arranged into sections by bamboo dividers',
        ],
        'avatars' => [
            'person-thumbs-up.png' => 'Profile photo of the store administrator',
            'person-pointing.png' => 'Profile photo of the demo customer account',
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('MediaLibrarySeeder skipped: demo media is not seeded in production.');

            return;
        }

        $imported = 0;
        $reused = 0;

        foreach (self::ASSETS as $folder => $files) {
            foreach ($files as $filename => $alt) {
                $existing = Media::query()
                    ->where('folder', $folder)
                    ->where('original_filename', $filename)
                    ->first();

                if ($existing !== null) {
                    $reused++;

                    continue;
                }

                if (self::import($filename, $folder, $alt) !== null) {
                    $imported++;
                }
            }
        }

        $this->command?->info("Media library ensured ({$imported} imported, {$reused} already present).");
    }

    /**
     * Copies one bundled asset onto the public disk and records it.
     *
     * Returns null when the asset file is missing, so a partial checkout
     * degrades to "no image" rather than aborting the whole seed.
     */
    public static function import(string $filename, string $folder, ?string $alt): ?Media
    {
        $source = database_path('seeders/assets/'.$filename);

        if (! is_file($source)) {
            return null;
        }

        $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $storedName = Str::lower(Str::random(40)).'.'.$extension;
        $path = $folder.'/'.$storedName;

        Storage::disk('public')->put($path, (string) file_get_contents($source));

        $dimensions = @getimagesize($source);

        return Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'type' => 'image',
            'mime_type' => $mime,
            'size_bytes' => (int) filesize($source),
            'width' => $dimensions === false ? null : (int) $dimensions[0],
            'height' => $dimensions === false ? null : (int) $dimensions[1],
            'alt' => $alt,
            // Metadata only. It is never used to build the stored path, but it
            // is what makes this seeder idempotent.
            'original_filename' => $filename,
            'folder' => $folder,
            'thumbnail_path' => null,
            'uploaded_by' => null,
        ]);
    }

    /** Looks up a previously imported asset by its source filename. */
    public static function find(string $filename): ?Media
    {
        return Media::query()->where('original_filename', $filename)->first();
    }
}
