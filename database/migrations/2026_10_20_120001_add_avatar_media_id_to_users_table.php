<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional profile picture to users, stored as a reference to the
 * media library rather than a loose path, so avatars go through the same
 * validation, MIME sniffing and deletion rules as every other upload.
 *
 * Additive and reversible: the column is nullable and nothing existing moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('avatar_media_id')
                ->nullable()
                ->after('phone')
                ->constrained('media')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('avatar_media_id');
        });
    }
};
