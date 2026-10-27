<?php

declare(strict_types=1);

use Database\Seeders\Content\DutchPages;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-language page content. `translations` holds the other languages, e.g.
 * {"nl": {"title": …, "body": …}}; the page's own columns stay the default
 * language and the fallback.
 *
 * The seeded placeholder pages get their Dutch text here, but only while their
 * English body is still the placeholder: a page someone has rewritten would
 * otherwise get a translation of text it no longer contains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->json('translations')->nullable()->after('seo_noindex');
        });

        foreach (DutchPages::all() as $slug => $dutch) {
            $page = DB::table('pages')->where('slug', $slug)->first(['id', 'body', 'translations']);

            if ($page === null || $page->translations !== null) {
                continue;
            }

            if (! str_contains((string) $page->body, 'This page is placeholder content')) {
                continue;
            }

            DB::table('pages')->where('id', $page->id)->update([
                'translations' => json_encode(['nl' => $dutch], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
    }
};
