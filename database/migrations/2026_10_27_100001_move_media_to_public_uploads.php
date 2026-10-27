<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Moves every uploaded file from storage/app/public (the `public` disk) to
 * public/uploads (the `uploads` disk) and points its media row there, so no
 * media is left under storage/.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->move('public', 'uploads');
    }

    public function down(): void
    {
        $this->move('uploads', 'public');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        DB::table('media')->where('disk', $from)->orderBy('id')->each(function (object $media) use ($source, $target, $to): void {
            foreach (array_filter([$media->path, $media->thumbnail_path]) as $path) {
                if ($source->exists($path) && ! $target->exists($path)) {
                    $target->put($path, (string) $source->get($path));
                }

                if ($target->exists($path)) {
                    $source->delete($path);
                }
            }

            DB::table('media')->where('id', $media->id)->update(['disk' => $to]);
        });
    }
};
